{{--
    The header's navigation (spec §6.2): the header puts it in its <nav>. An item with children
    is its link plus a toggle; the list opens on hover with a delay and a corridor, on the
    toggle, on the first tap of a touch screen and on Enter, Space or ↓ — and without
    JavaScript on hover and on focus inside. Deeper levels are lists inside the dropdown.

    Classes: webx-header-nav, __item, __item--mega, __link, __toggle, __dropdown, __mega,
    __sublist, __subitem, __sublink; is-current on the visitor's branch, is-open on an open item
    and its panel, is-flipped on a dropdown held against the window's right edge.
--}}
<ul {{ $attributes->class(['webx-header-nav'])->merge(['data-webx-header-nav' => '']) }}>
    @foreach ($tree as $item)
        @php($view = $megaView($item))
        @php($panel = $prefix.'-'.$loop->index)
        <li @class(['webx-header-nav__item', 'webx-header-nav__item--mega' => $view !== null, 'is-current' => $item->active])>
            @if ($item->href() !== null)
                <a @class(['webx-header-nav__link', 'webx-header-nav__link--'.$item->variant => $item->variant !== 'link', 'is-current' => $item->active]) {!! $item->attributes() !!}>{{ $item->label }}</a>
            @elseif ($item->hasChildren() || $view !== null)
                <button type="button" @class(['webx-header-nav__link', 'is-current' => $item->active]) aria-expanded="false" aria-controls="{{ $panel }}" data-webx-header-nav-toggle>{{ $item->label }}</button>
            @else
                <span class="webx-header-nav__link">{{ $item->label }}</span>
            @endif

            @if ($item->hasChildren() || $view !== null)
                @if ($item->href() !== null)
                    <button type="button" class="webx-header-nav__toggle" aria-expanded="false" aria-controls="{{ $panel }}" aria-label="{{ $submenuLabel($item) }}" data-webx-header-nav-toggle></button>
                @endif

                @if ($view !== null)
                    <div class="webx-header-nav__mega" id="{{ $panel }}">@include($view, ['item' => $item])</div>
                @else
                    <ul class="webx-header-nav__dropdown" id="{{ $panel }}">
                        @include('webx-widgets::components.header.nav-items', ['items' => $item->children])
                    </ul>
                @endif
            @endif
        </li>
    @endforeach
</ul>
