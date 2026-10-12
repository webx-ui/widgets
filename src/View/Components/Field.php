<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;

/**
 * `<x-webx-field>` — one field of a form on the site (spec §17.2): the label, the control, the
 * hint and the place its error goes, in one markup for every form a module draws.
 *
 * Laravel does the rest on its own: the value is `old($name)`, the error `$errors->first($name)`.
 * A name is written with dots (`fields.email`, `items.0.qty`) or with brackets (`fields[email]`);
 * the control is named with brackets, which is what PHP reads back into an array, and the error is
 * looked up by dots, which is what the validator calls it. So a form sent without JavaScript comes
 * back with each error under its own field.
 *
 * `class` goes on the field; every other attribute on the control (on the `<fieldset>` of a group),
 * where `data-*` and a script's hooks are wanted.
 */
final class Field extends Component
{
    public const array TYPES = ['text', 'email', 'tel', 'url', 'password', 'number', 'textarea', 'select', 'radio', 'checkbox', 'consent', 'date', 'file', 'hidden'];

    /**
     * What a password manager, an address book and a phone's keyboard go by. Only the type is
     * known here; a field named `name` or `postal-code` says so with its own `autocomplete`.
     */
    private const array AUTOCOMPLETE = ['email' => 'email', 'tel' => 'tel', 'url' => 'url', 'password' => 'current-password'];

    private const array INPUTMODE = ['email' => 'email', 'tel' => 'tel', 'url' => 'url'];

    /** An address, a password: a capital letter or a "correction" put in by the keyboard is a typo. */
    private const array LITERAL = ['email', 'url', 'password'];

    /** The validator's name of the field: dots, no trailing `[]`. */
    public string $key;

    /** The control's name: brackets, `[]` where several values go. */
    public string $inputName;

    public string $fieldId;

    public ?string $message;

    /** @var list<array{value: string, label: string, disabled: bool}> */
    public array $options = [];

    /**
     * The checks of FormRules as the browser runs them (`data-webx-rules` on the field), or null
     * when the form gave no rules this field has.
     */
    public ?string $checks = null;

    /** The page came back from another of its forms: what is in the session is not this field's. */
    private bool $foreign = false;

    /** @param  array<array-key, mixed>  $choices  `value => label`, or a list of `['value', 'label', 'disabled']` */
    public function __construct(
        public string $name,
        public string $type = 'text',
        public ?string $label = null,
        public ?string $hint = null,
        public mixed $value = null,
        public bool $checked = false,
        array $choices = [],
        public bool $required = false,
        public bool $disabled = false,
        public bool $readonly = false,
        public bool $multiple = false,
        public bool $inline = false,
        public bool $optional = false,
        public ?string $placeholder = null,
        public string|false|null $autocomplete = null,
        public string|false|null $inputmode = null,
        public string|false|null $enterkeyhint = null,
        public ?string $error = null,
        public string $bag = 'default',
        ?string $id = null,
        public ?int $rows = null,
        public int|float|string|null $min = null,
        public int|float|string|null $max = null,
        public int|float|string|null $step = null,
        public ?int $minlength = null,
        public ?int $maxlength = null,
        public ?string $pattern = null,
        public ?string $accept = null,
        public ?string $optionalLabel = null,
        public ?string $showLabel = null,
        public ?string $showPasswordLabel = null,
        public ?string $chooseLabel = null,
        public ?string $fileLabel = null,
        public ?string $noFileLabel = null,
        public ?string $filesLabel = null,
        public ?string $removeLabel = null,
        public ?string $dropLabel = null,
        mixed $rules = null,
        public bool $mask = false,
    ) {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("<x-webx-field type=\"{$type}\">: one of ".implode(', ', self::TYPES).'.');
        }

        $this->key = self::keyOf($name);

        if ($this->key === '') {
            throw new InvalidArgumentException('<x-webx-field>: a name is required — it is what the value and the error are found by.');
        }

