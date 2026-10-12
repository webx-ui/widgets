<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Forms;

use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\ValidationRuleParser;
use Stringable;

/**
 * The rules of a form, written once in Laravel, as a browser can check them (spec §17.5).
 *
 * ```blade
 * <x-webx-form :action="route('checkout.contact')" :rules="FormRules::for($rules, $attributes)">
 * ```
 *
 * Each `<x-webx-field>` inside asks for its own: the part of its rules a browser understands
 * becomes attributes of the field — `required`, `min`/`max` of a number or a date, `accept` of a
 * file, the keyboard of a number — and a list in `data-webx-rules`, every check with the line the
 * validator would write if it failed, in the page's language. `dist/form.js` checks by that list.
 *
 * Understood: `required`, `accepted`, `email`, `url`, `numeric`, `integer`, `min`, `max`,
 * `between`, `size` (a string's length, a number, a file's kilobytes, a count — by the field),
 * `regex` and `not_regex` (when the expression means the same in JavaScript), `in`, `mimes`,
 * `extensions`, `mimetypes`, `image`, `confirmed`, `same`, `different`, `date`, `after`, `before`,
 * `after_or_equal`, `before_or_equal` with a literal date, and `bail`, `nullable`, which change how
 * the others run. Everything else — `unique`, `exists`, a closure, a rule object — only the server
 * checks: silence, not an error. The server checks everything always.
 */
final class FormRules
{
    /** Rules that need no translation: the browser has nothing to check, and nothing to say. */
    private const array MARKS = ['Bail', 'Nullable'];

    private const array SIMPLE = ['Required', 'Accepted', 'Email', 'Url', 'Numeric', 'Integer', 'Date'];

    private const array SIZES = ['Min', 'Max', 'Size', 'Between'];

    private const array DATES = ['After', 'Before', 'AfterOrEqual', 'BeforeOrEqual'];

