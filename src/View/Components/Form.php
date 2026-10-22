<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;

/**
 * `<x-webx-form :action method>` — the shell of every form of the site (spec §17.3).
 *
 * Without JavaScript it is a plain form: `@csrf` and `@method` printed by itself, a POST, and the
 * page back with the errors under the fields (`<x-webx-field>`) or the answer of
 * {@see FormResponse} in place. With it (`dist/form.js`) the same form is sent in place: the token
 * from the cookie, one retry on a stale one, the errors of a 422 by field, no second submission
 * while the first is on its way, the answer in a live region.
 *
 * `:rules` — the server's rules, an array or {@see FormRules::for()}: every field inside checks
 * itself by its own part of them in the browser, in the validator's words (spec §17.5).
 *
 * `:csrf="false"` — for a door that guards itself otherwise (inbox's intake: Origin, a honeypot, a
 * limit). `method="get"` — a search: a plain form, nothing to send in place.
 *
 * `again` — the thank-you takes the place of the fields, with a button under it that brings them
 * back empty, the focus on the first (`webx-form__again`, its words `:again-label`). Without
 * JavaScript it is a link to the same page, which comes back without the answer.
 */
final class Form extends Component
{
    public const array METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /** What the browser sends: GET or POST, the rest is spoofed through `_method`. */
    public string $verb;

    public ?string $spoof;

    public string $formId;

    /** The name the fields inside find the rules by: Blade keeps a component's public data for its slot. */
    public const string RULES = 'webxFormRules';

    public ?FormRules $webxFormRules;

    /**
     * The name the fields inside find the form's id by: a page that came back from another of its
     * forms is not this form's answer, and its fields keep what the page gave them.
     */
    public const string ID = 'webxFormId';

    public string $webxFormId;

    public function __construct(
        public string $action,
        public string $method = 'post',
        public bool $csrf = true,
        ?string $id = null,
        public string $bag = 'default',
        public ?string $failedLabel = null,
        public ?string $expiredLabel = null,
        public ?string $closeLabel = null,
        mixed $rules = null,
        public bool $again = false,
        public ?string $againLabel = null,
    ) {
        $this->webxFormRules = match (true) {
            $rules instanceof FormRules => $rules,
            is_array($rules) => FormRules::for($rules),
            default => null,
        };

        $method = strtolower(trim($method));

        if (! in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException("<x-webx-form method=\"{$this->method}\">: one of ".implode(', ', self::METHODS).'.');
        }

        $this->method = $method;
        $this->verb = $method === 'get' ? 'get' : 'post';
        $this->spoof = in_array($method, ['get', 'post'], true) ? null : strtoupper($method);
        // The same on every rendering of the page, so the page that comes back after a POST
        // knows which of its forms was the one sent.
        $this->formId = $id ?? 'form-'.Widgets::sequence('form');
        $this->webxFormId = $this->formId;
    }

    public function render(): View
    {
        Widgets::need('form');

        $answer = $this->answer();

        return view('webx-widgets::components.form', [
            'sending' => $this->verb === 'post',
            'answer' => $answer,
            'sent' => $answer !== null || $this->sentWithErrors(),
            'unplaced' => fn (string $slot): array => $this->unplaced($slot),
            'words' => [
                'failed' => $this->failedLabel ?? __('webx-widgets::widgets.form.failed'),
                'expired' => $this->expiredLabel ?? __('webx-widgets::widgets.form.expired'),
                'close' => $this->closeLabel ?? __('webx-widgets::widgets.dialog.close'),
                'again' => $this->againLabel ?? __('webx-widgets::widgets.form.again'),
            ],
            // The page itself, which comes back without what was answered: the flash is gone by
            // then. No `#id` — a link to a fragment of the same page is not a new request.
            'againUrl' => $this->again ? request()->fullUrl() : null,
        ]);
    }

    /**
     * What {@see FormResponse} answered this form, when the page came back from it without
     * JavaScript.
     *
     * @return array{heading: ?string, heading_html: ?string, message: ?string, html: ?string}|null
     */
    private function answer(): ?array
    {
        $request = request();
        $flash = $request->hasSession() ? $request->session()->get(FormResponse::FLASH) : null;

        if (! is_array($flash) || ($flash['form'] ?? null) !== $this->formId) {
            return null;
        }

        $text = static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null;

        return ['heading' => $text($flash['heading'] ?? null), 'heading_html' => $text($flash['heading_html'] ?? null), 'message' => $text($flash['message'] ?? null), 'html' => $text($flash['html'] ?? null)];
    }

    /** The validator sent this form back: Laravel flashes the input with its errors. */
    private function sentWithErrors(): bool
    {
        $request = request();

        return $request->hasSession() && $request->old(FormResponse::FORM) === $this->formId && $this->bag()?->isNotEmpty() === true;
    }

    /**
     * The errors no field of this form shows — a field the form does not have, a refusal of the
     * whole form: said in the form's own place rather than nowhere. Only for the form that was sent.
     *
     * @return list<string>
     */
    private function unplaced(string $slot): array
    {
        $bag = $this->bag();

        if ($bag === null || ! $this->sentWithErrors()) {
            return [];
        }

        preg_match_all('/data-webx-field="([^"]+)"/', $slot, $fields);
        $messages = [];

        foreach ($bag->keys() as $key) {
            foreach ($fields[1] as $field) {
                $field = html_entity_decode($field, ENT_QUOTES);

                if ($key === $field || str_starts_with($key, $field.'.')) {
                    continue 2;
                }
            }

            array_push($messages, ...$bag->get($key));
        }

        return array_values(array_unique(array_filter($messages, 'is_string')));
    }

    private function bag(): ?MessageBag
    {
        $errors = app('view')->shared('errors');
        $request = request();

        if (! $errors instanceof ViewErrorBag && $request->hasSession()) {
            $errors = $request->session()->get('errors');
        }

        return $errors instanceof ViewErrorBag ? $errors->getBag($this->bag) : null;
    }
}
