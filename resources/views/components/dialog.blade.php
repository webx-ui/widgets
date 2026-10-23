{{--
    A modal dialog (spec §6). Opened by any `data-webx-dialog="{{ $id }}"`; the runtime makes it
    modal, traps focus in it, closes it on Esc and on a click outside and gives focus back to
    whatever opened it. Without JavaScript `#{{ $id }}` shows it through `:target`, and the close
    link `#` drops the target.

    A form inside it (<x-webx-form>) that the page came back from without JavaScript — its
    thank-you or its errors — prints the dialog open: over the page without the runtime, opened
    again as a modal with it (spec §17.3).

    `sheet` — on a narrow screen a sheet from the bottom, the whole width, rounded on top, instead
    of a card in the middle; `description` — a line under the title, its accessible description.

    Classes: webx-dialog (+ --sheet), __header, __heading, __title, __description, __close, __body.
--}}
<dialog {{ $attributes->class(['webx-dialog', 'webx-dialog--sheet' => $sheet])->merge(['id' => $id, 'aria-labelledby' => $title !== null ? $id.'-title' : null, 'aria-describedby' => $description !== null ? $id.'-description' : null]) }}@if (str_contains((string) $slot, 'data-webx-returned')) open @endif>
    <div class="webx-dialog__header">
        @if ($title !== null || $description !== null)
            <div class="webx-dialog__heading">
                @if ($title !== null)
                    <h2 class="webx-dialog__title" id="{{ $id }}-title">{{ $title }}</h2>
                @endif
                @if ($description !== null)
                    <p class="webx-dialog__description" id="{{ $id }}-description">{{ $description }}</p>
                @endif
            </div>
        @endif
        <a class="webx-dialog__close" href="#" data-webx-dialog-close aria-label="{{ $closeLabel }}"><span aria-hidden="true">&times;</span></a>
    </div>
    <div class="webx-dialog__body">{{ $slot }}</div>
</dialog>
