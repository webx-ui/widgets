<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Video;

/**
 * Whose address it is: YouTube and Vimeo, and whatever a site registers in a provider of its own
 * — `app(VideoProviders::class)->register(new MyProvider)` — with no change to the component.
 * A later one with the same key replaces the earlier.
 */
final class VideoProviders
{
    /** @var array<string, VideoProvider> */
    private array $providers = [];

    public function __construct()
    {
        $this->register(new YouTube);
        $this->register(new Vimeo);
    }

    public function register(VideoProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function find(string $url): ?ProvidedVideo
    {
        foreach ($this->providers as $provider) {
            if (($video = $provider->find($url)) !== null) {
                return $video;
            }
        }

        return null;
    }

    /** @return list<string> What a visitor reads, for an error that says which addresses are taken. */
    public function labels(): array
    {
        return array_values(array_map(static fn (VideoProvider $provider): string => $provider->label(), $this->providers));
    }
}
