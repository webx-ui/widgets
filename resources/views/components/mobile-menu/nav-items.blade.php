@foreach ($items as $item)
    <li @class(['webx-mobile-nav__item', 'is-current' => $item->current && ! $item->hasChildren()])>
        @if ($item->hasChildren())
            <details class="webx-mobile-nav__group"{!! $item->active ? ' open' : '' !!}>
                <summary class="webx-mobile-nav__toggle">{{ $item->label }}</summary>
                <div @class(['webx-mobile-nav__level', 'webx-mobile-nav__level--drill' => $mode === 'drill'])>
                    @if ($mode === 'drill')
                        <button type="button" class="webx-mobile-nav__back" data-webx-mobile-nav-back>{{ $backLabel }}</button>
                        @if ($item->href() !== null)
                            <a @class(['webx-mobile-nav__title', 'is-current' => $item->current]) {!! $item->attributes() !!}>{{ $item->label }}</a>
                        @else
                            <p class="webx-mobile-nav__title">{{ $item->label }}</p>
                        @endif
                    @endif
                    <ul class="webx-mobile-nav__list">
                        @if ($mode === 'accordion' && $item->href() !== null)
                            <li @class(['webx-mobile-nav__item', 'is-current' => $item->current])>
                                <a class="webx-mobile-nav__link" {!! $item->attributes() !!}>{{ $item->label }}</a>
                            </li>
                        @endif
                        @include('webx-widgets::components.mobile-menu.nav-items', ['items' => $item->children])
                    </ul>
                </div>
            </details>
        @elseif ($item->href() !== null)
            <a class="webx-mobile-nav__link" {!! $item->attributes() !!}>{{ $item->label }}</a>
        @else
            <span class="webx-mobile-nav__heading">{{ $item->label }}</span>
        @endif
    </li>
@endforeach
