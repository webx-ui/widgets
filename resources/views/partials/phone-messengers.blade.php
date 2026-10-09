@if ($phone->messengers !== [])
    <span class="webx-phones__messengers">
        @foreach ($phone->messengers as $chat)
            <a class="webx-phones__messenger webx-phones__messenger--{{ $chat->kind }}" href="{{ $chat->url }}" target="_blank" rel="noopener" aria-label="{{ __('webx-widgets::widgets.phones.chat', ['messenger' => WebxUi\Widgets\Contacts\ContactsSource::brand($chat->kind), 'number' => $phone->number]) }}"><x-webx-icon :name="WebxUi\Widgets\Contacts\ContactsSource::icon($chat->kind)" /></a>
        @endforeach
    </span>
@endif
