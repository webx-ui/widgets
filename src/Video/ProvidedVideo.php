<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Video;

/**
 * One video of a provider: its id there, the private hash an unlisted Vimeo video needs, and
 * the second it starts at when the address said so (`?t=90`, `#t=1m30s`).
 */
final readonly class ProvidedVideo
{
    public function __construct(
        public VideoProvider $provider,
        public string $id,
        public ?string $hash = null,
        public int $start = 0,
    ) {}

    /** Seconds from `90`, `90s`, `1m30s`, `1h2m3s`; zero for anything else. */
    public static function seconds(?string $time): int
    {
        if ($time === null || preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$/', $time, $parts) !== 1) {
            return 0;
        }

        return (int) ($parts[1] ?? 0) * 3600 + (int) ($parts[2] ?? 0) * 60 + (int) ($parts[3] ?? 0);
    }
}
