{{--
    An announcement bar above the header (spec §14): a banner of the place `notice` of
    module-banners — its title, text and buttons — or the slot. `data-webx-notice-bar` is the
    version of what it says: the script remembers it when the bar is closed, and the head hides a
    remembered one before the body is painted (`Widgets::finish()`). The close button comes with
    the script: without it, there is nothing to remember a click with.

    Classes: webx-notice-bar, __inner, __content, __title, __text, __link, __link--<variant>, __close, __icon.
--}}
<section {{ $attributes->class(['webx-notice-bar']) }} aria-label="{{ $labelText }}" data-webx-notice-bar="{{ $revision }}">
    <div class="webx-notice-bar__inner">
        <div class="webx-notice-bar__content">
            @if ($card !== null)
                @if ($card['title'] !== '')
                    <strong class="webx-notice-bar__title">{{ $card['title'] }}</strong>
                @endif
                @if ($card['text'] !== '')
                    <span class="webx-notice-bar__text">{!! nl2br(e($card['text']), false) !!}</span>
                @endif
                @foreach ($card['buttons'] as $button)
                    @php($link = array_filter([
                        'class' => 'webx-notice-bar__link'.(is_string($button['variant'] ?? null) ? ' webx-notice-bar__link--'.$button['variant'] : ''),
                        'href' => $button['url'],
                        'target' => ($button['new_tab'] ?? false) ? '_blank' : null,
                        'rel' => $button['rel'] ?? null,
                    ], static fn ($value): bool => $value !== null))
                    <a {{ new \Illuminate\View\ComponentAttributeBag($link) }}>{{ $button['label'] }}</a>
                @endforeach
            @else
                {{ $slot }}
            @endif
        </div>
        @if ($closable)
            <button type="button" class="webx-notice-bar__close" aria-label="{{ __('webx-widgets::widgets.notice_bar.close') }}" hidden><x-webx-icon name="close" class="webx-notice-bar__icon" /></button>
        @endif
    </div>
</section>
