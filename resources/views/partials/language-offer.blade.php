@php($offerId = 'webx-language-offer-'.\WebxUi\Widgets\Facades\Widgets::sequence('language-offer-id'))
<div class="webx-language-offer" role="dialog" aria-modal="false" aria-labelledby="{{ $offerId }}" lang="{{ $offer->tag() }}" data-webx-language-offer="{{ $offer->cookie }}" data-webx-language-offer-lifetime="{{ $offer->lifetime }}" hidden>
    <p class="webx-language-offer__question" id="{{ $offerId }}">{{ $offer->words['question'] }}</p>
    <div class="webx-language-offer__actions">
        <a class="webx-language-offer__accept" href="{{ $offer->url }}" hreflang="{{ $offer->tag() }}">{{ $offer->words['accept'] }}</a>
        <button type="button" class="webx-language-offer__stay">{{ $offer->words['stay'] }}</button>
    </div>
    <button type="button" class="webx-language-offer__close" aria-label="{{ $offer->words['close'] }}"><x-webx-icon name="close" class="webx-language-offer__icon" /></button>
</div>