    /** What `image` lets through (`validateImage` of Laravel). */
    private const array IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'avif', 'heic', 'heif'];

    /** PCRE that JavaScript reads otherwise or not at all: such a pattern is the server's alone. */
    private const string FOREIGN = '/\(\?[>|#(R+\-0-9]|\(\?[a-zA-Z]+[):]|\(\?P[>=]|\\\\[AzZGKQEhHvVRXC]|(?<!\\\\)(?:[*+?]|\{\d+(?:,\d*)?\})\+|\\\\[gk]\{?-?[0-9]/';

    private ?RuleWords $words = null;

    /**
     * @param  array<string, mixed>  $rules  key => rules, as the validator takes them
     * @param  array<string, string>  $attributes  the names the validator says (`attributes()` of a FormRequest)
     * @param  array<string, string>  $messages  the form's own lines (`messages()` of a FormRequest)
     */
    private function __construct(
        private readonly array $rules,
        private readonly array $attributes,
        private readonly array $messages,
    ) {}

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $attributes
     * @param  array<string, string>  $messages
     */
    public static function for(array $rules, array $attributes = [], array $messages = []): self
    {
        return new self($rules, $attributes, $messages);
    }

    /**
     * What a field asks for. `$kind` is what its value will be: `one` (a line, a choice, a tick),
     * `many` (a group of ticks, a select of several), `file`, `files`.
     *
     * @return array{required: bool, attributes: array<string, string>, checks: list<array<string, mixed>>}
     */
    public function field(string $key, string $kind = 'one'): array
    {
        $own = [];
        $items = [];
        $ownPattern = null;
        $itemPattern = null;
        $segments = explode('.', $key);

        foreach ($this->rules as $pattern => $rules) {
            $parts = explode('.', (string) $pattern);

            if (self::matches($parts, $segments)) {
                $own = [...$own, ...self::parsed($rules)];
                $ownPattern = (string) $pattern;
            } elseif (count($parts) === count($segments) + 1 && end($parts) === '*' && self::matches(array_slice($parts, 0, -1), $segments)) {
                $items = [...$items, ...self::parsed($rules)];
                $itemPattern = (string) $pattern;
            }
        }

        $whole = in_array($kind, ['many', 'files'], true);
        $ownType = self::type($own, $whole ? 'array' : ($kind === 'file' ? 'file' : 'string'));
        $itemType = self::type($items, $kind === 'files' ? 'file' : 'string');
        $checks = [
            ...$this->checks($own, $key, $ownType, $ownPattern, false),
            ...($whole ? $this->checks($items, $key.'.0', $itemType, $itemPattern, true) : []),
        ];

        $names = array_column($own, 0);
        $required = in_array('Required', $names, true) || in_array('Accepted', $names, true);

        return ['required' => $required, 'attributes' => self::attributes($own, $items, $kind), 'checks' => $checks];
    }

    /**
     * @param  list<string>  $pattern
     * @param  list<string>  $key
     */
    private static function matches(array $pattern, array $key): bool
    {
        if (count($pattern) !== count($key)) {
            return false;
        }

        foreach ($pattern as $i => $part) {
            if ($part !== '*' && $part !== $key[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * The rules of one key as Laravel reads them — `a|b`, a list, `Rule::in()` — each `[Name, params]`.
     * An object the browser cannot know (a closure, `Rule::unique()`, a rule class) is left out.
     *
     * @return list<array{0: string, 1: list<mixed>}>
     */
    private static function parsed(mixed $rules): array
    {
        $list = is_string($rules) ? explode('|', $rules) : (is_array($rules) ? $rules : [$rules]);
        $parsed = [];

        foreach ($list as $rule) {
            if (is_object($rule) && ! $rule instanceof Stringable) {
                continue;
            }

            if ($rule instanceof Stringable) {
                // Rule::in(), Rule::date() and their kind say themselves as a string; a rule class
                // that is Stringable for its own reasons says something the browser cannot use.
                $rule = (string) $rule;

                if (str_contains($rule, '|')) {
                    $parsed = [...$parsed, ...self::parsed($rule)];

                    continue;
                }
            }

            if (! is_string($rule) && ! is_array($rule)) {
                continue;
            }

            [$name, $parameters] = ValidationRuleParser::parse($rule);

            if (is_string($name) && $name !== '') {
                $parsed[] = [$name, array_values((array) $parameters)];
            }
        }

        return $parsed;
    }

    /**
     * What a size rule measures, as the validator decides: a number when a numeric rule is there,
     * a count for an array, else what the field holds.
     *
     * @param  list<array{0: string, 1: list<mixed>}>  $rules
     */
    private static function type(array $rules, string $default): string
    {
        $names = array_column($rules, 0);

        return match (true) {
            array_intersect($names, ['Numeric', 'Integer', 'Decimal']) !== [] => 'numeric',
            array_intersect($names, ['Array', 'List']) !== [] => 'array',
            default => $default,
        };
    }

    /**
     * @param  list<array{0: string, 1: list<mixed>}>  $rules
     * @return list<array<string, mixed>>
     */
    private function checks(array $rules, string $attribute, string $type, ?string $pattern, bool $each): array
    {
        $checks = [];

        foreach ($rules as [$name, $parameters]) {
            $check = $this->check($name, $parameters, $type);

            if ($check === null) {
                continue;
            }

            if (! in_array($name, self::MARKS, true)) {
                $check['message'] = $this->words()->say($attribute, $name, $parameters, $type, $pattern !== null && str_contains($pattern, '*') ? $pattern : null);
            }

            if ($each) {
                $check['each'] = true;
                // The validator names the item by its index: the browser writes its own in place of 0.
                $check['item'] = $attribute;
            }

            $checks[] = $check;
        }

        return $checks;
    }

    /**
     * One rule as the browser checks it, or null for one only the server can.
     *
     * @param  list<mixed>  $parameters
     * @return array<string, mixed>|null
     */
    private function check(string $name, array $parameters, string $type): ?array
    {
        $rule = ['rule' => self::snake($name)];

        return match (true) {
            in_array($name, self::MARKS, true), in_array($name, self::SIMPLE, true) => $rule,
            in_array($name, self::SIZES, true) => self::sized($rule, $name, $parameters, $type),
            $name === 'In' => $rule + ['values' => array_map('strval', $parameters)],
            in_array($name, ['Mimes', 'Extensions'], true) => $parameters === [] ? null : $rule + ['values' => array_map(static fn (mixed $value): string => strtolower((string) $value), $parameters)],
            $name === 'Mimetypes' => $parameters === [] ? null : $rule + ['values' => array_map('strval', $parameters)],
            $name === 'Image' => ['rule' => 'mimes', 'values' => in_array('allow_svg', $parameters, true) ? [...self::IMAGES, 'svg'] : self::IMAGES],
            $name === 'Confirmed' => ['rule' => 'confirmed', 'other' => isset($parameters[0]) ? (string) $parameters[0] : null],
            in_array($name, ['Same', 'Different'], true) => isset($parameters[0]) ? $rule + ['other' => (string) $parameters[0]] : null,
            in_array($name, self::DATES, true) => isset($parameters[0]) && self::literalDate((string) $parameters[0]) ? $rule + ['value' => (string) $parameters[0]] : null,
            in_array($name, ['Regex', 'NotRegex'], true) => self::regex($rule, (string) ($parameters[0] ?? '')),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  list<mixed>  $parameters
     * @return array<string, mixed>|null
     */
    private static function sized(array $rule, string $name, array $parameters, string $type): ?array
    {
        $count = $name === 'Between' ? 2 : 1;
        $values = array_slice($parameters, 0, $count);

        if (count($values) !== $count || array_filter($values, 'is_numeric') !== $values) {
            return null;
        }

        $values = array_map(static fn (mixed $value): int|float => $value + 0, $values);

        return $rule + ['as' => $type, 'value' => $count === 1 ? $values[0] : $values];
    }

    /** `2026-01-01` is a date; `tomorrow` depends on the server's clock, `start_date` on another field. */
    private static function literalDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }

    /**
     * A PCRE as JavaScript writes it: the body between the delimiters and the flags both know.
     * Anything JavaScript reads otherwise — an atomic group, a possessive quantifier, `\A`, an
     * inline flag, `x` — leaves the rule to the server. The browser tries the result once more and
     * stays silent if it does not compile.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>|null
     */
    private static function regex(array $rule, string $pattern): ?array
    {
        if (strlen($pattern) < 2) {
            return null;
        }

        $open = $pattern[0];
        $close = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'][$open] ?? $open;
        $end = strrpos($pattern, $close);

        if (ctype_alnum($open) || $open === '\\' || $end === false || $end === 0) {
            return null;
        }

        $body = substr($pattern, 1, $end - 1);
        $flags = substr($pattern, $end + 1);

        if (preg_match('/^[imsuD]*$/', $flags) !== 1 || preg_match(self::FOREIGN, $body) === 1) {
            return null;
        }

        // Python's named groups, which PCRE also reads; JavaScript has only the short form.
        $body = (string) preg_replace('/\(\?P</', '(?<', $body);
        $flags = str_replace('D', '', $flags);

        if (str_contains($body, '\\p') || str_contains($body, '\\P')) {
            $flags .= str_contains($flags, 'u') ? '' : 'u';
        }

        return $rule + ['source' => $body, 'flags' => count_chars($flags, 3)];
    }

    /**
     * What the field prints besides the list: what helps the visitor before any check — the
     * picker's limits, the files the dialog offers, the keyboard of a number.
     *
     * @param  list<array{0: string, 1: list<mixed>}>  $own
     * @param  list<array{0: string, 1: list<mixed>}>  $items
     * @return array<string, string>
     */
    private static function attributes(array $own, array $items, string $kind): array
    {
        $attributes = [];
        $names = array_column($own, 0);
        $numeric = array_intersect($names, ['Numeric', 'Integer', 'Decimal']) !== [];

        foreach ($own as [$name, $parameters]) {
            $first = (string) ($parameters[0] ?? '');

            if ($numeric && in_array($name, ['Min', 'Max', 'Between'], true) && is_numeric($first)) {
                if ($name === 'Between') {
                    $attributes['min'] = $first;
                    $attributes['max'] = (string) ($parameters[1] ?? '');
                } else {
                    $attributes[strtolower($name)] = $first;
                }
            }

            if (in_array($name, self::DATES, true) && self::literalDate($first)) {
                $day = new \DateTimeImmutable($first);
                $attributes[str_starts_with($name, 'After') ? 'date-min' : 'date-max'] = match ($name) {
                    'After' => $day->modify('+1 day')->format('Y-m-d'),
                    'Before' => $day->modify('-1 day')->format('Y-m-d'),
                    default => $first,
                };
            }
        }

        if ($numeric) {
            $attributes['inputmode'] = in_array('Integer', $names, true) ? 'numeric' : 'decimal';
        }

        $accept = [];

        foreach (in_array($kind, ['file', 'files'], true) ? ($kind === 'files' ? $items : $own) : [] as [$name, $parameters]) {
            $accept = [...$accept, ...match ($name) {
                'Mimes', 'Extensions' => array_map(static fn (mixed $value): string => '.'.strtolower((string) $value), $parameters),
                'Image' => array_map(static fn (string $value): string => '.'.$value, in_array('allow_svg', $parameters, true) ? [...self::IMAGES, 'svg'] : self::IMAGES),
                'Mimetypes' => array_map('strval', $parameters),
                default => [],
            }];
        }

        if ($accept !== []) {
            $attributes['accept'] = implode(',', array_unique($accept));
        }

        return $attributes;
    }

    private static function snake(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    private function words(): RuleWords
    {
        if ($this->words === null) {
            $container = Container::getInstance();
            $this->words = new RuleWords($container->make(Translator::class), [], [], $this->messages, $this->attributes);
            $this->words->setContainer($container);
        }

        return $this->words;
    }
}
