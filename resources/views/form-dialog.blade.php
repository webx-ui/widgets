{{--
    A form of module-inbox in a dialog (spec §6.4), printed once per slug before </body>.
    Anything with `data-webx-form="{{ $form->slug }}"`, or a link to `#{{ $id }}`, opens it; the
    form inside is the module's own, placed `modal` — an <x-webx-form>, so it behaves in the dialog
    the way any form does (spec §17.3): the thank-you in place of the fields and a button that
    closes the dialog — it never closes by itself, the visitor must have time to read. Without
    JavaScript the link shows it through `:target`; a page that comes back from it with the
    thank-you or the errors prints it open.

    Override: <layer>/views/vendor/webx-widgets/form-dialog.blade.php — keep
    `data-webx-form-dialog`, `data-webx-dialog-close` and `{{ $body }}`.

    `$description` — the form's line under its title (inbox §5), HTML; null without one. `$sheet` —
    a sheet from the bottom on a narrow screen (`data-webx-sheet` on an opener). `$page` — the form's
    `dialog.scroll: page` (FORM_STEPS §16.1): the dialog is the whole window and scrolls with its
    backdrop, its box (__window) inside; the page under it stays.

    Classes: webx-dialog (+ --sheet, --page, __window) and webx-form-dialog, __header, __heading, __title,
    __description, __close, __body, __window and __header--flow (scrolled as a whole); __done and is-sent only around an inbox view published before
    W6.4.
--}}
<dialog id="{{ $id }}" @class(['webx-dialog', 'webx-dialog--sheet' => $sheet ?? false, 'webx-dialog--page' => $page ?? false, 'webx-form-dialog']) data-webx-form-dialog="{{ $form->slug }}" aria-labelledby="{{ $id }}-title"@if ($description ?? null) aria-describedby="{{ $id }}-description"@endif{!! $open ? ' open' : '' !!}>
@if ($page ?? false)
<div class="webx-dialog__window webx-form-dialog__window">
@endif
    <div @class(['webx-dialog__header', 'webx-form-dialog__header', 'webx-form-dialog__header--flow' => $page ?? false])>
        <div class="webx-dialog__heading">
            <h2 class="webx-dialog__title webx-form-dialog__title" id="{{ $id }}-title">{{ $title }}</h2>
            @if ($description ?? null)
                <p class="webx-dialog__description webx-form-dialog__description" id="{{ $id }}-description">{{ $description }}</p>
            @endif
        </div>
        <a class="webx-dialog__close webx-form-dialog__close" href="#" data-webx-dialog-close aria-label="{{ __('webx-widgets::widgets.dialog.close') }}"><span aria-hidden="true">&times;</span></a>
    </div>
    <div class="webx-dialog__body webx-form-dialog__body">
        {{ $body }}
        @if ($legacy)
            <button type="button" class="webx-form-dialog__done" data-webx-dialog-close hidden>{{ __('webx-widgets::widgets.dialog.close') }}</button>
        @endif
    </div>
@if ($page ?? false)
</div>
@endif
</dialog>
