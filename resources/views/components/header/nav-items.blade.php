@foreach ($items as $item)
    <li @class(['webx-header-nav__subitem', 'is-current' => $item->active])>
        @if ($item->href() !== null)
            <a @class(['webx-header-nav__sublink', 'is-current' => $item->current]) {!! $item->attributes() !!}>{{ $item->label }}</a>
        @else
            <span class="webx-header-nav__sublink">{{ $item->label }}</span>
        @endif

        @if ($item->hasChildren())
            <ul class="webx-header-nav__sublist">
                @include('webx-widgets::components.header.nav-items', ['items' => $item->children])
            </ul>
        @endif
    </li>
@endforeach
