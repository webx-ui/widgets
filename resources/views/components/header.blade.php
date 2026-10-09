{{--
    The site's header (spec §6.2). The runtime keeps --webx-header-height on :root, folds the
    navigation into the mobile menu (is-collapsed), and sticks, hides and turns solid as the
    page scrolls (is-hidden, is-scrolled).

    The bar is a grid with named areas — brand, nav, actions, trigger — which a theme rearranges
    on .webx-header__bar without overriding this view. A `mobile` slot of the site's own takes the
    place of the menu built here; give its trigger `trigger-class="webx-header__trigger"` and
    `:breakpoint="null"` so that the header decides when it shows.

    Classes: webx-header, --sticky, --hide-on-scroll, --overlay, __skip, __topbar, __bar,
    __brand, __nav, __actions, __trigger; is-collapsed, is-scrolled, is-hidden.
--}}
@if ($skip !== false)
    <a class="webx-header__skip" href="{{ $skip }}">{{ $skipText }}</a>
@endif

@if ($collapse !== 'never')
    {{-- Without JavaScript nothing measures the navigation: it folds at a width, by a container query that cannot read a custom property. --}}
    <style>@container webx-header (width < {{ $foldsAt }}px) { html:not(.webx-js) #{{ $id }} .webx-header__nav, html:not(.webx-js) #{{ $id }} .webx-header__actions { display: none; } html:not(.webx-js) #{{ $id }} .webx-header__trigger { display: inline-flex; } }</style>
@endif

<header {{ $attributes->class([
    'webx-header',
    'webx-header--sticky' => $sticky !== 'none',
    'webx-header--hide-on-scroll' => $sticky === 'hide-on-scroll',
    'webx-header--overlay' => $overlay,
])->merge([
    'id' => $id,
    'data-webx-header' => '',
    'data-collapse' => (string) $collapse,
    'data-sticky' => $sticky,
]) }}>
    @if (isset($topbar) && $topbar->isNotEmpty())
        <div class="webx-header__topbar">{{ $topbar }}</div>
    @endif

    <div class="webx-header__bar">
        @if (isset($brand) && $brand->isNotEmpty())
            <div class="webx-header__brand">{{ $brand }}</div>
        @endif

        @if ($slot->isNotEmpty())
            <nav class="webx-header__nav" aria-label="{{ $navLabel }}">{{ $slot }}</nav>
        @endif

        @if (isset($actions) && $actions->isNotEmpty())
            <div class="webx-header__actions">{{ $actions }}</div>
        @endif

        @if ($collapse !== 'never')
            @if (isset($mobile) && $mobile->isNotEmpty())
                {{ $mobile }}
            @else
                @php($tree = $navigation())
                <x-webx-mobile-menu :id="$menuId" :side="$side" :breakpoint="null" trigger-class="webx-header__trigger">
                    @if (isset($trigger) && $trigger->isNotEmpty())
                        <x-slot:trigger>{{ $trigger }}</x-slot:trigger>
                    @endif

                    @if (isset($brand) && $brand->isNotEmpty())
                        <x-slot:top><div class="webx-mobile-menu__brand">{{ $brand }}</div></x-slot:top>
                    @endif

                    @if ($tree !== null)
                        <x-webx-mobile-menu.nav :items="$tree" :mode="$mode" :label="$navLabel" />
                    @else
                        {{ $slot }}
                    @endif

                    @if ((isset($actions) && $actions->isNotEmpty()) || (isset($mobileBottom) && $mobileBottom->isNotEmpty()))
                        <x-slot:bottom>{{ $actions ?? '' }}{{ $mobileBottom ?? '' }}</x-slot:bottom>
                    @endif
                </x-webx-mobile-menu>
            @endif
        @endif
    </div>
</header>
