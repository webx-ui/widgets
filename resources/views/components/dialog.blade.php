{{--
    A modal dialog (spec §6). Opened by any `data-webx-dialog="{{ $id }}"`; the runtime makes it
    modal, traps focus in it, closes it on Esc and on a click outside and gives focus back to
    whatever opened it. Without JavaScript `#{{ $id }}` shows it through `:target`, and the close
    link `#` drops the target.

    Classes: webx-dialog, __header, __title, __close, __body.
--}}
<dialog {{ $attributes->class(['webx-dialog'])->merge(['id' => $id, 'aria-labelledby' => $title !== null ? $id.'-title' : null]) }}>
    <div class="webx-dialog__header">
        @if ($title !== null)
            <h2 class="webx-dialog__title" id="{{ $id }}-title">{{ $title }}</h2>
        @endif
        <a class="webx-dialog__close" href="#" data-webx-dialog-close aria-label="{{ $closeLabel }}"><span aria-hidden="true">&times;</span></a>
    </div>
    <div class="webx-dialog__body">{{ $slot }}</div>
</dialog>
