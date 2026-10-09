<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Video;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Throwable;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Remote\FetchRefused;
use WebxUi\Media\Remote\RemoteFetcher;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Media\Storage\FileStore;

/**
 * The preview of a YouTube or Vimeo video without a poster of its own, kept on the site (§10):
 * a visitor never asks the provider for it — not before consent, not after.
 *
 * Where it is kept: the media library, the folder "Video posters" at its root, a picture named
 * `<provider>-<id>` (`youtube-aqz-KE-bpKQ`) — through the library's own fetch from an address
 * (`RemoteFetcher`: public addresses only, the upload limit) and its own store (the pipeline
 * makes a WebP of it). The owner sees the posters, can replace one by picking a poster for the
 * video, and can delete them: the next page fetches again. The cache remembers which file is
 * whose (`webx-widgets.video.poster.<name>`), so a poster renamed in the panel or deduplicated
 * into another file's bytes is still found; without the cache the name finds it.
 *
 * The fetch is never in the visitor's way: the page that first shows the video shows it without
 * a poster, and the fetch runs after its response has gone (`terminating`). A failure is
 * remembered for a day — a video taken down is not asked for on every page.
 *
 * Without `module-media` there is nowhere to keep it, and nothing is fetched: the video stands on
 * the frame's own background.
 */
final class Posters
{
    public const string FOLDER = 'Video posters';

    private const string KEPT = 'webx-widgets.video.poster.';

    private const string FAILED = 'webx-widgets.video.poster-failed.';

    private const string LOCK = 'webx-widgets.video.poster-lock.';

    /** @var array<string, true> What this request has already put off until its response is gone. */
    private array $scheduled = [];

    public function __construct(
        private readonly Container $app,
        private readonly Cache $cache,
    ) {}

    /** Whether the site has a library to keep posters in: its provider ran, not merely its classes are there. */
    public function available(): bool
    {
        return class_exists(MediaFile::class) && class_exists(RemoteFetcher::class) && $this->app->bound(MediaFiles::class);
    }

    /**
     * The poster kept for a video — `url`, `width`, `height` — or null, with its fetch put off
     * until this response is gone.
     *
     * @return array{url: string, width: ?int, height: ?int}|null
     */
    public function find(ProvidedVideo $video): ?array
    {
        if (! $this->available()) {
            return null;
        }

        try {
            $file = $this->kept($video);
        } catch (Throwable) {
            // The library is installed and not migrated: no poster, and no fetch to fail on it.
            return null;
        }

        if ($file !== null) {
            $details = $this->app->make(MediaValues::class)->resolve(['path' => $file->path]);

            if (is_array($details) && is_string($details['url'] ?? null)) {
                return [
                    'url' => $details['url'],
                    'width' => is_int($details['width'] ?? null) ? $details['width'] : null,
                    'height' => is_int($details['height'] ?? null) ? $details['height'] : null,
                ];
            }
        }

        $this->schedule($video);

        return null;
    }

    /**
     * Fetch a video's preview into the library now, unless it is there already, another request
     * is fetching it, or it failed less than a day ago. Null when there is none.
     */
    public function fetch(ProvidedVideo $video): ?MediaFile
    {
        $name = self::name($video);

        if (! $this->available() || $this->cache->has(self::FAILED.$name)) {
            return null;
        }

        if (($file = $this->kept($video)) !== null) {
            return $file;
        }

        if (! $this->cache->add(self::LOCK.$name, true, 120)) {
            return null;
        }

        try {
            $bytes = $video->provider->preview($video, function (string $url): ?string {
                try {
                    return $this->app->make(RemoteFetcher::class)->fetch($url)->body;
                } catch (FetchRefused) {
                    return null;
                }
            });
            $file = is_string($bytes) && $bytes !== '' ? $this->store($bytes, $name) : null;
        } catch (Throwable) {
            $file = null;
        } finally {
            $this->cache->forget(self::LOCK.$name);
        }

        if ($file === null) {
            $this->cache->put(self::FAILED.$name, true, 86400);

            return null;
        }

        $this->cache->forever(self::KEPT.$name, $file->path);

        return $file;
    }

    /** `youtube-aqz-KE-bpKQ`: the picture's name in the library, and its key in the cache. */
    public static function name(ProvidedVideo $video): string
    {
        return $video->provider->key().'-'.$video->id;
    }

    private function kept(ProvidedVideo $video): ?MediaFile
    {
        $name = self::name($video);
        $path = $this->cache->get(self::KEPT.$name);
        $file = is_string($path) ? MediaFile::query()->where('path', $path)->first() : null;

        if ($file === null && ($folder = $this->folder(create: false)) !== null) {
            $file = MediaFile::query()->where('directory_id', $folder->getKey())->where('name', $name)->first();

            if ($file !== null) {
                $this->cache->forever(self::KEPT.$name, $file->path);
            }
        }

        return $file;
    }

    private function schedule(ProvidedVideo $video): void
    {
        $name = self::name($video);

        if (isset($this->scheduled[$name]) || $this->cache->has(self::FAILED.$name)) {
            return;
        }

        $this->scheduled[$name] = true;

        // After the response: the visitor who happened to be first does not wait for YouTube.
        if ($this->app instanceof Application) {
            $this->app->terminating(function () use ($video): void {
                $this->fetch($video);
            });
        }
    }

    private function store(string $bytes, string $name): ?MediaFile
    {
        $mime = null;
        $temporary = tempnam(sys_get_temp_dir(), 'webx-poster');

        if ($temporary === false) {
            return null;
        }

        try {
            file_put_contents($temporary, $bytes);
            $mime = MimeTypes::getDefault()->guessMimeType($temporary);
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;

            // Whatever answered, only a picture becomes a poster.
            if ($extension === null || ($folder = $this->folder(create: true)) === null) {
                return null;
            }

            return $this->app->make(FileStore::class)->store(new UploadedFile($temporary, "{$name}.{$extension}", $mime, test: true), $folder);
        } finally {
            @unlink($temporary);
        }
    }

    private function folder(bool $create): ?MediaDirectory
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->orderBy('lft')->first();

        if (! $root instanceof MediaDirectory) {
            return null;
        }

        $folder = MediaDirectory::query()->where('parent_id', $root->getKey())->where('title', self::FOLDER)->first();

        if ($folder === null && $create) {
            $folder = new MediaDirectory(['title' => self::FOLDER]);
            $folder->appendTo($root);
        }

        return $folder;
    }
}
