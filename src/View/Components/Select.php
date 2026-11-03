<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-select name="point" :options="$points" searchable :source="$url" />` — a list with a
 * search (spec §17, decision 36 of the delivery spec).
 *
 * Without JavaScript it is what HTML has: a `<select>` of the options given. With it the select
 * stays as the holder of the value and a combobox stands over it: the visitor types, the options
 * already loaded are filtered at once and, when `source` is given, the server is asked for the
 * rest (`?q=…` — or `param` — and `page`), a stale request cancelled. The answer is JSON:
 * `{"options": [{"value": "12", "label": "Branch 12", "hint": "1 Example street"}], "more": true}`;
 * `more` adds «Show more», which asks the next page. Choosing an option sets the select and fires
 * its `change` — the owner listens to the select as to any field.
 *
 * `inline`: the list opens in the flow of the page, not over it — inside a dialog, whose edge would
 * cut a list that hangs over the content.
 *
 * `strict` (on by default): only an option can be the value — a carrier's street, a branch.
 * `:strict="false"`: the field is text, an option only fills it — a street typed by hand with the
 * suggestions it may take; without JavaScript it is a plain text field.
 *
 * Not a type of `<x-webx-field>`: the delivery step and other owners print it inside their own
 * frame. The label is shown when given; otherwise give `aria-label` through `label` anyway.
 */
final class Select extends Component
{
    public string $key;

    /** @var list<array{value: string, label: string, hint: string|null, disabled: bool}> */
    public array $choices;

    public string $current;

    /** @param  array<array-key, mixed>  $options  `value => label`, or a list of options ({@see self::normalise()}) */
    public function __construct(
        public string $name,
        array $options = [],
        mixed $value = null,
        public bool $searchable = false,
        public ?string $source = null,
        public string $param = 'q',
        public bool $strict = true,
        public ?string $id = null,
        public ?string $label = null,
        public bool $showLabel = false,
        public ?string $placeholder = null,
        public bool $required = false,
        public bool $disabled = false,
        public int $min = 2,
        public int $delay = 250,
        public bool $inline = false,
        public ?string $nothingLabel = null,
        public ?string $moreLabel = null,
        public ?string $countLabel = null,
        public ?string $failedLabel = null,
        public ?string $searchingLabel = null,
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.\-\[\]]*$/', $name) !== 1) {
            throw new InvalidArgumentException("<x-webx-select name=\"{$name}\">: the name of a field.");
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $param) !== 1) {
            throw new InvalidArgumentException("<x-webx-select param=\"{$param}\">: one plain name.");
        }

        if ($source !== null && trim($source) === '') {
            throw new InvalidArgumentException('<x-webx-select source="">: the address of the server search, or nothing.');
        }

        if ($min < 0 || $min > 10) {
            throw new InvalidArgumentException("<x-webx-select min=\"{$min}\">: 0 to 10 characters before asking the server.");
        }

        if ($delay < 0 || $delay > 2000) {
            throw new InvalidArgumentException("<x-webx-select delay=\"{$delay}\">: 0 to 2000 milliseconds.");
        }

        $this->choices = self::normalise($options);
        $this->key = $id ?? 'select-'.Widgets::sequence('select');

        // A form sent back without JavaScript keeps what was chosen, as a field does.
        $request = request();
        $old = $request->hasSession() ? $request->old(Field::keyOf($name)) : null;
        $this->current = is_scalar($old) ? (string) $old : (is_scalar($value) ? (string) $value : '');
    }

    /**
     * `value => label`, or a list of `['value' => …, 'label' => …, 'hint' => …, 'disabled' => …]`.
     *
     * @param  array<array-key, mixed>  $options
     * @return list<array{value: string, label: string, hint: string|null, disabled: bool}>
     */
    public static function normalise(array $options): array
    {
        $choices = [];

        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $value = $option['value'] ?? $option[0] ?? null;
                $label = $option['label'] ?? $option[1] ?? $value;

                if (! is_scalar($value) || ! is_scalar($label)) {
                    throw new InvalidArgumentException('<x-webx-select>: an option is a value and a label.');
                }

                $hint = $option['hint'] ?? null;
                $choices[] = [
                    'value' => (string) $value,
                    'label' => (string) $label,
                    'hint' => is_scalar($hint) && (string) $hint !== '' ? (string) $hint : null,
                    'disabled' => (bool) ($option['disabled'] ?? false),
                ];
            } elseif (is_scalar($option)) {
                $choices[] = ['value' => (string) $key, 'label' => (string) $option, 'hint' => null, 'disabled' => false];
            } else {
                throw new InvalidArgumentException('<x-webx-select>: an option is a value and a label.');
            }
        }

        return $choices;
    }

    public function render(): View
    {
        // The look of the plain select too: without JavaScript it is all there is.
        Widgets::need('select');

        $label = $this->label ?? (string) __('webx-widgets::widgets.select.label');

        return view('webx-widgets::components.select', [
            'labelText' => $label,
            'placeholderText' => $this->placeholder ?? (string) __('webx-widgets::widgets.select.placeholder'),
            'chosenLabel' => $this->chosenLabel(),
            'words' => [
                'nothing' => $this->nothingLabel ?? __('webx-widgets::widgets.select.nothing'),
                'more' => $this->moreLabel ?? __('webx-widgets::widgets.select.more'),
                'count' => $this->countLabel ?? __('webx-widgets::widgets.select.count'),
                'failed' => $this->failedLabel ?? __('webx-widgets::widgets.select.failed'),
                'searching' => $this->searchingLabel ?? __('webx-widgets::widgets.select.searching'),
            ],
        ]);
    }

    /** The label of the value: of its option, or the value itself when free text was typed. */
    private function chosenLabel(): string
    {
        foreach ($this->choices as $choice) {
            if ($choice['value'] === $this->current) {
                return $choice['label'];
            }
        }

        return $this->strict ? '' : $this->current;
    }
}
