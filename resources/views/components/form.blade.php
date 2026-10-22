{{--
    The shell of a form (spec §17.3): the token and the method, the fields in __body, then the
    places of the answer — the message (a live region, empty until there is something to say, so
    nothing moves before), the refusal of the whole form (an alert), the upload's progress, and the
    button that closes a dialog the form stands in.

    Without JavaScript a page that comes back from this form prints in them what it was answered:
    the message or the HTML of FormResponse, the errors no field shows. With it, form.js writes into
    the same places. `data-webx-returned` says this form is the one that was sent — a dialog around
    it opens again.

    `again`: the thank-you stands in place of the fields (__body hidden), and __again under it brings
    them back — a link to this page without JavaScript, `fresh()` and the focus on the first field
    with it. Hidden until the form is sent.

    Classes: webx-form, __body, __message, __answer, __heading, __text, __answer-value (a visitor's
    answer a controller put into the thank-you — Inbox's `{{field.<name>}}`), __error, __refusals,
    __refusal, __progress, __done, __again, __submit;
    states is-sending, is-sent.
--}}
@php
    $returned = $sent ? ' data-webx-returned' : '';
    $refusals = $unplaced((string) $slot);
    $said = ($answer['heading'] ?? null) !== null || ($answer['heading_html'] ?? null) !== null || ($answer['message'] ?? null) !== null;
    $aside = $again && $said && ($answer['html'] ?? null) === null;
    $enctype = $sending && ! $attributes->has('enctype') && preg_match('/<input[^>]+type="file"/', (string) $slot) === 1 ? 'multipart/form-data' : null;
@endphp
<form {{ $attributes->class(['webx-form', 'is-sent' => $answer !== null])->merge([
    'id' => $formId,
    'action' => $action,
    'method' => $verb,
    'enctype' => $enctype,
    'data-webx-send' => $sending ? '' : null,
    'data-webx-csrf' => $sending && $csrf ? route('webx-widgets.csrf') : null,
]) }}{!! $returned !!}>
    @if ($sending)
        @if ($csrf)
            @csrf
        @endif
        @if ($spoof !== null)
            @method($spoof)
        @endif
        <input type="hidden" name="{{ \WebxUi\Widgets\Forms\FormResponse::FORM }}" value="{{ $formId }}">
    @endif
    <div class="webx-form__body"{{ $aside ? ' hidden' : '' }}>
        @if (($answer['html'] ?? null) !== null)
            {!! $answer['html'] !!}
        @else
            {{ $slot }}
        @endif
    </div>
    @if ($sending)
        <progress class="webx-form__progress" max="100" value="0" hidden></progress>
        <div class="webx-form__message" role="status" aria-live="polite">@if ($said)<div class="webx-form__answer">
                @if ($answer['heading_html'] !== null)
                    <p class="webx-form__heading">{!! $answer['heading_html'] !!}</p>
                @elseif ($answer['heading'] !== null)
                    <p class="webx-form__heading">{{ $answer['heading'] }}</p>
                @endif
                @if ($answer['message'] !== null)
                    <div class="webx-form__text">{!! $answer['message'] !!}</div>
                @endif
            </div>@endif</div>
        <div class="webx-form__error" role="alert" data-webx-failed="{{ $words['failed'] }}" data-webx-expired="{{ $words['expired'] }}">@if ($refusals !== [])<div class="webx-form__refusals">@foreach ($refusals as $refusal)<p class="webx-form__refusal">{{ $refusal }}</p>@endforeach</div>@endif</div>
        <button type="button" class="webx-form__done" data-webx-dialog-close hidden>{{ $words['close'] }}</button>
        @if ($again)
            <a class="webx-form__again" href="{{ $againUrl }}" data-webx-again{{ $aside ? '' : ' hidden' }}>{{ $words['again'] }}</a>
        @endif
    @endif
</form>
