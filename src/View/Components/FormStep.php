<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * `<x-webx-form.step name title hint>` — one step of `<x-webx-form steps>` (FORM_STEPS §3).
 *
 * A `<fieldset>` with its title in the `<legend>`: without JavaScript every step stands on the page
 * one under another and the form is sent at once. With it (`dist/steps.js`) one step is shown at a
 * time — the rest `hidden`, their fields still in the form and sent together at the end — with
 * «Back» and «Next» under the fields and «Step 2 of 3» over the title.
 *
 * `label` — the short line of the indicator when the title is long. `:when` / `:unless` — the step
 * only for some answers before it, as a field's (§4): form.js keeps it out of the way and disables
 * its fields, the indicator counts it out; `condition` — the line a page without the script shows.
 * `level` — the title's heading level, 3 by default (2 is the dialog's own title). `next-label`,
 * `back-label` — the words of the buttons.
 */
final class FormStep extends Component
{
    /**
     * The name the fields inside find out by that the step has a condition: Blade keeps a
     * component's public data for its slot. A required field of such a step is not `required` to
     * the browser — without JavaScript it would stop a form whose answers leave the step out.
     */
    public const string CONDITIONAL = 'webxStepConditional';

    public bool $webxStepConditional = false;

    /**
     * @param  array<string, list<string>>|null  $when
     * @param  array<string, list<string>>|null  $unless
     */
    public function __construct(
        public string $name,
        public ?string $title = null,
        public ?string $hint = null,
        public ?string $label = null,
        public ?array $when = null,
        public ?array $unless = null,
        public ?string $condition = null,
        public int $level = 3,
        public ?string $nextLabel = null,
        public ?string $backLabel = null,
        public ?string $id = null,
    ) {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $name) !== 1) {
            throw new InvalidArgumentException("<x-webx-form.step name=\"{$name}\">: letters, digits, `-`, `_` and `.` only.");
        }

        $this->level = max(2, min(6, $level));
        $this->webxStepConditional = $this->conditional();
    }

    /** Shown only for some answers of the fields before it. */
    public function conditional(): bool
    {
        return ($this->when !== null && $this->when !== []) || ($this->unless !== null && $this->unless !== []);
    }

    /** `data-webx-when` / `data-webx-unless` of the step, as JSON — the same as a field's. */
    public function conditionAttributes(): string
    {
        $printed = '';

        foreach (['when' => $this->when, 'unless' => $this->unless] as $key => $condition) {
            if ($condition !== null && $condition !== []) {
                $printed .= ' data-webx-'.$key.'="'.e(json_encode($condition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)).'"';
            }
        }

        return $printed;
    }

    public function render(): View
    {
        $title = $this->title !== null && trim($this->title) !== '' ? $this->title : null;
        $label = $this->label !== null && trim($this->label) !== '' ? $this->label : $title;
        // Under the form's id: two forms of one page may well both have a step "contacts".
        $form = app('view')->getConsumableComponentData(Form::ID);

        return view('webx-widgets::components.form.step', [
            'heading' => $title,
            'short' => $label,
            'stepId' => $this->id ?? (is_string($form) ? $form.'-' : '').'step-'.$this->name,
            'words' => [
                'next' => $this->nextLabel ?? __('webx-widgets::widgets.form.steps.next'),
                'back' => $this->backLabel ?? __('webx-widgets::widgets.form.steps.back'),
            ],
        ]);
    }
}
