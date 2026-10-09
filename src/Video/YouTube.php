<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Video;

use Closure;

/**
 * YouTube: `watch?v=`, `youtu.be/`, `/shorts/`, `/embed/`, `/live/` on `www.`, `m.`, `music.`
 * and `youtube-nocookie.com`. The player is the one of `youtube-nocookie.com` (§10), the preview
 * the largest of `i.ytimg.com` it has — `maxresdefault` is missing for older and smaller videos.
 */
final class YouTube implements VideoProvider
{
    private const string ID = '[A-Za-z0-9_-]{11}';

    public function key(): string
    {
        return 'youtube';
    }

    public function label(): string
    {
        return 'YouTube';
    }

    public function find(string $url): ?ProvidedVideo
    {
        $parts = parse_url(trim($url));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        $id = match (true) {
            $host === 'youtu.be' => preg_match('~^/('.self::ID.')$~', $path, $m) === 1 ? $m[1] : null,
            preg_match('/^(?:(?:www|m|music)\.)?youtube(?:-nocookie)?\.com$/', $host) === 1 => match (true) {
                $path === '/watch' && is_string($query['v'] ?? null) && preg_match('/^'.self::ID.'$/', $query['v']) === 1 => $query['v'],
                preg_match('~^/(?:embed|shorts|live|v)/('.self::ID.')/?$~', $path, $m) === 1 => $m[1],
                default => null,
            },
            default => null,
        };

        if ($id === null) {
            return null;
        }

        $start = is_string($query['t'] ?? null) ? $query['t'] : (is_string($query['start'] ?? null) ? $query['start'] : null);

        return new ProvidedVideo($this, $id, start: ProvidedVideo::seconds($start));
    }

    public function embed(ProvidedVideo $video): string
    {
        // `playsinline`: an iPhone would otherwise take the click to its own full-screen player.
        // `rel=0`: the videos offered at the end are the same channel's, not anybody's.
        return "https://www.youtube-nocookie.com/embed/{$video->id}?".http_build_query(array_filter([
            'autoplay' => 1,
            'playsinline' => 1,
            'rel' => '0',
            'start' => $video->start ?: null,
        ], static fn (mixed $value): bool => $value !== null));
    }

    public function page(ProvidedVideo $video): string
    {
        return "https://www.youtube.com/watch?v={$video->id}".($video->start ? "&t={$video->start}s" : '');
    }

    public function preview(ProvidedVideo $video, Closure $fetch): ?string
    {
        foreach (['maxresdefault', 'hqdefault'] as $size) {
            $bytes = $fetch("https://i.ytimg.com/vi/{$video->id}/{$size}.jpg");

            if (is_string($bytes) && $bytes !== '') {
                return $bytes;
            }
        }

        return null;
    }
}
