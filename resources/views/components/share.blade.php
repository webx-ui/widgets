{{--
    Share (spec §14): each network's own address for sharing, a plain link; "Copy link" and the
    system's share sheet on a phone are the script's — without it the copy button is hidden.

    Classes: webx-share, __label, __list, __link, __link--<network>, __native, __status;
    is-native (the share sheet stands in for the links).
--}}
<div {{ $attributes->class(['webx-share']) }} role="group" aria-label="{{ $labelText }}" data-webx-share="{{ json_encode(['url' => $address, 'title' => (string) $title, 'copied' => __('webx-widgets::widgets.share.copied'), 'failed' => __('webx-widgets::widgets.share.failed')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">
    <span class="webx-share__label" aria-hidden="true">{{ $labelText }}</span>
    <ul class="webx-share__list">
        @foreach ($links as $link)
            <li>
                @if ($link['network'] === 'copy')
                    <button type="button" class="webx-share__link webx-share__link--copy" data-webx-share-copy aria-label="{{ $link['label'] }}"><x-webx-icon :name="$link['icon']" /></button>
                @elseif ($link['network'] === 'email')
                    <a class="webx-share__link webx-share__link--email" href="{{ $link['href'] }}" aria-label="{{ $link['label'] }}"><x-webx-icon :name="$link['icon']" /></a>
                @else
                    <a class="webx-share__link webx-share__link--{{ $link['network'] }}" href="{{ $link['href'] }}" target="_blank" rel="noopener" aria-label="{{ $link['label'] }}"><x-webx-icon :name="$link['icon']" /></a>
                @endif
            </li>
        @endforeach
    </ul>
    <button type="button" class="webx-share__native" data-webx-share-native hidden><x-webx-icon name="share" />{{ $labelText }}</button>
    <span class="webx-share__status" role="status"></span>
</div>
