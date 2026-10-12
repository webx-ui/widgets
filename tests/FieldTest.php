<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\View\Components\Field;

/**
 * Spec §17.2, W6.1: the fields of a form — one markup for fifteen types, Laravel's `old()` and
 * `$errors` by the validator's name of the field, the wiring a screen reader follows, the hints a
 * keyboard and an autofill go by, and a form sent without JavaScript coming back with each error
 * under its own field.
 */
final class FieldTest extends TestCase
{
    /** Every type with what it needs to stand: `[type, extra props]`. */
    private const array EVERY_TYPE = [
        'text' => '',
        'email' => '',
        'tel' => '',
        'url' => '',
        'password' => '',
        'number' => '',
        'textarea' => '',
        'select' => ':choices="[\'a\' => \'Alpha\', \'b\' => \'Beta\']"',
        'radio' => ':choices="[\'a\' => \'Alpha\', \'b\' => \'Beta\']"',
        'checkbox' => ':choices="[\'a\' => \'Alpha\', \'b\' => \'Beta\']"',
        'tick' => '',
        'consent' => '',
        'date' => '',
        'file' => '',
    ];

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The web group encrypts its cookies: the session a form comes back through needs a key.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(static function (Router $router): void {
            $router->get('/order', static fn (): string => Blade::render(<<<'BLADE'
                <x-layout>
                    <form method="post" action="/order">
                        <x-webx-field name="fields.email" type="email" label="Email" required />
                        <x-webx-field name="items[0][qty]" type="number" label="How many" />
                        <x-webx-field name="delivery" type="radio" label="Delivery" :choices="['post' => 'Post', 'pickup' => 'Pick-up']" required />
                        <x-webx-field name="extras" type="checkbox" label="Extras" :choices="['wrap' => 'Gift wrap', 'card' => 'A card']" />
                        <x-webx-field name="terms" type="consent" label="I agree" required />
                    </form>
                </x-layout>
                BLADE));
            $router->post('/order', static function (Request $request): string {
                $request->validate([
                    'fields.email' => ['required', 'email'],
                    'items.0.qty' => ['integer', 'min:1'],
                    'delivery' => ['required'],
                    'extras' => ['array', 'min:2'],
                    'extras.*' => ['in:wrap,card'],
                    'terms' => ['accepted'],
                ]);

                return 'sent';
            });
        });
    }

    private static function field(string $type, string $props = ''): string
    {
        $tag = $type === 'tick' ? 'checkbox' : $type;

        return Blade::render("<x-webx-field type=\"{$tag}\" name=\"answer\" label=\"Your answer\" {$props} />");
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, list<string>>  $errors
     */
    private function returned(array $old, array $errors): void
    {
        $this->app['session']->start();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $this->app['session.store']->flashInput($old);
        $this->app['view']->share('errors', (new ViewErrorBag)->put('default', new MessageBag($errors)));
    }

    /** @return iterable<string, array{string, string}> */
    public static function types(): iterable
    {
        foreach (self::EVERY_TYPE as $type => $props) {
            yield $type => [$type, $props];
        }
    }

    #[Test]
    #[DataProvider('types')]
    public function every_type_is_one_markup_with_its_label_hint_and_error(string $type, string $props): void
    {
        $html = self::field($type, $props.' hint="Short and true"');
        $grouped = in_array($type, ['radio', 'checkbox'], true);
        $root = $type === 'tick' ? 'checkbox' : $type;

        $this->assertMatchesRegularExpression('~^<'.($grouped ? 'fieldset' : 'div').'[^>]* class="webx-field webx-field--'.$root.'"~', trim($html));
        $this->assertStringContainsString('<p class="webx-field__hint" id="field-answer-hint">Short and true</p>', $html);
        // The error's place is there before there is an error, so the form's script writes into it.
        $this->assertStringContainsString('<p class="webx-field__error" id="field-answer-error"  hidden ></p>', $html);
        $this->assertStringContainsString('aria-describedby="field-answer-hint field-answer-error"', $html);
        $this->assertStringNotContainsString('aria-invalid', $html);

        if ($grouped) {
            $this->assertStringContainsString('<legend class="webx-field__label webx-field__label--legend">Your answer</legend>', $html);
        } elseif (in_array($type, ['tick', 'consent'], true)) {
            $this->assertMatchesRegularExpression('~<label class="webx-field__choice webx-field__choice--(single|consent)" for="field-answer">\s*<input type="checkbox" class="webx-field__check" id="field-answer" name="answer" value="1"~', $html);
            $this->assertStringContainsString('<span class="webx-field__choice-label">Your answer</span>', $html);
        } else {
            $this->assertStringContainsString('<label class="webx-field__label" for="field-answer">Your answer</label>', $html);
            $this->assertMatchesRegularExpression('~<(input|select|textarea)[^>]* class="webx-field__control[^"]*" id="field-answer" name="answer"~', $html);
        }

        $this->assertSame($type === 'file' ? ['field', 'files'] : ['field'], Widgets::claimed());
    }

    #[Test]
    public function a_hidden_field_is_its_input_alone(): void
    {
        $html = trim(Blade::render('<x-webx-field type="hidden" name="page.id" value="42" data-x="y" />'));

        $this->assertSame('<input type="hidden" id="field-page-id" name="page[id]" value="42" data-x="y">', $html);
    }

    /**
     * A dotted name is written with brackets, the error found by dots — and a bracketed name is the
     * same field. The value comes back from `old()`; a password never does.
     *
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function returnedTypes(): iterable
    {
        yield 'text' => ['text', '', 'value="Ann"', 'Ann'];
        yield 'email' => ['email', '', 'value="ann@example.com"', 'ann@example.com'];
        yield 'tel' => ['tel', '', 'value="+48 600 100 200"', '+48 600 100 200'];
        yield 'url' => ['url', '', 'value="https://example.com"', 'https://example.com'];
        yield 'number' => ['number', '', 'value="3"', '3'];
        yield 'date' => ['date', '', 'value="2026-10-12"', '2026-10-12'];
        yield 'textarea' => ['textarea', '', '>Two
lines</textarea>', "Two\nlines"];
        yield 'select' => ['select', ':choices="[\'a\' => \'Alpha\', \'b\' => \'Beta\']"', '<option value="b" selected >Beta</option>', 'b'];
        yield 'radio' => ['radio', ':choices="[\'a\' => \'Alpha\', \'b\' => \'Beta\']"', 'value="b" checked', 'b'];
        yield 'hidden' => ['hidden', '', 'value="7"', '7'];
    }

    #[Test]
    #[DataProvider('returnedTypes')]
    public function a_form_that_came_back_shows_what_was_sent_and_the_error_under_its_field(string $type, string $props, string $expected, string $sent): void
    {
        $this->returned(['fields' => ['answer' => $sent]], ['fields.answer' => ['Not this one.'], 'fields.other' => ['Not mine.']]);

        foreach (['fields.answer', 'fields[answer]'] as $name) {
            $html = Blade::render("<x-webx-field type=\"{$type}\" name=\"{$name}\" label=\"Answer\" value=\"default\" {$props} />");

            $this->assertStringContainsString('name="fields[answer]"', $html);
            $this->assertStringContainsString($expected, $html);
            $this->assertStringNotContainsString('default', $html);
            $this->assertStringNotContainsString('Not mine.', $html);

            if ($type === 'hidden') {
                $this->assertStringNotContainsString('Not this one.', $html, 'a hidden field has nowhere to show an error');

                continue;
            }

            $this->assertStringContainsString('<p class="webx-field__error" id="field-fields-answer'.($name === 'fields.answer' ? '' : '-2').'-error" >Not this one.</p>', $html);
            $this->assertStringContainsString('is-invalid', $html);
            $this->assertStringContainsString('aria-invalid="true"', $html);
        }
    }

    #[Test]
    public function a_password_and_a_file_are_never_printed_back(): void
    {
        $this->returned(['secret' => 'hunter2', 'upload' => 'x.pdf'], ['secret' => ['Too short.']]);

        $password = Blade::render('<x-webx-field type="password" name="secret" label="Password" value="hunter2" />');
        $file = Blade::render('<x-webx-field type="file" name="upload" label="CV" />');

        $this->assertStringNotContainsString('hunter2', $password);
        $this->assertStringContainsString('Too short.', $password);
        $this->assertStringNotContainsString('value=', $file);
    }

    #[Test]
    public function a_group_sends_an_array_and_ticks_what_came_back_and_nothing_more(): void
    {
        $choices = ['wrap' => 'Gift wrap', 'card' => 'A card', 'bow' => 'A bow'];

        // Not returned: the page's default.
        $fresh = Blade::render('<x-webx-field type="checkbox" name="order.extras" label="Extras" :choices="$choices" :value="[\'wrap\', \'bow\']" required />', ['choices' => $choices]);
        $this->assertSame(3, substr_count($fresh, 'name="order[extras][]"'));
        $this->assertSame(2, substr_count($fresh, '" checked '));
        // `required` on a box would demand that box: the group's rule is the server's.
        $this->assertStringNotContainsString(' required', $fresh);
        $this->assertStringContainsString('<legend class="webx-field__label webx-field__label--legend">Extras<span class="webx-field__required" aria-hidden="true">*</span></legend>', $fresh);

        // Returned with one ticked: one, and the error of an item lands on the group.
        $this->returned(['order' => ['extras' => ['card']]], ['order.extras.0' => ['The extra is not offered.']]);
        $back = Blade::render('<x-webx-field type="checkbox" name="order.extras" label="Extras" :choices="$choices" :value="[\'wrap\', \'bow\']" />', ['choices' => $choices]);

        $this->assertSame(1, substr_count($back, '" checked '));
        $this->assertStringContainsString('value="card" checked ', $back);
        $this->assertMatchesRegularExpression('~<fieldset aria-describedby="field-order-extras-2-error" class="webx-field webx-field--checkbox is-invalid" data-webx-field="order.extras">~', $back);
        $this->assertSame(3, substr_count($back, 'aria-invalid="true"'), 'each box says the group is wrong');
        $this->assertStringContainsString('The extra is not offered.', $back);
    }

    #[Test]
    public function one_tick_comes_back_as_it_was_left_not_as_the_page_set_it(): void
    {
        $fresh = Blade::render('<x-webx-field type="checkbox" name="news" label="Send me news" :checked="true" />');
        $this->assertStringContainsString('value="1" checked', $fresh);

        // The visitor unticked it and the form came back: unticked, whatever the default.
        $this->returned(['email' => 'a@b.c'], ['terms' => ['Accept the terms.']]);
        $back = Blade::render('<x-webx-field type="checkbox" name="news" label="Send me news" :checked="true" />');
        $this->assertStringNotContainsString(' checked', $back);

        $consent = Blade::render('<x-webx-field type="consent" name="terms" required>I agree to <a href="/terms">the terms</a></x-webx-field>');
        $this->assertStringContainsString('<span class="webx-field__choice-label">I agree to <a href="/terms">the terms</a><span class="webx-field__required" aria-hidden="true">*</span></span>', $consent);
        $this->assertStringContainsString('aria-describedby="field-terms-error" aria-invalid="true"', $consent);
        $this->assertStringContainsString(' required="required"', $consent);
    }

    #[Test]
    public function radios_are_a_fieldset_whose_every_radio_is_required_and_says_when_it_is_wrong(): void
    {
        $this->returned([], ['plan' => ['Choose a plan.']]);

        $html = Blade::render('<x-webx-field type="radio" name="plan" label="Plan" :choices="[[\'value\' => \'s\', \'label\' => \'Small\'], [\'value\' => \'l\', \'label\' => \'Large\', \'disabled\' => true]]" required inline />');

        $this->assertStringContainsString('<fieldset aria-describedby="field-plan-error" class="webx-field webx-field--radio is-invalid is-required" data-webx-field="plan">', $html);
        $this->assertSame(2, substr_count($html, ' required '));
        $this->assertSame(2, substr_count($html, 'aria-invalid="true"'));
        $this->assertSame(1, substr_count($html, ' disabled '));
        $this->assertSame(2, substr_count($html, 'class="webx-field__choice webx-field__choice--inline"'));
        $this->assertStringContainsString('<label class="webx-field__choice webx-field__choice--inline" for="field-plan-1">', $html);
    }

    #[Test]
    public function a_named_bag_and_a_given_error_are_read_too(): void
    {
        $this->app['view']->share('errors', (new ViewErrorBag)->put('login', new MessageBag(['email' => ['These credentials do not match.']])));

        $this->assertStringNotContainsString('do not match', Blade::render('<x-webx-field type="email" name="email" label="Email" />'));
        $this->assertStringContainsString('These credentials do not match.', Blade::render('<x-webx-field type="email" name="email" label="Email" bag="login" />'));
        $this->assertStringContainsString('>Said by the page.</p>', Blade::render('<x-webx-field name="x" label="X" error="Said by the page." />'));
    }

    /** @return iterable<string, array{string, string, list<string>, list<string>}> */
    public static function defaults(): iterable
    {
        yield 'text' => ['text', '', [], ['autocomplete=', 'inputmode=', 'enterkeyhint=', 'spellcheck=']];
        yield 'email' => ['email', '', ['autocomplete="email"', 'inputmode="email"', 'spellcheck="false"', 'autocapitalize="none"'], ['enterkeyhint=']];
        yield 'tel' => ['tel', '', ['autocomplete="tel"', 'inputmode="tel"'], ['spellcheck=']];
        yield 'url' => ['url', '', ['autocomplete="url"', 'inputmode="url"', 'autocapitalize="none"'], []];
        yield 'password' => ['password', '', ['autocomplete="current-password"', 'spellcheck="false"'], ['inputmode=']];
        yield 'new password' => ['password', 'autocomplete="new-password"', ['autocomplete="new-password"'], ['current-password']];
        yield 'number' => ['number', 'min="1" max="9"', ['inputmode="numeric"', 'min="1"', 'max="9"'], ['autocomplete=']];
        yield 'number with a fraction' => ['number', 'step="0.01"', ['inputmode="decimal"', 'step="0.01"'], []];
        yield 'number below zero' => ['number', ':inputmode="false"', [], ['inputmode=']];
        yield 'email, no autofill' => ['email', 'autocomplete="off" enterkeyhint="send"', ['autocomplete="off"', 'enterkeyhint="send"'], []];
        yield 'email, none at all' => ['email', ':autocomplete="false"', ['inputmode="email"'], ['autocomplete=']];
        yield 'name' => ['text', 'autocomplete="given-name" maxlength="80"', ['autocomplete="given-name"', 'maxlength="80"'], []];
        yield 'date' => ['date', 'min="2026-01-01"', ['min="2026-01-01"'], ['inputmode=', 'autocomplete=']];
        yield 'file' => ['file', 'accept=".pdf,.jpg" multiple', ['accept=".pdf,.jpg"', 'multiple="multiple"', 'name="answer[]"'], ['autocomplete=', 'inputmode=']];
    }

    /**
     * @param  list<string>  $present
     * @param  list<string>  $absent
     */
    #[Test]
    #[DataProvider('defaults')]
    public function the_hints_for_a_keyboard_and_an_autofill_have_a_default_per_type(string $type, string $props, array $present, array $absent): void
    {
        $html = self::field($type, $props);

        foreach ($present as $attribute) {
            $this->assertStringContainsString($attribute, $html);
        }

        foreach ($absent as $attribute) {
            $this->assertStringNotContainsString($attribute, $html);
        }
    }

    #[Test]
    public function the_words_are_the_pages_language_and_each_one_a_prop(): void
    {
        app()->setLocale('de');

        $password = Blade::render('<x-webx-field type="password" name="p" label="Passwort" />');
        $this->assertStringContainsString('aria-label="Passwort anzeigen" data-webx-password hidden >Anzeigen</button>', $password);
        $this->assertStringContainsString('<option value="">Bitte wählen…</option>', Blade::render('<x-webx-field type="select" name="s" label="S" :choices="[1 => \'Eins\']" />'));
        $this->assertStringContainsString('(optional)</span>', Blade::render('<x-webx-field name="n" label="Name" optional />'));
        $file = Blade::render('<x-webx-field type="file" name="f" label="F" multiple />');
        $this->assertStringContainsString('>Dateien auswählen</span>', $file);
        $this->assertStringContainsString('{"none":"Keine Datei ausgewählt","many":"Ausgewählte Dateien: :count"}', html_entity_decode($file));

        app()->setLocale('ru');
        $this->assertStringContainsString('>Выбрать файл</span>', Blade::render('<x-webx-field type="file" name="f" label="F" />'));

        $own = Blade::render('<x-webx-field type="password" name="p" label="P" show-label="Peek" show-password-label="Peek at it" />');
        $this->assertStringContainsString('aria-label="Peek at it" data-webx-password hidden >Peek</button>', $own);
        $this->assertStringContainsString('<option value="">Pick one</option>', Blade::render('<x-webx-field type="select" name="s" label="S" :choices="[1 => \'One\']" placeholder="Pick one" />'));
        $this->assertStringContainsString('(if you like)</span>', Blade::render('<x-webx-field name="n" label="Name" optional optional-label="if you like" />'));
    }

    #[Test]
    public function the_state_is_on_the_field_and_on_its_control(): void
    {
        $html = Blade::render('<x-webx-field name="code" label="Code" required disabled readonly class="b-form__code" data-mask="code" />');

        $this->assertStringContainsString('<div class="webx-field webx-field--text is-required is-disabled b-form__code" data-webx-field="code">', $html);
        $this->assertStringContainsString('<label class="webx-field__label" for="field-code">Code<span class="webx-field__required" aria-hidden="true">*</span></label>', $html);
        $this->assertMatchesRegularExpression('~<input type="text" class="webx-field__control" id="field-code" name="code" value="" required="required" disabled="disabled" readonly="readonly"[^>]* data-mask="code">~', $html);
    }

    #[Test]
    public function the_same_name_twice_on_a_page_keeps_two_ids_and_an_id_can_be_given(): void
    {
        $html = Blade::render('<x-webx-field type="email" name="email" label="A" /><x-webx-field type="email" name="email" label="B" /><x-webx-field type="email" name="email" label="C" id="login-email" />');

        $this->assertStringContainsString('for="field-email"', $html);
        $this->assertStringContainsString('for="field-email-2"', $html);
        $this->assertStringContainsString('for="login-email"', $html);
        $this->assertStringContainsString('id="login-email-error"', $html);
    }

    #[Test]
    public function a_wrong_type_a_choice_without_choices_or_no_name_is_said_at_once(): void
    {
        foreach (['<x-webx-field type="color" name="c" />', '<x-webx-field type="select" name="s" />', '<x-webx-field type="radio" name="r" />', '<x-webx-field name="[]" />'] as $wrong) {
            $cause = null;

            try {
                Blade::render($wrong);
            } catch (Throwable $error) {
                $cause = $error instanceof ViewException ? ($error->getPrevious() ?? $error) : $error;
            }

            $this->assertInstanceOf(InvalidArgumentException::class, $cause, "{$wrong} rendered");
            $this->assertStringContainsString('<x-webx-field', $cause->getMessage());
        }
    }

    #[Test]
    public function names_turn_into_keys_and_back(): void
    {
        $this->assertSame('fields.email', Field::keyOf('fields[email]'));
        $this->assertSame('items.0.qty', Field::keyOf('items[0][qty]'));
        $this->assertSame('tags', Field::keyOf('tags[]'));
        $this->assertSame('items[0][qty]', Field::nameOf('items.0.qty'));
        $this->assertSame('email', Field::nameOf('email'));
    }

    /**
     * The whole way without JavaScript: a plain POST, Laravel's redirect back with the input and
     * the errors in the session, and the page drawn again — each error under its own field, the
     * answers where they were, a box unticked staying unticked.
     */
    #[Test]
    public function a_form_sent_without_javascript_comes_back_with_its_errors_in_their_places(): void
    {
        $this->get('/order')->assertOk();

        $page = $this->followingRedirects()->from('/order')->post('/order', [
            'fields' => ['email' => 'not an address'],
            'items' => [['qty' => '0']],
            'extras' => ['wrap'],
        ]);

        $html = (string) $page->getContent();

        $this->assertStringContainsString('name="fields[email]" value="not an address"', $html);
        $this->assertMatchesRegularExpression('~id="field-fields-email-error" >[^<]*email[^<]*</p>~', $html);
        $this->assertStringContainsString('name="items[0][qty]" value="0"', $html);
        $this->assertMatchesRegularExpression('~id="field-items-0-qty-error" >[^<]*1[^<]*</p>~', $html);
        $this->assertMatchesRegularExpression('~<fieldset aria-describedby="field-delivery-error" class="webx-field webx-field--radio is-invalid is-required" data-webx-field="delivery">~', $html);
        $this->assertMatchesRegularExpression('~<fieldset aria-describedby="field-extras-error" class="webx-field webx-field--checkbox is-invalid" data-webx-field="extras">~', $html);
        $this->assertStringContainsString('value="wrap" checked ', $html);
        $this->assertMatchesRegularExpression('~id="field-terms-error" >[^<]+</p>~', $html);
        $this->assertStringNotContainsString('id="field-terms" name="terms" value="1" checked', $html);
    }
}
