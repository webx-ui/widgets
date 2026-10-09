<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Video\Posters;
use WebxUi\Widgets\Video\VideoProviders;

/**
 * `<x-webx-video src="https://www.youtube.com/watch?v=…" :poster="$image" title="…" />` and
 * `<x-webx-video :file="$media" :poster="$image" />` — a video in the page (spec §10).
 *
 * YouTube and Vimeo are a facade: the poster and a play button, and the player only on a click.
 * Before the visitor agreed to `media` the server prints the placeholder of §9.4 in its place —
 * it reads the answer from the cookie, so nothing flashes — with "Load" (this one) and "Always
 * load videos" (the consent). The poster is the site's: the one given, or the video's preview
 * the site fetched once into its media library ({@see Posters}). A file of the site is a
 * `<video preload="none">` and asks for nothing: it is first party.
 *
 * `poster` and `file` take what a template has in hand: a value of a media field (`url`, `width`,
 * `height`, `mime`), an object with `url()`, or an address. The frame keeps its `ratio` — `16/9`
 * unless told — before anything has loaded. Without JavaScript the facade is a link to the video.
 */
final class Video extends Component
{
    public const string RATIO = '16 / 9';

    /** The file types a `<video>` is told, by extension. */
    private const array TYPES = ['mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg', 'mov' => 'video/quicktime'];

    /** `youtube`, `vimeo`, … — or `file`. */
    public string $kind;

    public string $ratio;

    public string $name;

    public ?string $posterUrl;

    public ?int $posterWidth;

    public ?int $posterHeight;

    /** The provider's page of the video, or the file: where the link without JavaScript leads. */
    public string $href;

    public ?string $fileType = null;

    /** Before consent to `media`: the placeholder of §9.4 rather than the facade. */
    public bool $blocked = false;

    public string $notice = '';

    /** @var array<string, string> What the script is told, `data-webx-video`. */
    public array $config = [];

    public function __construct(
        ?string $src = null,
        mixed $file = null,
        mixed $poster = null,
        ?string $title = null,
        float|int|string|null $ratio = null,
    ) {
        $src = self::filled($src);
        $fileUrl = self::filled(is_string($file) ? $file : (self::read($file, 'url') ?? self::read($file, 'src')));

        if (($src === null) === ($fileUrl === null)) {
            throw new InvalidArgumentException('<x-webx-video>: either `src` — an address of YouTube or Vimeo — or `file`, a video of the site.');
        }

        $this->ratio = self::ratio($ratio);
        $this->name = self::filled($title) ?? (string) __('webx-widgets::widgets.video.untitled');
        $this->posterUrl = self::filled(is_string($poster) ? $poster : self::read($poster, 'url'));
        $this->posterWidth = self::size(self::read($poster, 'width'));
        $this->posterHeight = self::size(self::read($poster, 'height'));

        if ($fileUrl !== null) {
            $this->kind = 'file';
            $this->href = $fileUrl;
            $mime = self::filled(self::read($file, 'mime'));
            $this->fileType = $mime !== null && str_starts_with($mime, 'video/')
                ? $mime
                : (self::TYPES[strtolower(pathinfo((string) parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION))] ?? null);

            return;
        }

        $providers = app(VideoProviders::class);
        $video = $providers->find((string) $src)
            ?? throw new InvalidArgumentException("<x-webx-video>: \"{$src}\" is not an address of ".implode(' or ', $providers->labels()).'; a video of the site goes in `file`.');

        $this->kind = $video->provider->key();
        $this->href = $video->provider->page($video);
        $this->blocked = ! app(Consent::class)->has('media');
        $this->notice = (string) __('webx-widgets::widgets.video.notice', ['provider' => $video->provider->label()]);
        $this->config = ['embed' => $video->provider->embed($video), 'title' => $this->name];

        if ($this->posterUrl === null && ($kept = app(Posters::class)->find($video)) !== null) {
            [$this->posterUrl, $this->posterWidth, $this->posterHeight] = [$kept['url'], $kept['width'], $kept['height']];
        }
    }

    public function render(): View
    {
        Widgets::need('video');

        return view('webx-widgets::components.video');
    }

    /** `16/9`, `4:3`, `1.5`, `9 / 16` → `W / H`, the value of `aspect-ratio`. */
    private static function ratio(float|int|string|null $ratio): string
    {
        if ($ratio === null || $ratio === '') {
            return self::RATIO;
        }

        if (is_string($ratio) && preg_match('~^\s*(\d+(?:\.\d+)?)\s*[/:]\s*(\d+(?:\.\d+)?)\s*$~', $ratio, $parts) === 1 && (float) $parts[1] > 0 && (float) $parts[2] > 0) {
            return "{$parts[1]} / {$parts[2]}";
        }

        if (is_numeric($ratio) && (float) $ratio > 0) {
            return ((string) (float) $ratio).' / 1';
        }

        throw new InvalidArgumentException("<x-webx-video>: a ratio is \"16/9\", \"4:3\" or a number, not \"{$ratio}\".");
    }

    /** A key of an array, a method or a property of an object; nothing else has one. */
    private static function read(mixed $value, string $key): mixed
    {
        return match (true) {
            is_array($value) => $value[$key] ?? null,
            is_object($value) && method_exists($value, $key) => $value->{$key}(),
            is_object($value) && isset($value->{$key}) => $value->{$key},
            default => null,
        };
    }

    private static function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function size(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
