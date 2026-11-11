<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Forms;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Demo\DemoLedger;

/**
 * `POST {path}/showcase` — the door of «Kitchen sink › Forms › Sending» (spec §17.6): a form of
 * the demo's own answers by §17.4 the way a sign-in or a checkout would — the validator's errors
 * on an empty form, then the answer the visitor chose: a thank-you, a redirect, HTML in place of
 * the fields, or nothing.
 *
 * Only while the demo is on the site: its journal is what `webx:demo --remove` deletes, and then
 * this answers 404 like any address that is not there. It writes nothing anywhere either way.
 *
 * `POST {path}/showcase/validation` — the door of «Kitchen sink › Forms › Validation» (§17.5):
 * {@see self::CHECKS}, the rules the page's form is checked by in the browser through FormRules,
 * checked here again by the validator — the same words both ways — and a thank-you.
 *
 * `POST {path}/showcase/steps` and `…/steps/check` — the doors of «Kitchen sink › Forms › Steps»
 * (FORM_STEPS §3): {@see self::STEPS}, a step's rules, checked up to the step named `webx_step` (204)
 * or all of them at the end (a thank-you); the meeting's only when a meeting was chosen.
 *
 * Its words come from the page — the showcase's words are the page's localized fields, never
 * this package's — and the page says its language, so the validator speaks it.
 */
final class ShowcaseController
{
    public const array ANSWERS = ['message', 'redirect', 'html', 'nothing'];

    /**
     * The rules of the validation showcase: one of every kind the browser checks, and one it
     * leaves to the server (`not_in` of the name) — what the server alone refuses.
     */
    public const array CHECKS = [
        'check.name' => ['required', 'string', 'min:2', 'max:60', 'not_in:admin,root'],
        'check.email' => ['required', 'email', 'max:190'],
        'check.phone' => ['required', 'regex:/^\+[1-9][0-9 ]{6,18}$/'],
        'check.age' => ['nullable', 'integer', 'between:18,120'],
        'check.website' => ['nullable', 'url'],
        'check.day' => ['required', 'date', 'after:2026-01-01'],
        'check.password' => ['required', 'string', 'min:8', 'confirmed'],
        'check.plan' => ['required', 'in:basic,team'],
        'check.photos' => ['nullable', 'array', 'max:3'],
        'check.photos.*' => ['image', 'max:2048'],
        'check.agree' => ['accepted'],
    ];

    /**
     * The steps of the steps showcase and the rules of each. Two more are the server's alone, so a
     * «Next» that asks the server says what the browser could not: `steps.name` refuses `admin`,
     * and the day of a meeting is a weekday ({@see self::stepsUpTo()}).
     */
    public const array STEPS = [
        'purpose' => ['steps.purpose' => ['required', 'in:question,meeting']],
        'meeting' => ['steps.day' => ['required', 'date', 'after:2026-01-01'], 'steps.time' => ['required', 'in:morning,afternoon']],
        'contacts' => ['steps.name' => ['required', 'string', 'min:2', 'max:60', 'not_in:admin,root'], 'steps.email' => ['required', 'email', 'max:190']],
    ];

    /**
     * Every rule of the steps showcase, for FormRules on the page: the browser checks the fields
     * of the step shown, whatever was chosen.
     *
     * @return array<string, list<string>>
     */
    public static function stepRules(): array
    {
        return array_merge(...array_values(self::STEPS));
    }

    /**
     * The rules of the steps up to `$upTo` (all without it), the meeting's only for a meeting —
     * the way the Inbox counts a step its condition hides out — and its day a weekday, in the
     * page's words (`words[weekday]`).
     *
     * @return array<string, list<mixed>>
     */
    private static function stepsUpTo(Request $request, ?string $upTo = null): array
    {
        $rules = [];
        $weekday = $request->input('words.weekday');
        $weekday = is_string($weekday) && $weekday !== '' ? $weekday : 'Choose a weekday.';

        foreach (self::STEPS as $step => $fields) {
            if ($step !== 'meeting' || $request->input('steps.purpose') === 'meeting') {
                $rules = [...$rules, ...$fields];
            }

            if ($step === 'meeting' && isset($rules['steps.day'])) {
                $rules['steps.day'][] = static function (string $attribute, mixed $value, Closure $fail) use ($weekday): void {
                    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && Carbon::parse($value)->isWeekend()) {
                        $fail($weekday);
                    }
                };
            }

            if ($step === $upTo) {
                break;
            }
        }

