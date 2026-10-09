<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Video;

use Closure;

/**
 * Vimeo: `vimeo.com/<id>`, an unlisted `vimeo.com/<id>/<hash>`, `player.vimeo.com/video/<id>?h=`,
 * and the addresses of channels, groups and showcases that end in the id. The player asks Vimeo
 * not to track (`dnt=1`); the preview is the one Vimeo's oEmbed names — it has no address that
 * can be worked out from the id.
 */
final class Vimeo implements VideoProvider
{
    public function key(): string
    {
        return 'vimeo';
    }

    public function label(): string
    {
        return 'Vimeo';
    }

    public function find(string $url): ?ProvidedVideo
    {
        $parts = parse_url(trim($url));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        parse_str((string) ($parts['query'] ?? ''), $query);
        $hash = is_string($query['h'] ?? null) && preg_match('/^[a-f0-9]+$/i', $query['h']) === 1 ? $query['h'] : null;

        if ($host === 'player.vimeo.com') {
            $found = preg_match('~^/video/(\d+)$~', $path, $m) === 1;
        } elseif (preg_match('/^(?:www\.)?vimeo\.com$/', $host) === 1) {
            $found = preg_match('~^/(?:channels/[\w-]+/|groups/[\w-]+/videos/|showcase/\d+/video/)?(\d+)(?:/([a-f0-9]+))?$~i', $path, $m) === 1;
            $hash = isset($m[2]) && $m[2] !== '' ? $m[2] : $hash;
        } else {
            return null;
        }

        if (! $found) {
            return null;
        }

        $start = preg_match('/(?:^|&)t=([\dhms]+)/', (string) ($parts['fragment'] ?? ''), $t) === 1 ? $t[1] : null;

        return new ProvidedVideo($this, $m[1], $hash, ProvidedVideo::seconds($start));
    }

    public function embed(ProvidedVideo $video): string
    {
        $query = http_build_query(array_filter(['h' => $video->hash, 'autoplay' => 1, 'dnt' => 1]));

        return "https://player.vimeo.com/video/{$video->id}?{$query}".($video->start ? "#t={$video->start}s" : '');
    }

    public function page(ProvidedVideo $video): string
    {
        return "https://vimeo.com/{$video->id}".($video->hash !== null ? "/{$video->hash}" : '').($video->start ? "#t={$video->start}s" : '');
    }

    public function preview(ProvidedVideo $video, Closure $fetch): ?string
    {
        $answer = $fetch('https://vimeo.com/api/oembed.json?'.http_build_query(['url' => $this->page($video), 'width' => 1280]));
        $answer = is_string($answer) ? json_decode($answer, true) : null;
        $picture = is_array($answer) ? ($answer['thumbnail_url'] ?? null) : null;

        return is_string($picture) && str_starts_with($picture, 'https://') ? $fetch($picture) : null;
    }
}
