{{--
    The cookie banner and its dialog (spec §9), printed before </body> of every page with a
    theme. Both start hidden: the script shows the banner when the cookie holds no answer to the
    current policy, and without JavaScript nothing third-party starts, so there is nothing to ask.

    "Reject all" and "Accept all" are the same element with the same class — same weight, same
    size: consent won with unequal buttons is no consent. A category with nothing on the site is
    printed hidden; the script shows it when something on the page asks for it.

    Classes: webx-consent, __inner, __text, __title, __body, __policy, __actions, __button, __customize,
    webx-consent-dialog, webx-consent-dialog__intro, __gpc, __list, __item, __label, __switch, __name,
    __description, __always, __actions.
--}}
@php
    $id = WebxUi\Widgets\Consent::DIALOG;
    $policy = $consent->policy();
    $gpc = $consent->gpc();
@endphp
<div class="webx-consent-root" data-webx-consent-root data-version="{{ $consent->version() }}" data-lifetime="{{ $consent->lifetime() }}"{!! $enabled ? '' : ' data-off' !!}{!! $gpc ? ' data-gpc' : '' !!}>
@if ($enabled)
    <section class="webx-consent" aria-labelledby="{{ $id }}-banner-title" data-webx-consent-banner hidden>
        <div class="webx-consent__inner">
            <div class="webx-consent__text">
                <h2 class="webx-consent__title" id="{{ $id }}-banner-title">{{ $consent->text('title') }}</h2>
                <p class="webx-consent__body">
                    {{ $consent->text('text') }}
                    @if ($policy !== null)
                        <a class="webx-consent__policy" href="{{ $policy }}">{{ __('webx-widgets::widgets.consent.policy') }}</a>
                    @endif
                </p>
            </div>
            <div class="webx-consent__actions">
                <button type="button" class="webx-consent__button" data-webx-consent-reject>{{ __('webx-widgets::widgets.consent.reject') }}</button>
                <button type="button" class="webx-consent__button" data-webx-consent-accept>{{ __('webx-widgets::widgets.consent.accept') }}</button>
                <button type="button" class="webx-consent__customize" data-webx-dialog="{{ $id }}">{{ __('webx-widgets::widgets.consent.customize') }}</button>
            </div>
        </div>
    </section>

    <dialog class="webx-dialog webx-consent-dialog" id="{{ $id }}" aria-labelledby="{{ $id }}-title">
        <div class="webx-dialog__header">
            <h2 class="webx-dialog__title" id="{{ $id }}-title">{{ __('webx-widgets::widgets.consent.dialog') }}</h2>
            <a class="webx-dialog__close" href="#" data-webx-dialog-close aria-label="{{ __('webx-widgets::widgets.dialog.close') }}"><span aria-hidden="true">&times;</span></a>
        </div>
        <div class="webx-dialog__body">
            <p class="webx-consent-dialog__intro">
                {{ $consent->text('text') }}
                @if ($policy !== null)
                    <a class="webx-consent__policy" href="{{ $policy }}">{{ __('webx-widgets::widgets.consent.policy') }}</a>
                @endif
            </p>
            <p class="webx-consent-dialog__gpc" data-webx-consent-gpc{!! $gpc ? '' : ' hidden' !!}>{{ __('webx-widgets::widgets.consent.gpc') }}</p>
            <ul class="webx-consent-dialog__list">
                @foreach (WebxUi\Widgets\Consent::CATEGORIES as $category)
                    @php($optional = $category !== 'necessary')
                    <li class="webx-consent-dialog__item" data-webx-consent-row="{{ $category }}"{!! $optional && ! in_array($category, $shown, true) ? ' hidden' : '' !!}>
                        <label class="webx-consent-dialog__label">
                            <input class="webx-consent-dialog__switch" type="checkbox" role="switch" name="{{ $category }}" data-webx-consent-category="{{ $category }}"{!! ! $optional || in_array($category, $granted, true) ? ' checked' : '' !!}{!! $optional ? '' : ' disabled' !!} aria-describedby="{{ $id }}-{{ $category }}">
                            <span class="webx-consent-dialog__name">{{ __("webx-widgets::widgets.consent.categories.{$category}") }}</span>
                            @if (! $optional)
                                <span class="webx-consent-dialog__always">{{ __('webx-widgets::widgets.consent.always') }}</span>
                            @endif
                        </label>
                        <p class="webx-consent-dialog__description" id="{{ $id }}-{{ $category }}">{{ $consent->text($category) }}</p>
                    </li>
                @endforeach
            </ul>
            <div class="webx-consent-dialog__actions">
                <button type="button" class="webx-consent__button" data-webx-consent-reject>{{ __('webx-widgets::widgets.consent.reject') }}</button>
                <button type="button" class="webx-consent__button" data-webx-consent-save>{{ __('webx-widgets::widgets.consent.save') }}</button>
                <button type="button" class="webx-consent__button" data-webx-consent-accept>{{ __('webx-widgets::widgets.consent.accept') }}</button>
            </div>
        </div>
    </dialog>
@endif
</div>