        return $rules;
    }

    /**
     * The names the validator says, from the labels of the steps' form (`labels[day]` …).
     *
     * @return array<string, string>
     */
    private static function stepAttributes(Request $request): array
    {
        $labels = array_filter((array) $request->input('labels', []), 'is_string');
        $attributes = [];

        foreach (array_keys(self::stepRules()) as $key) {
            $short = explode('.', $key)[1];

            if (($labels[$short] ?? '') !== '') {
                $attributes[$key] = $labels[$short];
            }
        }

        return $attributes;
    }

    /** `POST …/showcase/steps/check`: the steps up to `webx_step`, nothing taken — 204 or a 422. */
    public function stepCheck(Request $request): Response
    {
        abort_unless(self::available(), 404);
        self::speak($request);

        $step = $request->input('webx_step');
        abort_unless(is_string($step) && array_key_exists($step, self::STEPS), 422);

        Validator::make($request->all(), self::stepsUpTo($request, $step), [], self::stepAttributes($request))->validate();

        return response()->noContent();
    }

    /** `POST …/showcase/steps`: every step shown, then a thank-you. */
    public function steps(Request $request): FormResponse
    {
        abort_unless(self::available(), 404);
        self::speak($request);

        Validator::make($request->all(), self::stepsUpTo($request), [], self::stepAttributes($request))->validate();

        $words = array_filter((array) $request->input('words', []), 'is_string');
        $name = e((string) $request->input('steps.name'));

        return FormResponse::message($words['heading'] ?? 'Thank you', '<p>'.str_replace(':name', $name, e($words['message'] ?? 'Sent, :name.')).'</p>');
    }

    /**
     * The names the validator says, from the labels of the page's form — on the page for
     * FormRules, posted back for the door.
     *
     * @param  array<array-key, mixed>  $labels  `name` => `Your name`, by the last part of the key
     * @return array<string, string>
     */
    public static function attributes(array $labels): array
    {
        $attributes = [];

        foreach (array_keys(self::CHECKS) as $key) {
            $short = explode('.', $key)[1];

            if (is_string($labels[$short] ?? null) && $labels[$short] !== '') {
                $attributes[$key] = $labels[$short];
            }
        }

        return $attributes;
    }

    public function validation(Request $request): FormResponse
    {
        abort_unless(self::available(), 404);
        self::speak($request);

        Validator::make($request->all(), self::CHECKS, [], self::attributes((array) $request->input('labels', [])))->validate();

        $words = array_filter((array) $request->input('words', []), 'is_string');

        return FormResponse::message($words['heading'] ?? 'Thank you', '<p>'.e($words['message'] ?? 'Everything is right.').'</p>');
    }

    /** The page says its language, so the validator speaks it. */
    private static function speak(Request $request): void
    {
        $locale = $request->input('locale');

        if (is_string($locale) && preg_match('/^[a-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/', $locale) === 1) {
            App::setLocale($locale);
        }
    }

    public static function available(): bool
    {
        return class_exists(DemoLedger::class)
            && Container::getInstance()->bound(DemoLedger::class)
            && Container::getInstance()->make(DemoLedger::class)->exists();
    }

    public function __invoke(Request $request): FormResponse
    {
        abort_unless(self::available(), 404);
        self::speak($request);

        $labels = array_filter((array) $request->input('labels', []), 'is_string');

        $data = Validator::make($request->all(), [
            'showcase.name' => ['required', 'string', 'max:120'],
            'showcase.email' => ['required', 'email', 'max:190'],
            'showcase.answer' => ['required', 'in:'.implode(',', self::ANSWERS)],
            'showcase.agree' => ['accepted'],
        ], [], [
            'showcase.name' => $labels['name'] ?? 'name',
            'showcase.email' => $labels['email'] ?? 'email',
            'showcase.answer' => $labels['answer'] ?? 'answer',
            'showcase.agree' => $labels['agree'] ?? 'agree',
        ])->validate();

        $words = array_filter((array) $request->input('words', []), 'is_string');
        $name = e((string) data_get($data, 'showcase.name'));

        return match (data_get($data, 'showcase.answer')) {
            'message' => FormResponse::message(
                $words['heading'] ?? 'Thank you',
                '<p>'.str_replace(':name', $name, e($words['message'] ?? 'Sent, :name.')).'</p>',
            ),
            'redirect' => FormResponse::redirect(self::local($request->input('to')) ?? url('/')),
            'html' => FormResponse::html(
                '<div class="webx-showcase-step"><p>'.str_replace(':name', $name, e($words['step'] ?? 'Step two, :name.')).'</p></div>',
            ),
            default => FormResponse::ok(),
        };
    }

    /**
     * `GET {path}/showcase/suggest?q=` — the door of «Kitchen sink › Forms › Suggestions» (W6.5):
     * what a site's address of suggestions answers, HTML for `<x-webx-suggest>`. The goods are the
     * page's (`items`, a line each, «name | price»), and so are the words (`words[products]`,
     * `words[all]`, `words[nothing]`); `as=values` answers values for a field inside another form
     * instead of cards with a price and «see all». Nothing asked — 204.
     */
    public function suggest(Request $request): Response
    {
        abort_unless(self::available(), 404);

        $headers = ['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow'];
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response('', 204, $headers);
        }

        $words = array_filter((array) $request->query('words', []), 'is_string');
        $found = self::found(self::items((string) $request->query('items', '')), $term);
        $headers['Content-Type'] = 'text/html; charset=UTF-8';

        if ($found === []) {
            return response('<p class="b-showcase-suggest__nothing">'.e(str_replace(':q', $term, $words['nothing'] ?? 'Nothing for “:q”.')).'</p>', 200, $headers);
        }

        if ($request->query('as') === 'values') {
            $html = '<ul class="b-showcase-suggest__values">';

            foreach (array_slice($found, 0, 8) as $item) {
                $html .= '<li class="b-showcase-suggest__value" data-webx-option="'.e($item['name']).'">'.self::marked($item['name'], $term).'</li>';
            }

            return response($html.'</ul>', 200, $headers);
        }

        $count = (string) count($found);
        // A card leads to the results page for its own name: the demo has no product pages.
        $to = self::local($request->query('to')) ?? '/';
        $html = '<div class="b-showcase-suggest__found" data-webx-section="products">'
            .'<p class="b-showcase-suggest__found-heading" data-webx-heading>'.e(str_replace(':count', $count, $words['products'] ?? 'Products (:count)')).'</p>';

        foreach (array_slice($found, 0, 5) as $item) {
            $html .= '<a class="b-showcase-suggest__card" href="'.e($to.'?q='.rawurlencode($item['name'])).'" data-webx-option>'
                .'<span class="b-showcase-suggest__card-body"><span class="b-showcase-suggest__card-name">'.self::marked($item['name'], $term).'</span>'
                .($item['price'] !== '' ? '<span class="b-showcase-suggest__card-price">'.e($item['price']).'</span>' : '').'</span>'
                .'</a>';
        }

        $html .= '</div><a class="b-showcase-suggest__all" href="'.e($to.'?q='.rawurlencode($term)).'" data-webx-option>'
            .e(str_replace(':count', $count, $words['all'] ?? 'See all: :count')).'</a>';

        return response($html, 200, $headers);
    }

    /**
     * The goods of the showcase, a line each: «name | price». For the door and for the block,
     * which lists what was found on the results page without JavaScript.
     *
     * @return list<array{name: string, price: string}>
     */
    public static function items(string $lines): array
    {
        $items = [];

        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            [$name, $price] = array_map('trim', explode('|', $line, 2) + [1 => '']);

            if ($name !== '') {
                $items[] = ['name' => $name, 'price' => $price];
            }
        }

        return $items;
    }

    /**
     * @param  list<array{name: string, price: string}>  $items
     * @return list<array{name: string, price: string}>
     */
    public static function found(array $items, string $term): array
    {
        return array_values(array_filter($items, static fn (array $item): bool => mb_stripos($item['name'], $term) !== false));
    }

    /** The name with the words typed in `<mark>`, escaped around it. */
    private static function marked(string $name, string $term): string
    {
        $at = mb_stripos($name, $term);

        if ($at === false) {
            return e($name);
        }

        $length = mb_strlen($term);

        return e(mb_substr($name, 0, $at)).'<mark class="b-showcase-suggest__mark">'.e(mb_substr($name, $at, $length)).'</mark>'.e(mb_substr($name, $at + $length));
    }

    /** A path of this site — never another site's address: the demo is not an open redirect. */
    private static function local(mixed $to): ?string
    {
        return is_string($to) && preg_match('~^/(?![/\\\\])~', $to) === 1 ? $to : null;
    }
}
