{{--
    The site's captcha in a form (WebxUi\Widgets\Captcha). Classes: webx-captcha, __widget (an
    invisible one, drawn by captcha.js on submit). The error is the fields' own `webx-field__error`.
--}}
<div
    {{ $attributes->class(['webx-captcha']) }}
    data-webx-field="captcha"
    data-webx-captcha="{{ $captcha['provider'] }}"
    data-webx-captcha-type="{{ $captcha['type'] }}"
    data-webx-captcha-key="{{ $captcha['key'] }}"
    data-webx-captcha-action="{{ $captcha['action'] }}"
    data-webx-captcha-message="{{ __('webx-widgets::widgets.captcha.missing') }}"
    data-webx-captcha-unavailable="{{ __('webx-widgets::widgets.captcha.unavailable') }}"
>
    @if ($captcha['type'] === 'v3')
        <input type="hidden" name="{{ $captcha['field'] }}" value="">
    @else
        <div class="{{ $captcha['widget'] }}" data-sitekey="{{ $captcha['key'] }}"></div>
    @endif

    <p class="webx-field__error" id="{{ $errorId }}" @if ($refusal === []) hidden @endif>{{ implode(' ', $refusal) }}</p>
</div>
@if ($script !== null)
    <script src="{{ $script }}" async defer></script>
@endif
