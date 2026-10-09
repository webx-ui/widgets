{{--
    The site's networks (spec §12.5): an icon each, the name for a screen reader.

    Classes: webx-socials, __list, __link, __link--<network>.
--}}
<nav {{ $attributes->class(['webx-socials']) }} aria-label="{{ $labelText }}">
    <ul class="webx-socials__list">
        @foreach ($networks as $network)
            <li>
                <a class="webx-socials__link webx-socials__link--{{ $network->kind }}" href="{{ $network->url }}" target="_blank" rel="noopener" aria-label="{{ $network->label ?? WebxUi\Widgets\Contacts\ContactsSource::brand($network->kind) }}"><x-webx-icon :name="WebxUi\Widgets\Contacts\ContactsSource::icon($network->kind)" /></a>
            </li>
        @endforeach
    </ul>
</nav>
