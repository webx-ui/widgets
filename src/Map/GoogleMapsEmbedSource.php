<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Map;

use WebxUi\Admin\Screens\Embeds;
use WebxUi\Admin\Screens\EmbedSource;

/**
 * `{ "type": "wx-embed", "props": { "source": "google-maps" } }` — the field of the Map block's
 * "Google Maps embed" (spec §11.2), registered with the panel's {@see Embeds}
 * when the site has it. Loaded only then: the interface is `module-admin`'s, not required here.
 */
final class GoogleMapsEmbedSource implements EmbedSource
{
    public function address(string $pasted): ?string
    {
        return GoogleMapsEmbed::address($pasted);
    }

    public function refusal(): string
    {
        return (string) __('webx-widgets::widgets.map.embed_refused');
    }
}
