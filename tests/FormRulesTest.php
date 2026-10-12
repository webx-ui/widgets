<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Forms\FormRules;

/**
 * Spec §17.5, W6.3: the server's rules as the browser checks them — every rule of the list
 * translated, with the very line the validator writes when it fails, in the page's language;
 * every other rule silent; the fields of a form asking the form for their own part.
 */
final class FormRulesTest extends TestCase
{
    /**
     * A rule, the kind of field, a value that fails it — and the check the browser gets.
     *
     * @return iterable<string, array{0: array<string, mixed>, 1: string, 2: array<string, mixed>, 3: array<string, mixed>}>
     */
    public static function rules(): iterable
    {
        yield 'required' => [['f' => 'required'], 'one', ['f' => null], ['rule' => 'required']];
        yield 'accepted' => [['f' => 'accepted'], 'one', ['f' => 'no'], ['rule' => 'accepted']];
        yield 'email' => [['f' => 'email'], 'one', ['f' => 'nope'], ['rule' => 'email']];
        yield 'url' => [['f' => 'url'], 'one', ['f' => 'nope'], ['rule' => 'url']];
        yield 'numeric' => [['f' => 'numeric'], 'one', ['f' => 'x'], ['rule' => 'numeric']];
        yield 'integer' => [['f' => 'integer'], 'one', ['f' => '1.5'], ['rule' => 'integer']];
        yield 'min of a string' => [['f' => 'min:3'], 'one', ['f' => 'ab'], ['rule' => 'min', 'as' => 'string', 'value' => 3]];
        yield 'max of a number' => [['f' => 'numeric|max:10'], 'one', ['f' => '11'], ['rule' => 'max', 'as' => 'numeric', 'value' => 10]];
        yield 'between of a string' => [['f' => 'between:2,4'], 'one', ['f' => 'abcde'], ['rule' => 'between', 'as' => 'string', 'value' => [2, 4]]];
        yield 'size of a number' => [['f' => 'integer|size:5'], 'one', ['f' => '4'], ['rule' => 'size', 'as' => 'numeric', 'value' => 5]];
        yield 'min of a group' => [['f' => 'array|min:2'], 'many', ['f' => ['a']], ['rule' => 'min', 'as' => 'array', 'value' => 2]];
        yield 'max of a file' => [['f' => 'max:100'], 'file', ['f' => UploadedFile::fake()->create('big.pdf', 200)], ['rule' => 'max', 'as' => 'file', 'value' => 100]];
        yield 'regex' => [['f' => 'regex:/^\d{3}$/'], 'one', ['f' => '12'], ['rule' => 'regex', 'source' => '^\d{3}$', 'flags' => '']];
        yield 'regex with its flags' => [['f' => 'regex:/^ab$/iu'], 'one', ['f' => 'x'], ['rule' => 'regex', 'source' => '^ab$', 'flags' => 'iu']];
        yield 'not_regex' => [['f' => 'not_regex:/admin/'], 'one', ['f' => 'admin'], ['rule' => 'not_regex', 'source' => 'admin', 'flags' => '']];
        yield 'in' => [['f' => 'in:a,b'], 'one', ['f' => 'c'], ['rule' => 'in', 'values' => ['a', 'b']]];
        yield 'Rule::in' => [['f' => [Rule::in(['a', 'b'])]], 'one', ['f' => 'c'], ['rule' => 'in', 'values' => ['a', 'b']]];
        yield 'mimes' => [['f' => 'mimes:pdf,docx'], 'file', ['f' => UploadedFile::fake()->create('a.txt', 1, 'text/plain')], ['rule' => 'mimes', 'values' => ['pdf', 'docx']]];
        yield 'extensions' => [['f' => 'extensions:pdf'], 'file', ['f' => UploadedFile::fake()->create('a.txt', 1)], ['rule' => 'extensions', 'values' => ['pdf']]];
        yield 'mimetypes' => [['f' => 'mimetypes:application/pdf'], 'file', ['f' => UploadedFile::fake()->create('a.txt', 1, 'text/plain')], ['rule' => 'mimetypes', 'values' => ['application/pdf']]];
        yield 'confirmed' => [['f' => 'confirmed'], 'one', ['f' => 'a', 'f_confirmation' => 'b'], ['rule' => 'confirmed', 'other' => null]];
        yield 'same' => [['f' => 'same:g'], 'one', ['f' => 'a', 'g' => 'b'], ['rule' => 'same', 'other' => 'g']];
        yield 'different' => [['f' => 'different:g'], 'one', ['f' => 'a', 'g' => 'a'], ['rule' => 'different', 'other' => 'g']];
        yield 'date' => [['f' => 'date'], 'one', ['f' => '2026-02-30'], ['rule' => 'date']];
        yield 'after a date' => [['f' => 'after:2026-01-01'], 'one', ['f' => '2026-01-01'], ['rule' => 'after', 'value' => '2026-01-01']];
        yield 'before a date' => [['f' => 'before:2026-01-01'], 'one', ['f' => '2026-01-01'], ['rule' => 'before', 'value' => '2026-01-01']];
        yield 'after or on a date' => [['f' => 'after_or_equal:2026-01-01'], 'one', ['f' => '2025-12-31'], ['rule' => 'after_or_equal', 'value' => '2026-01-01']];
        yield 'before or on a date' => [['f' => 'before_or_equal:2026-01-01'], 'one', ['f' => '2026-01-02'], ['rule' => 'before_or_equal', 'value' => '2026-01-01']];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $expected
     */
    #[Test]
    #[DataProvider('rules')]
    public function every_rule_of_the_list_comes_with_the_line_the_validator_writes(array $rules, string $kind, array $data, array $expected): void
    {
        $checks = FormRules::for($rules, ['f' => 'the field'])->field('f', $kind)['checks'];
        // The rule of the case, last: a size rule comes after the numeric one it measures by.
        $check = end($checks);
        $this->assertIsArray($check);

        $message = $check['message'];
        unset($check['message']);
        $this->assertSame($expected, $check);

        // The server, with a value that fails the rule: the same words.
        $said = Validator::make($data, $rules, [], ['f' => 'the field'])->errors()->first('f');
        $this->assertNotSame('', $said);
        $this->assertSame($said, $message);
    }

    #[Test]
    public function what_only_the_server_can_check_is_silence_not_an_error(): void
    {
        $rules = FormRules::for([
            'email' => ['required', 'string', 'unique:users,email', Rule::exists('users', 'email'), static fn (): null => null, 'required_if:other,1', 'starts_with:a', 'ip'],
            // PCRE that JavaScript reads otherwise: an atomic group, a possessive quantifier, \A, an inline flag, x.
            'code' => ['regex:/(?>ab)c/', 'regex:/a++b/', 'regex:/\Aab/', 'regex:/(?i)ab/', 'regex:/a b/x', 'after:tomorrow', 'after:start', 'before:2026-13-45'],
        ]);

        $email = $rules->field('email');
        $this->assertSame(['required'], array_column($email['checks'], 'rule'));
        $this->assertSame([], $rules->field('code')['checks']);
        // A field the rules do not mention asks nothing.
        $this->assertSame(['required' => false, 'attributes' => [], 'checks' => []], $rules->field('other'));
    }

    #[Test]
    public function bail_and_nullable_ride_along_without_words(): void
    {
        $checks = FormRules::for(['f' => 'bail|nullable|email'])->field('f')['checks'];

        $this->assertSame(['bail', 'nullable', 'email'], array_column($checks, 'rule'));
        $this->assertArrayNotHasKey('message', $checks[0]);
        $this->assertArrayNotHasKey('message', $checks[1]);
    }

    #[Test]
    public function a_python_named_group_is_written_the_javascript_way(): void
    {
        $check = FormRules::for(['f' => 'regex:#^(?P<area>\d{3})-\p{L}+$#'])->field('f')['checks'][0];

        $this->assertSame('^(?<area>\d{3})-\p{L}+$', $check['source']);
        // \p wants the u flag in JavaScript; PHP reads it without.
        $this->assertSame('u', $check['flags']);
    }

    #[Test]
    public function the_words_are_the_validators_in_the_language_of_the_page(): void
    {
        app('translator')->addLines([
            'validation.required' => 'Поле :attribute обязательно.',
            'validation.min.string' => 'Поле :attribute — не короче :min символов.',
            'validation.attributes.name' => 'имя',
        ], 'ru');
        App::setLocale('ru');

        $checks = FormRules::for(['name' => 'required|min:2'])->field('name')['checks'];

        $this->assertSame('Поле имя обязательно.', $checks[0]['message']);
        $this->assertSame('Поле имя — не короче 2 символов.', $checks[1]['message']);
        $this->assertSame(Validator::make(['name' => 'a'], ['name' => 'min:2'])->errors()->first('name'), $checks[1]['message']);
    }

    #[Test]
    public function the_forms_own_messages_and_names_are_the_ones_said(): void
    {
        $checks = FormRules::for(['email' => 'required|email'], ['email' => 'your e-mail'], ['email.email' => 'That is no address, :attribute.'])->field('email')['checks'];

        $this->assertSame('The your e-mail field is required.', $checks[0]['message']);
        $this->assertSame('That is no address, your e-mail.', $checks[1]['message']);
    }

    #[Test]
    public function the_items_of_a_group_or_of_several_files_are_checked_one_by_one(): void
    {
        $rules = ['photos' => 'array|max:3', 'photos.*' => 'image|max:2048', 'lines.*.qty' => 'integer|min:1'];
        $field = FormRules::for($rules, ['photos.*' => 'photo'])->field('photos', 'files');

        // `array` itself is the server's: a field of several sends an array anyway.
        $this->assertSame([['max', false], ['mimes', true], ['max', true]], array_map(static fn (array $check): array => [$check['rule'], $check['each'] ?? false], $field['checks']));
        $this->assertSame('array', $field['checks'][0]['as']);
        $this->assertSame('file', $field['checks'][2]['as']);
        $this->assertSame('photos.0', $field['checks'][1]['item']);
        // The picker offers what `image` lets through.
        $this->assertStringContainsString('.jpg,.jpeg,.png', $field['attributes']['accept']);

        // The server names the item the same way.
        $said = Validator::make(['photos' => [UploadedFile::fake()->create('a.pdf', 3000)]], $rules, [], ['photos.*' => 'photo'])->errors()->get('photos.0');
        $this->assertSame([$field['checks'][1]['message'], $field['checks'][2]['message']], $said);

        // A field of a row: `lines.*.qty` is the rule of `lines.0.qty`, said with its own name.
        $qty = FormRules::for($rules)->field('lines.0.qty');
        $this->assertSame(['integer', 'min'], array_column($qty['checks'], 'rule'));
        $this->assertSame(Validator::make(['lines' => [['qty' => '0']]], $rules)->errors()->first('lines.0.qty'), $qty['checks'][1]['message']);
    }

    #[Test]
    public function what_helps_before_any_check_becomes_an_attribute(): void
    {
        $rules = FormRules::for([
            'age' => 'nullable|integer|between:18,120',
            'price' => 'numeric|min:0',
            'day' => 'date|after:2026-01-01|before_or_equal:2026-12-31',
            'cv' => 'required|mimes:pdf,docx',
            'scan' => 'mimetypes:image/*,application/pdf',
            'agree' => 'accepted',
        ]);

        $this->assertSame(['min' => '18', 'max' => '120', 'inputmode' => 'numeric'], $rules->field('age')['attributes']);
        $this->assertSame(['min' => '0', 'inputmode' => 'decimal'], $rules->field('price')['attributes']);
        $this->assertSame(['date-min' => '2026-01-02', 'date-max' => '2026-12-31'], $rules->field('day')['attributes']);
        $this->assertSame(['accept' => '.pdf,.docx'], $rules->field('cv', 'file')['attributes']);
        $this->assertSame(['accept' => 'image/*,application/pdf'], $rules->field('scan', 'file')['attributes']);
        $this->assertTrue($rules->field('cv', 'file')['required']);
        $this->assertTrue($rules->field('agree')['required']);
        $this->assertFalse($rules->field('age')['required']);
    }

    #[Test]
    public function the_fields_of_a_form_take_their_part_of_its_rules(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-webx-form action="/checkout" :rules="['contact.email' => 'required|email', 'contact.phone' => 'required', 'qty' => 'integer|min:1|max:9', 'when' => 'after:2026-01-01', 'files' => 'max:2', 'files.*' => 'mimes:pdf']">
                <x-webx-field name="contact[email]" type="email" label="Email" />
                <x-webx-field name="contact.phone" type="tel" label="Phone" mask />
                <x-webx-field name="qty" type="number" label="How many" />
                <x-webx-field name="when" type="date" label="When" />
                <x-webx-field name="files" type="file" label="Files" multiple />
                <x-webx-field name="note" type="textarea" label="Note" />
            </x-webx-form>
            <x-webx-field name="contact.email" type="email" label="Outside" />
            BLADE);

        // The rules on the field, the marker of a required field, required on the control.
        $this->assertMatchesRegularExpression('~<div class="webx-field webx-field--email is-required" data-webx-field="contact.email" data-webx-rules="\[\{&quot;rule&quot;:&quot;required&quot;,&quot;message&quot;:&quot;The contact.email field is required.&quot;\},\{&quot;rule&quot;:&quot;email&quot;~', $html);
        $this->assertMatchesRegularExpression('~<input type="email"[^>]*name="contact\[email\]"[^>]*required~', $html);
        $this->assertMatchesRegularExpression('~<input type="tel"[^>]*data-webx-mask="phone"~', $html);
        $this->assertMatchesRegularExpression('~<input type="number"[^>]*min="1"[^>]*max="9"~', $html);
        $this->assertMatchesRegularExpression('~<input type="date"[^>]*min="2026-01-02"~', $html);
        $this->assertMatchesRegularExpression('~<input type="file"[^>]*multiple[^>]*accept=".pdf"~', $html);
        // A field the rules do not name, and a field outside the form, get nothing.
        $this->assertDoesNotMatchRegularExpression('~data-webx-field="note" data-webx-rules~', $html);
        $this->assertSame(1, substr_count($html, 'data-webx-field="contact.email" data-webx-rules'));

        $this->assertContains('mask', Widgets::claimed());
        $this->assertContains('files', Widgets::claimed());
    }

