{{--
    The mobile menu (spec §6.1): a trigger and a modal <dialog> of three zones — `top` and
    `bottom` stay put, the body between them scrolls on its own. The close button is always
    there, whatever the `top` slot holds. The trigger is a link to #{{ $id }}: without
    JavaScript the panel opens through :target and closes by the link back to the page.

    Classes: webx-mobile-menu, __trigger, __burger, __panel, __panel--<side>, __top, __close,
    __body, __bottom; is-open on the trigger while the panel is open.
--}}
<div {{ $attributes->class(['webx-mobile-menu'])->merge([
    'data-webx-mobile-menu' => $id,
    'data-side' => $side,
    'data-close-on-navigate' => $closeOnNavigate ? 'true' : 'false',
    'data-history' => $history ? 'true' : 'false',
]) }}>
    @if ($breakpoint !== null)
        {{-- A container query cannot read a custom property, so the width is written here. --}}
        <style>@container (min-width: {{ $breakpoint }}px) { #{{ $id }}-trigger { display: none; } }</style>
    @endif

    <a id="{{ $id }}-trigger" @class(array_filter(['webx-mobile-menu__trigger', $triggerClass])) href="#{{ $id }}" data-webx-dialog="{{ $id }}" aria-controls="{{ $id }}" aria-haspopup="dialog"@if (! isset($trigger) || $trigger->isEmpty()) aria-label="{{ $openLabel }}"@endif>
        @if (isset($trigger) && $trigger->isNotEmpty())
            {{ $trigger }}
        @else
            <span class="webx-mobile-menu__burger" aria-hidden="true"></span>
        @endif
    </a>

    <dialog id="{{ $id }}" class="webx-mobile-menu__panel webx-mobile-menu__panel--{{ $side }}" aria-label="{{ $titleLabel }}">
        <div class="webx-mobile-menu__top">
            {{ $top ?? '' }}
            <a class="webx-mobile-menu__close" href="#" data-webx-dialog-close aria-label="{{ $closeLabel }}"><span aria-hidden="true">&times;</span></a>
        </div>
        <div class="webx-mobile-menu__body">{{ $slot }}</div>
        @if (isset($bottom) && $bottom->isNotEmpty())
            <div class="webx-mobile-menu__bottom">{{ $bottom }}</div>
        @endif
    </dialog>
</div>
