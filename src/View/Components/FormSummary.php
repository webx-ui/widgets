<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-form.summary title hint :fields>` — the answers given so far, each with «Edit» that
 * brings its field back (FORM_STEPS §7). It asks nothing and sends nothing.
 *
 * The list is the browser's (`dist/summary.js`, claimed here): built from what the form holds as
 * the visitor reaches it — the labels of the fields, the words of the choices, not their values.
 * Without JavaScript it stays hidden: every answer stands on the page above it anyway.
 *
 * `:fields` — the names of the fields to show, as the page names them (`fields.purpose`); none —
 * every field above it that is shown and answered, but a consent, a file and a hidden one.
 * `level` — the title's heading level, 4 by default (a step's own title is 3). `edit-label`,
 * `edit-field-label` — the words of «Edit» and of its name for a reader of the screen («Edit:
 * :field»).
 */
final class FormSummary extends Component
{
    /**
     * @param  list<string>|null  $fields
     */
    public function __construct(
        public ?string $title = null,
        public ?string $hint = null,
        public ?array $fields = null,
        public int $level = 4,
        public ?string $editLabel = null,
        public ?string $editFieldLabel = null,
        public ?string $id = null,
    ) {
        $this->level = max(2, min(6, $level));
    }

    public function render(): View
    {
        Widgets::need('summary');

        $title = $this->title !== null && trim($this->title) !== '' ? trim($this->title) : null;
        $form = app('view')->getConsumableComponentData(Form::ID);
        $fields = array_values(array_filter($this->fields ?? [], static fn (mixed $name): bool => is_string($name) && $name !== ''));

        return view('webx-widgets::components.form.summary', [
            'heading' => $title,
            'summaryId' => $this->id ?? (is_string($form) ? $form.'-' : '').'summary-'.Widgets::sequence('form-summary'),
            'names' => $fields === [] ? null : json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'words' => [
                'edit' => $this->editLabel ?? __('webx-widgets::widgets.form.steps.edit'),
                'field' => $this->editFieldLabel ?? __('webx-widgets::widgets.form.steps.edit-field'),
            ],
        ]);
    }
}
