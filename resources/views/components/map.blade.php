{{--
    A map (spec §11). The place — the address and "Open in maps" — in a frame of the map's height:
    the map without JavaScript. Before consent to media the notice of §9.4 stands in it, its
    buttons the script's; with consent the script puts the map over it. The attribution of the
    tiles is printed under every map. An address with no coordinates is the place alone. A map
    the site lets load without asking has no data-webx-consent: the banner does not offer media for it.
    Google's "Embed a map" (embed, §11.2) is the same frame: the script puts Google's <iframe> in it —
    or the server does, for a map that does not wait for consent (`$printed`), and the place hides.

    The place is a card over a drawing of streets made of the theme's colours — a map before the
    map, with nothing asked of anyone: no picture, no request.

    Classes: webx-map, --place (no coordinates), --embed (Google's frame), __place, __place--static, __card, __pin,
    __address, __open, __notice, __actions, __button, __button--always, __attribution, __canvas,
    __frame (Google's <iframe>), __marker, __hint;
    is-blocked (before consent), is-ready (the script took it), is-loaded (the map is in),
    is-active (clicked or focused: the wheel zooms).
--}}
@if ($hasMap())
<div {{ $attributes->class(['webx-map', 'webx-map--embed' => $embed !== null, 'is-blocked' => $blocked, 'is-loaded' => $printed])->merge(['style' => $frameStyle()]) }} role="region" aria-label="{{ __('webx-widgets::widgets.map.label', ['place' => $name]) }}" data-webx-map="{{ json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"@if ($waits) data-webx-consent="media"@endif>
    @if ($printed)
    <div class="webx-map__canvas"><iframe class="webx-map__frame" src="{{ $embed }}" title="{{ $name }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>
    @endif
    <div class="webx-map__place"{!! $printed ? ' hidden' : '' !!}>
        <div class="webx-map__card">
            <svg class="webx-map__pin" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7m0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5"/></svg>
            @if ($address)<p class="webx-map__address">{{ $address }}</p>@endif
            @if ($blocked)
            <p class="webx-map__notice">{{ $notice }}</p>
            <div class="webx-map__actions">
                <button type="button" class="webx-map__button" data-webx-map-load>{{ __('webx-widgets::widgets.map.load') }}</button>
                <button type="button" class="webx-map__button webx-map__button--always" data-webx-map-always>{{ __('webx-widgets::widgets.map.always') }}</button>
            </div>
            @endif
            @if ($href)<a class="webx-map__open" href="{{ $href }}" target="_blank" rel="noopener">{{ __($embed !== null ? 'webx-widgets::widgets.map.open_google' : 'webx-widgets::widgets.map.open') }}</a>@endif
        </div>
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
