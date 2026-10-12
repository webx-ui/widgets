<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use ReflectionClass;
use ReflectionMethod;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;
use WebxUi\Widgets\View\Components\Field;

/**
 * Spec §17.7: an agent told "make a sign-in form" builds it from the fields and the shell without
 * reading the sources — so everything it may reach for is named where it reads: every type of a
 * field, every way of `FormResponse`, every rule `FormRules` carries into the browser and every
 * event of the form, in the widgets' AGENTS.md or the guide «Forms».
 *
 * The lists are read from the code, not written here: a type, a rule or an event added later and
 * not documented fails this test. Runs only in the monorepo, where the guide is.
 */
final class FormsDocsCoverageTest extends PlainTestCase
{
    private static function docs(): string
    {
        $guide = dirname(__DIR__, 4).'/apps/docs/guide/forms.md';

        if (! is_file($guide)) {
            self::markTestSkipped('Not in the monorepo: the guide «Forms» is not here.');
        }

        return (string) file_get_contents(dirname(__DIR__).'/AGENTS.md')."\n".(string) file_get_contents($guide);
    }

    /** Named as code: inside a span of backticks, or as `type="…"` in an example. */
    private static function named(string $docs, string $word): bool
    {
        $word = preg_quote($word, '/');

        return preg_match('/`[^`\n]*(?<![a-z_-])'.$word.'(?![a-z_-])[^`\n]*`|type="'.$word.'"/', $docs) === 1;
    }

    #[Test]
    public function every_type_of_a_field_is_named(): void
    {
        $docs = self::docs();
        $missing = array_values(array_filter(Field::TYPES, static fn (string $type): bool => ! self::named($docs, $type)));

        $this->assertSame([], $missing, 'Types of <x-webx-field> named nowhere in the docs.');
    }

    #[Test]
    public function every_way_of_form_response_is_named(): void
    {
        $docs = self::docs();
        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            array_filter((new ReflectionClass(FormResponse::class))->getMethods(ReflectionMethod::IS_PUBLIC), static fn (ReflectionMethod $method): bool => $method->isStatic()),
        );

        $this->assertNotSame([], $methods);
        $missing = array_values(array_filter($methods, static fn (string $method): bool => ! str_contains($docs, 'FormResponse::'.$method.'(')));

        $this->assertSame([], $missing, 'Constructors of FormResponse named nowhere in the docs.');
    }

    #[Test]
    public function every_rule_the_browser_checks_is_named(): void
    {
        $docs = self::docs();
        $missing = array_values(array_filter(self::rules(), static fn (string $rule): bool => ! self::named($docs, $rule)));

        $this->assertSame([], $missing, 'Rules FormRules carries to the browser named nowhere in the docs.');
    }

    #[Test]
    public function every_event_of_the_form_is_named(): void
    {
        $docs = self::docs();
        preg_match_all("/emit\\('([a-z]+)'/", (string) file_get_contents(dirname(__DIR__).'/resources/js/form.js'), $events);

        $this->assertNotSame([], $events[1]);
        $missing = array_values(array_filter(array_unique($events[1]), static fn (string $event): bool => ! str_contains($docs, 'webx:form:'.$event)));

        $this->assertSame([], $missing, 'Events of form.js named nowhere in the docs.');
    }

    /** The suggestions while typing (W6.5): their events and the marks a site's HTML carries. */
    #[Test]
    public function every_event_and_mark_of_the_suggestions_is_named(): void
    {
        $docs = self::docs();
        $script = (string) file_get_contents(dirname(__DIR__).'/resources/js/suggest.js');
        preg_match_all("/emit\\('([a-z]+)'/", $script, $events);
        preg_match_all('/\[(data-webx-[a-z-]+)\]/', $script, $marks);

        $this->assertNotSame([], $events[1]);
        $this->assertContains('data-webx-option', $marks[1]);
        $missing = [
            ...array_map(static fn (string $event): string => 'webx:suggest:'.$event, array_unique($events[1])),
            ...array_unique($marks[1]),
            '<x-webx-suggest',
        ];

        $this->assertSame([], array_values(array_filter($missing, static fn (string $word): bool => ! str_contains($docs, $word))), 'Events and marks of suggest.js named nowhere in the docs.');
    }

    /**
     * What FormRules understands, as the validator spells it: its lists of rules and every rule its
     * `check()` matches by name.
     *
     * @return list<string>
     */
    private static function rules(): array
    {
        $class = new ReflectionClass(FormRules::class);
        $names = [];

        foreach (['MARKS', 'SIMPLE', 'SIZES', 'DATES'] as $list) {
            $names = [...$names, ...(array) $class->getConstant($list)];
        }

        $check = $class->getMethod('check');
        $source = implode('', array_slice((array) file((string) $class->getFileName()), $check->getStartLine() - 1, $check->getEndLine() - $check->getStartLine() + 1));
        preg_match_all("/'([A-Z][A-Za-z]+)'/", $source, $literal);
        $names = array_unique([...$names, ...$literal[1]]);

        return array_values(array_map(static fn (string $name): string => strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name)), $names));
    }
}
