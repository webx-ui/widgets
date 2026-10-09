<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Video;

use Closure;

/**
 * A place videos are watched at, for `<x-webx-video src="…">` (spec §10): which of its addresses
 * are its own, where its player is embedded, where a visitor without JavaScript watches, and
 * where its preview is — which the site fetches once for itself, so that no visitor ever asks the
 * place for it (§9.4).
 *
 * YouTube and Vimeo are in the package; a site registers another through {@see VideoProviders}.
 */
interface VideoProvider
{
    /** The key: `youtube`, the modifier `webx-video--youtube`, the poster's name in the library. */
    public function key(): string;

    /** What a visitor reads: "The video loads from YouTube…". */
    public function label(): string;

    /** The video an address names, or null when the address is not this provider's. */
    public function find(string $url): ?ProvidedVideo;

    /** The player, playing at once: it is put in only on a click. */
    public function embed(ProvidedVideo $video): string;

    /** The video's own page: the link a page without JavaScript shows. */
    public function page(ProvidedVideo $video): string;

    /**
     * The bytes of the video's preview picture, or null when it has none to give.
     *
     * @param  Closure(string): ?string  $fetch  the body at an address of the public internet, null when it failed
     */
    public function preview(ProvidedVideo $video, Closure $fetch): ?string;
}
