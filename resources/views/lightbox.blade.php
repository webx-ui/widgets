{{--
    What the lightbox's script is told (spec §8), once before </body> on a page that has one: the
    words in the page's language and the icons of the chain — a theme's arrow is the lightbox's.
--}}
@php
    $icon = static fn (string $name): string => \Illuminate\Support\Facades\Blade::render('<x-webx-icon :name="$name" class="webx-lightbox__icon" />', ['name' => $name]);
    $settings = [
        'words' => [
            'dialog' => __('webx-widgets::widgets.lightbox.dialog'),
            'carousel' => __('webx-widgets::widgets.slider.carousel'),
            'slide' => __('webx-widgets::widgets.slider.slide'),
            'close' => __('webx-widgets::widgets.lightbox.close'),
            'zoom' => __('webx-widgets::widgets.lightbox.zoom'),
            'prev' => __('webx-widgets::widgets.lightbox.prev'),
            'next' => __('webx-widgets::widgets.lightbox.next'),
            'error' => __('webx-widgets::widgets.lightbox.error'),
        ],
        'icons' => [
            'prev' => trim($icon('chevron-left')),
            'next' => trim($icon('chevron-right')),
            'close' => trim($icon('close')),
            'zoom' => trim($icon('zoom')),
        ],
    ];
@endphp
<script type="application/json" id="webx-lightbox">{!! json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
