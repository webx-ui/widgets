{{--
    A panel tied to its trigger (spec §6.3). A <details>: without JavaScript a click on the
    trigger opens the panel under it. The runtime makes the panel a popover placed from the
    trigger and turned over at the window's edge, opens it on hover when asked, closes it on
    Esc, on a click elsewhere and on Tab out, and keeps one open on the page.

    Classes: webx-dropdown, --<placement>, __trigger, __panel; is-open, is-flipped.
--}}
<details {{ $attributes->class(['webx-dropdown', 'webx-dropdown--'.$placement])->merge(['data-webx-dropdown' => $openOn]) }}{!! $open ? ' open' : '' !!}>
    <summary {{ $trigger->attributes->class(['webx-dropdown__trigger'])->merge(['aria-label' => $label]) }}>{{ $trigger }}</summary>
    <div class="webx-dropdown__panel">{{ $slot }}</div>
</details>
