{{--
    The list of a table of contents (spec §14), filled in by the server from the finished page.

    The title names the navigation; the toggle — the title and the section being read — replaces
    it where the list folds into a dropdown, and is shown by the script only: without JavaScript
    the list stays open above the text.

    Classes: webx-toc__nav, __nav--beside (with the text in the slot), __title, __toggle,
    __toggle-title, __current, __panel, __list, __list--sub, __item, __link; is-ready, is-open,
    is-current.
--}}
<nav class="webx-toc__nav{{ $beside ? ' webx-toc__nav--beside' : '' }}" id="{{ $id }}" aria-labelledby="{{ $id }}-title">
    <p class="webx-toc__title" id="{{ $id }}-title">{{ $title }}</p>
    <button type="button" class="webx-toc__toggle" aria-expanded="false" aria-controls="{{ $id }}-panel"><span class="webx-toc__toggle-title">{{ $title }}</span><span class="webx-toc__current"></span></button>
    <div class="webx-toc__panel" id="{{ $id }}-panel">
        <ol class="webx-toc__list">
            @foreach ($items as $item)
            <li class="webx-toc__item"><a class="webx-toc__link" href="#{{ $item['id'] }}">{{ $item['text'] }}</a>
                @if ($item['children'] !== [])
                <ol class="webx-toc__list webx-toc__list--sub">
                    @foreach ($item['children'] as $child)
                    <li class="webx-toc__item"><a class="webx-toc__link" href="#{{ $child['id'] }}">{{ $child['text'] }}</a></li>
                    @endforeach
                </ol>
                @endif
            </li>
            @endforeach
        </ol>
    </div>
</nav>
