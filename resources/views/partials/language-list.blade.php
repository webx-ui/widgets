<ul class="webx-language-switcher__list">
    @foreach ($links as $link)
        <li class="webx-language-switcher__item">
            <a @class(['webx-language-switcher__link', 'is-current' => $link->current, 'is-fallback' => $link->fallback]) href="{{ $link->url }}" hreflang="{{ $link->tag() }}" lang="{{ $link->tag() }}"{!! $link->current ? ' aria-current="page"' : '' !!}{!! $link->fallback ? ' title="'.e($fallbackTitle($link)).'"' : '' !!}>
                @if ($codes)
                    <span class="webx-language-switcher__code">{{ strtoupper($link->tag()) }}</span>
                @endif
                <span class="webx-language-switcher__name">{{ $link->name }}</span>
            </a>
        </li>
    @endforeach
</ul>