    #[Test]
    public function a_field_takes_its_own_rules_and_the_page_has_the_last_word(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-webx-field name="nick" label="Nick" :rules="['required', 'regex:/^[a-z]+$/']" />
            <x-webx-field name="age" type="number" label="Age" :rules="\WebxUi\Widgets\Forms\FormRules::for(['age' => 'integer|min:18'])" min="21" />
            <x-webx-field name="tel" type="tel" label="Phone" />
            BLADE);

        $this->assertMatchesRegularExpression('~data-webx-field="nick" data-webx-rules="[^"]*regex~', $html);
        // The page's own min stands over the rules'.
        $this->assertMatchesRegularExpression('~<input type="number"[^>]*min="21"~', $html);
        // No mask unless asked: a phone field of the page may have its own rules for the digits.
        $this->assertStringNotContainsString('data-webx-mask', $html);
        $this->assertNotContains('mask', Widgets::claimed());
    }

    #[Test]
    public function the_file_field_has_its_list_and_the_drop_hint_in_place(): void
    {
        $html = Blade::render('<x-webx-field name="cv" type="file" label="CV" />');

        $this->assertStringContainsString('data-webx-files="{&quot;remove&quot;:&quot;Remove&quot;}"', $html);
        $this->assertStringContainsString('<span class="webx-field__drop" hidden>or drop a file here</span>', $html);
        $this->assertStringContainsString('<ul class="webx-field__files" hidden></ul>', $html);

        App::setLocale('pl');
        $several = Blade::render('<x-webx-field name="docs" type="file" label="Docs" multiple remove-label="Precz" />');
        $this->assertStringContainsString('lub upuść pliki tutaj', $several);
        $this->assertStringContainsString('{&quot;remove&quot;:&quot;Precz&quot;}', $several);
    }
}
