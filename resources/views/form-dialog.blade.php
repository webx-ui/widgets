{{--
    A form of module-inbox in a dialog (spec §6.4), printed once per slug before </body>.
    Anything with `data-webx-form="{{ $form->slug }}"`, or a link to `#{{ $id }}`, opens it; the
    form inside is the module's own, placed `modal`. After a submission the dialog shows the
    form's thank-you and a button that closes it — it never closes by itself, the visitor must
    have time to read. Without JavaScript the link shows it through `:target`; a page that comes
    back from it with the thank-you or the errors prints it open.

    Override: <layer>/views/vendor/webx-widgets/form-dialog.blade.php — keep
    `data-webx-form-dialog`, `data-webx-dialog-close` and the form.

    Classes: webx-dialog and webx-form-dialog, __header, __title, __close, __body, __done; is-sent.
--}}
<dialog id="{{ $id }}" class="webx-dialog webx-form-dialog" data-webx-form-dialog="{{ $form->slug }}" aria-labelledby="{{ $id }}-title"{!! $open ? ' open' : '' !!}>
    <div class="webx-dialog__header webx-form-dialog__header">
        <h2 class="webx-dialog__title webx-form-dialog__title" id="{{ $id }}-title">{{ $title }}</h2>
        <a class="webx-dialog__close webx-form-dialog__close" href="#" data-webx-dialog-close aria-label="{{ __('webx-widgets::widgets.dialog.close') }}"><span aria-hidden="true">&times;</span></a>
    </div>
    <div class="webx-dialog__body webx-form-dialog__body">
        <x-webx-inbox::form :form="$form" placement="modal" />
        <button type="button" class="webx-form-dialog__done" data-webx-dialog-close hidden>{{ __('webx-widgets::widgets.dialog.close') }}</button>
    </div>
</dialog>