        foreach ($choices as $choice => $text) {
            $this->options[] = is_array($text)
                ? ['value' => (string) ($text['value'] ?? ''), 'label' => (string) ($text['label'] ?? $text['value'] ?? ''), 'disabled' => (bool) ($text['disabled'] ?? false)]
                : ['value' => (string) $choice, 'label' => (string) $text, 'disabled' => false];
        }

        if (in_array($type, ['select', 'radio'], true) && $this->options === []) {
            throw new InvalidArgumentException("<x-webx-field type=\"{$type}\" name=\"{$name}\">: :choices — there is nothing to choose from.");
        }

        $this->inputName = self::nameOf($this->key).($this->many() ? '[]' : '');
        // Two forms with a field of one name on a page — a sign-in in the header and on the page —
        // keep their labels pointing at their own controls.
        $base = $id ?? 'field-'.trim((string) preg_replace('/[^a-z0-9]+/i', '-', $this->key), '-');
        $this->fieldId = $id ?? (($n = Widgets::sequence('field:'.$base)) > 1 ? "{$base}-{$n}" : $base);
        $this->foreign = $this->sentByAnother();
        $this->message = $type === 'hidden' ? null : ($error ?? $this->errorFromSession());
        $this->applyRules($rules);
    }

    /**
     * The rules the server checks this field by, as the browser can (spec §17.5): its own `:rules`
     * — a list for this field, or a FormRules — else those of the `<x-webx-form :rules>` around it.
     * What they say fills what the page did not: `required`, the limits of a number or a date, the
     * files a picker offers, a number's keyboard.
     */
    private function applyRules(mixed $rules): void
    {
        if ($this->type === 'hidden') {
            return;
        }

        if (is_string($rules) || (is_array($rules) && array_is_list($rules))) {
            $rules = FormRules::for([$this->key => $rules]);
        } elseif (is_array($rules)) {
            $rules = FormRules::for($rules);
        } elseif (! $rules instanceof FormRules) {
            // The nearest form's — Blade keeps the data of every component the field stands in.
            $rules = app('view')->getConsumableComponentData(Form::RULES);
        }

        if (! $rules instanceof FormRules) {
            return;
        }

        $kind = match (true) {
            $this->type === 'file' => $this->multiple ? 'files' : 'file',
            $this->many() => 'many',
            default => 'one',
        };
        $found = $rules->field($this->key, $kind);
        $given = $found['attributes'];

        $this->required = $this->required || $found['required'];

        if ($this->type === 'number') {
            $this->min ??= $given['min'] ?? null;
            $this->max ??= $given['max'] ?? null;
        }

        if ($this->type === 'date') {
            $this->min ??= $given['date-min'] ?? null;
            $this->max ??= $given['date-max'] ?? null;
        }

        if ($this->type === 'file') {
            $this->accept ??= $given['accept'] ?? null;
        }

        if ($this->type === 'text' && $this->inputmode === null && isset($given['inputmode'])) {
            $this->inputmode = $given['inputmode'];
        }

        if ($found['checks'] !== []) {
            $this->checks = json_encode($found['checks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
    }

    /** `fields[email]` and `fields.email` are one field to the validator: `fields.email`. */
    public static function keyOf(string $name): string
    {
        $name = trim((string) preg_replace('/\[\]$/', '', trim($name)));

        return trim(str_replace(['][', '[', ']'], ['.', '.', ''], $name), '.');
    }

    /** `items.0.qty` as a browser has to send it: `items[0][qty]`. */
    public static function nameOf(string $key): string
    {
        $parts = explode('.', $key);

        return array_shift($parts).implode('', array_map(static fn (string $part): string => "[{$part}]", $parts));
    }

    /** A group of radios or ticks: a `<fieldset>`, its label the `<legend>`. */
    public function grouped(): bool
    {
        return $this->type === 'radio' || ($this->type === 'checkbox' && $this->options !== []);
    }

    /** One tick whose words stand beside it rather than above. */
    public function tick(): bool
    {
        return $this->type === 'consent' || ($this->type === 'checkbox' && $this->options === []);
    }

    private function many(): bool
    {
        return ($this->type === 'checkbox' && $this->options !== [])
            || ($this->multiple && in_array($this->type, ['select', 'file'], true));
    }

    public function shouldRender(): bool
    {
        return true;
    }

    public function render(): View
    {
        Widgets::need('field');

        if ($this->type === 'file') {
            // Dropping, the list with sizes and pictures, removing one before sending.
            Widgets::need('files');
        }

        if ($this->type === 'tel' && $this->mask) {
            Widgets::need('mask');
        }

        return view('webx-widgets::components.field', [
            'invalid' => $this->message !== null && $this->message !== '',
            'hintId' => $this->fieldId.'-hint',
            'errorId' => $this->fieldId.'-error',
            'shown' => $this->shown(),
            'chosen' => $this->chosen(),
            'ticked' => $this->ticked(),
            'words' => [
                'optional' => $this->optionalLabel ?? __('webx-widgets::widgets.field.optional'),
                'show' => $this->showLabel ?? __('webx-widgets::widgets.field.show'),
                'show_password' => $this->showPasswordLabel ?? __('webx-widgets::widgets.field.show_password'),
                'choose' => $this->chooseLabel ?? __('webx-widgets::widgets.field.choose'),
                'file' => $this->fileLabel ?? __($this->multiple ? 'webx-widgets::widgets.field.choose_files' : 'webx-widgets::widgets.field.choose_file'),
                'no_file' => $this->noFileLabel ?? __('webx-widgets::widgets.field.no_file'),
                'files' => $this->filesLabel ?? __('webx-widgets::widgets.field.files'),
                'remove' => $this->removeLabel ?? __('webx-widgets::widgets.field.remove'),
                'drop' => $this->dropLabel ?? __($this->multiple ? 'webx-widgets::widgets.field.drop_files' : 'webx-widgets::widgets.field.drop_file'),
            ],
            'control' => fn (ComponentAttributeBag $attributes): ComponentAttributeBag => $this->control($attributes),
        ]);
    }

    /**
     * The attributes of the control the type does not write itself: its state, its wiring to the
     * hint and the error, the hints for a keyboard and an autofill. `false` and `null` print nothing.
     */
    private function control(ComponentAttributeBag $attributes): ComponentAttributeBag
    {
        $invalid = $this->message !== null && $this->message !== '';
        $typed = ! in_array($this->type, ['radio', 'checkbox', 'consent', 'file', 'hidden'], true);
        $line = in_array($this->type, ['text', 'email', 'tel', 'url', 'password'], true);
        $ranged = in_array($this->type, ['number', 'date'], true);
        $literal = in_array($this->type, self::LITERAL, true);

        $own = [
            'required' => $this->required && ! ($this->type === 'checkbox' && $this->options !== []),
            'disabled' => $this->disabled,
            'readonly' => $this->readonly && $typed,
            'multiple' => $this->multiple && in_array($this->type, ['select', 'file'], true),
            'placeholder' => $line || in_array($this->type, ['number', 'textarea'], true) ? $this->placeholder : null,
            'autocomplete' => $typed ? self::chosenOr($this->autocomplete, self::AUTOCOMPLETE[$this->type] ?? null) : null,
            'inputmode' => $typed ? self::chosenOr($this->inputmode, $this->type === 'number' ? $this->numberMode() : (self::INPUTMODE[$this->type] ?? null)) : null,
            'enterkeyhint' => $typed ? self::chosenOr($this->enterkeyhint, null) : null,
            'spellcheck' => $literal ? 'false' : null,
            'autocapitalize' => $literal ? 'none' : null,
            'minlength' => $line || $this->type === 'textarea' ? $this->minlength : null,
            'maxlength' => $line || $this->type === 'textarea' ? $this->maxlength : null,
            'pattern' => $line && $this->type !== 'email' ? $this->pattern : null,
            'min' => $ranged ? $this->min : null,
            'max' => $ranged ? $this->max : null,
            'step' => $ranged ? $this->step : null,
            'accept' => $this->type === 'file' ? $this->accept : null,
            'data-webx-mask' => $this->type === 'tel' && $this->mask ? 'phone' : null,
            'aria-describedby' => $this->grouped() ? null : $this->describedBy(),
            'aria-invalid' => $invalid && ! $this->grouped() ? 'true' : null,
        ];

        $own = array_filter($own, static fn (mixed $value): bool => $value !== null && $value !== false && $value !== '');

        // The page's own attributes after the field's, and over them where both name one.
        return $this->grouped() ? new ComponentAttributeBag($own) : $attributes->except('class')->merge($own);
    }

    /** The hint, then the error: read in the order they stand. */
    public function describedBy(): string
    {
        return trim(($this->hint !== null && trim($this->hint) !== '' ? $this->fieldId.'-hint ' : '').$this->fieldId.'-error');
    }

    /**
     * A whole step (1 when none is given, as the browser has it) wants the digits only; a fraction
     * or `any` the decimal separator too. Neither pad has a minus: a field that takes a negative
     * number says `:inputmode="false"`.
     */
    private function numberMode(): string
    {
        if ($this->step === null) {
            return 'numeric';
        }

        return $this->step !== 'any' && floor((float) $this->step) === (float) $this->step ? 'numeric' : 'decimal';
    }

    private static function chosenOr(string|false|null $chosen, ?string $default): ?string
    {
        return $chosen === false ? null : ($chosen ?? $default);
    }

    /** What the visitor sent back, or what the page gives: a value of one control. */
    private function shown(): string
    {
        if (in_array($this->type, ['password', 'file'], true)) {
            // A password is never printed back into the page; a file cannot be.
            return '';
        }

        $value = $this->old($this->value);

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * The values chosen in a group, a radio or a select. Once a form has come back, what it sent is
     * the whole answer: a box left unticked sends nothing, and must not tick itself again from the
     * page's default.
     *
     * @return list<string>
     */
    private function chosen(): array
    {
        if (! in_array($this->type, ['radio', 'checkbox', 'select'], true)) {
            return [];
        }

        $value = $this->returned() ? $this->old(null) : $this->value;

        if (is_string($value) && $this->many() && str_contains($value, ',')) {
            $value = explode(',', $value);
        }

        return array_values(array_map(static fn (mixed $one): string => (string) $one, array_filter((array) $value, static fn (mixed $one): bool => is_scalar($one))));
    }

    private function ticked(): bool
    {
        if (! $this->tick()) {
            return false;
        }

        return $this->returned() ? ! in_array($this->old(null), [null, '', '0', false], true) : $this->checked;
    }

    /** The form of this field — or, outside `<x-webx-form>`, any form of the page — came back with what it sent. */
    private function returned(): bool
    {
        $request = request();

        return ! $this->foreign && $request->hasSession() && $request->session()->hasOldInput();
    }

    private function old(mixed $default): mixed
    {
        $request = request();

        return $request->hasSession() && ! $this->foreign ? $request->old($this->key, $default) : $default;
    }

    /**
     * Inside `<x-webx-form>`, and the page came back from another form of it — `_webx_form` names
     * the one sent. A contact form refused in the page's body leaves the subscription in its footer
     * as it was, though both have an `email`.
     */
    private function sentByAnother(): bool
    {
        $request = request();
        $form = app('view')->getConsumableComponentData(Form::ID);

        if (! is_string($form) || ! $request->hasSession()) {
            return false;
        }

        $sent = $request->old(FormResponse::FORM);

        return is_string($sent) && $sent !== $form;
    }

    /** The validator's word for this field — for a group, for any of its items too. */
    private function errorFromSession(): ?string
    {
        if ($this->foreign) {
            return null;
        }

        $errors = app('view')->shared('errors');
        $request = request();

        if (! $errors instanceof ViewErrorBag && $request->hasSession()) {
            $errors = $request->session()->get('errors');
        }

        $bag = $errors instanceof ViewErrorBag ? $errors->getBag($this->bag) : $errors;

        if (! $bag instanceof MessageBag) {
            return null;
        }

        $message = $bag->first($this->key);

        if ($message === '' && $this->many()) {
            $message = $bag->first($this->key.'.*');
        }

        return $message === '' ? null : $message;
    }
}
