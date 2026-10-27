@foreach ($items as $item)
    {{-- `split` drops a child that leads where its parent does: the parent is that link already. --}}
    @php($branch = $split && $item->href() !== null ? $item->childrenElsewhere() : $item->children)
    <li @class(['webx-mobile-nav__item', 'is-current' => $item->current && $branch === []])>
        @if ($branch !== [] && $split && $item->href() !== null)
            {{-- `split`: the name is the link, the arrow beside it opens the branch, and the branch does not repeat the link. --}}
            <div class="webx-mobile-nav__row">
                <a @class(['webx-mobile-nav__link', 'webx-mobile-nav__link--split', 'is-current' => $item->current]) {!! $item->attributes() !!}>{{ $item->label }}</a>
                <details class="webx-mobile-nav__group webx-mobile-nav__group--split"{!! $item->active ? ' open' : '' !!}>
                    <summary class="webx-mobile-nav__toggle webx-mobile-nav__toggle--split" aria-label="{{ $submenuLabel($item) }}"></summary>
                    <div class="webx-mobile-nav__level">
                        <ul class="webx-mobile-nav__list">
                            @include('webx-widgets::components.mobile-menu.nav-items', ['items' => $branch])
                        </ul>
                    </div>
                </details>
            </div>
        @elseif ($branch !== [])
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
