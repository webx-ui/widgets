{{--
    A map (spec §11). The place — the address and "Open in maps" — in a frame of the map's height:
    the map without JavaScript. Before consent to media the notice of §9.4 stands in it, its
    buttons the script's; with consent the script puts the map over it. The attribution of the
    tiles is printed under every map. An address with no coordinates is the place alone.

    Classes: webx-map, --place (no coordinates), __place, __place--static, __address, __open, __notice, __actions,
    __button, __button--always, __attribution, __canvas, __marker, __hint;
    is-blocked (before consent), is-ready (the script took it), is-loaded (the map is in),
    is-active (clicked or focused: the wheel zooms).
--}}
@if ($hasMap())
<div {{ $attributes->class(['webx-map', 'is-blocked' => $blocked])->merge(['style' => "--webx-map-height: {$height}"]) }} role="region" aria-label="{{ __('webx-widgets::widgets.map.label', ['place' => $name]) }}" data-webx-map="{{ json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}" data-webx-consent="media">
    <div class="webx-map__place">
        @if ($address)<p class="webx-map__address">{{ $address }}</p>@endif
        @if ($href)<a class="webx-map__open" href="{{ $href }}" target="_blank" rel="noopener">{{ __('webx-widgets::widgets.map.open') }}</a>@endif
        @if ($blocked)
        <p class="webx-map__notice">{{ $notice }}</p>
        <div class="webx-map__actions">
            <button type="button" class="webx-map__button" data-webx-map-load>{{ __('webx-widgets::widgets.map.load') }}</button>
            <button type="button" class="webx-map__button webx-map__button--always" data-webx-map-always>{{ __('webx-widgets::widgets.map.always') }}</button>
        </div>
        @endif
    </div>
    @if ($attribution !== '')<p class="webx-map__attribution">{!! $attribution !!}</p>@endif
</div>
@else
<div {{ $attributes->class(['webx-map', 'webx-map--place']) }}>
    <div class="webx-map__place webx-map__place--static">
        <p class="webx-map__address">{{ $address }}</p>
        @if ($href)<a class="webx-map__open" href="{{ $href }}" target="_blank" rel="noopener">{{ __('webx-widgets::widgets.map.open') }}</a>@endif
    </div>
</div>
@endif
