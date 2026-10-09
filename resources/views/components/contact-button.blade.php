{{--
    Quick contact (spec §12.4): a round button in a corner of the window, a dropdown (§6.3)
    opening upwards into the chats, the number, the e-mail and the form. The script lifts it
    above the cookie banner while the banner is on the screen (`--webx-contact-lift`).

    Classes: webx-contact-button, --<corner>, __toggle, __list, __item, __item--<channel>.
--}}
<div {{ $attributes->class(['webx-contact-button', 'webx-contact-button--'.$corner])->merge(['data-webx-contact-button' => '']) }}>
    <x-webx-dropdown class="webx-contact-button__dropdown" :placement="$corner === 'bottom-start' ? 'top-start' : 'top-end'" :label="$labelText">
        <x-slot:trigger class="webx-contact-button__toggle"><x-webx-icon name="chat" /></x-slot:trigger>
        <ul class="webx-contact-button__list">
            @foreach ($links as $link)
                <li>
                    <a class="webx-contact-button__item webx-contact-button__item--{{ $link['kind'] }}" href="{{ $link['href'] }}"@if ($link['external']) target="_blank" rel="noopener"@endif>
                        <x-webx-icon :name="$link['icon']" />
                        <span>{{ $link['text'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </x-webx-dropdown>
</div>
