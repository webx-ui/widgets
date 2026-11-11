<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Widgets\Facades\Widgets;

/**
 * FORM_STEPS §3, stage 5: `<x-webx-form steps>` and `<x-webx-form.step>` — every step printed, a
 * fieldset each, the indicator and the buttons hidden for the page without JavaScript, the script
 * claimed only by a form of steps, the door that checks a step, a step's condition, and the page
 * that comes back with errors under the fields of every step.
 */
final class FormStepsTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    private const string FORM = <<<'BLADE'
        <x-webx-form action="/visit" id="visit" steps check="/visit/check">
            <x-webx-form.step name="purpose" title="Purpose of contact" hint="One of four.">
                <x-webx-field name="fields.purpose" type="radio" label="How can we help?" :choices="['direct' => 'Direct inquiry', 'agency' => 'Agency meeting']" required />
            </x-webx-form.step>
            <x-webx-form.step name="meeting" title="Choose a meeting time" label="Time" :when="['fields.purpose' => ['agency']]" condition="If chosen: Agency meeting">
                <x-webx-field name="fields.day" type="date" label="Day" required />
            </x-webx-form.step>
            <x-webx-form.step name="contacts" title="Your details" next-label="On">
                <x-webx-field name="fields.name" label="Name" required />
            </x-webx-form.step>
            <x-webx-form.submit>Send</x-webx-form.submit>
        </x-webx-form>
        BLADE;

    #[Test]
    public function every_step_is_printed_as_a_fieldset_and_the_script_has_what_it_needs(): void
    {
        $html = Blade::render(self::FORM);

        $this->assertMatchesRegularExpression('~<form id="visit" action="/visit" method="post" data-webx-send="" data-webx-csrf="[^"]+" data-webx-check="/visit/check" data-webx-steps-count="Step :current of :total" data-webx-steps-return="Back to step :number: :title" data-webx-steps-fix="Please check step :number: :message" class="webx-form webx-form--steps">~', $html);
        // The indicator, empty and hidden until steps.js fills it.
        $this->assertStringContainsString('<ol class="webx-form-steps" aria-label="Steps" hidden></ol>', $html);
        $this->assertSame(3, preg_match_all('~<fieldset id="visit-step-[a-z]+" class="webx-form-step"~', $html));
        $this->assertStringContainsString('<fieldset id="visit-step-purpose" class="webx-form-step" data-webx-step="purpose" data-webx-step-label="Purpose of contact">', $html);
        $this->assertStringContainsString('<span class="webx-form-step__title" id="visit-step-purpose-title" role="heading" aria-level="3" tabindex="-1">Purpose of contact</span>', $html);
        $this->assertStringContainsString('<p class="webx-form-step__hint">One of four.</p>', $html);
        // The short label of the indicator, the condition and its line for the page without JavaScript.
        $this->assertStringContainsString('data-webx-step="meeting" data-webx-step-label="Time" data-webx-when="{&quot;fields.purpose&quot;:[&quot;agency&quot;]}">', $html);
        $this->assertStringContainsString('<p class="webx-form-step__condition" data-webx-condition>If chosen: Agency meeting</p>', $html);
        // The buttons and the count: there for the script, hidden without it.
        $this->assertSame(3, substr_count($html, '<div class="webx-form-step__nav" hidden>'));
        $this->assertSame(3, substr_count($html, '<span class="webx-form-step__count" hidden></span>'));
        $this->assertStringContainsString('<button type="button" class="webx-form-step__back" data-webx-step-back>Back</button>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-form-step__next" data-webx-step-next>On</button>', $html);
        // One button sends it all, under the steps.
        $this->assertSame(1, substr_count($html, 'type="submit"'));
        $this->assertGreaterThan(strrpos($html, '</fieldset>'), strpos($html, '<button type="submit" class="webx-form__submit">Send</button>'));
        $this->assertContains('steps', Widgets::claimed());
        // A required field of a step with a condition is not required to the browser without the
        // script — the step may not be the visitor's; with it, form.js puts the attribute back.
        $this->assertMatchesRegularExpression('~<input type="date"[^>]*name="fields\[day\]"[^>]*data-webx-required="true"~', $html);
        $this->assertDoesNotMatchRegularExpression('~<input type="date"[^>]*\srequired[\s>]~', $html);
        $this->assertMatchesRegularExpression('~name="fields\[name\]"[^>]*\srequired~', $html);
    }

    #[Test]
    public function a_flat_form_neither_claims_the_script_nor_says_a_word_of_steps(): void
    {
        $html = Blade::render('<x-webx-form action="/send" check="/send/check"><x-webx-field name="name" label="Name" /></x-webx-form>');

        $this->assertNotContains('steps', Widgets::claimed());
        $this->assertStringNotContainsString('webx-form-steps', $html);
        $this->assertStringNotContainsString('data-webx-steps', $html);
        // A door that checks a step is nothing to a form without steps.
        $this->assertStringNotContainsString('data-webx-check', $html);
        $this->assertStringContainsString('class="webx-form">', $html);
    }

    #[Test]
    public function the_words_are_the_pages_language_and_each_a_prop(): void
    {
        $this->app->setLocale('ru');
        $html = Blade::render(self::FORM);

        $this->assertStringContainsString('data-webx-steps-count="Шаг :current из :total"', $html);
        $this->assertStringContainsString('aria-label="Шаги"', $html);
        $this->assertStringContainsString('data-webx-step-back>Назад</button>', $html);

        $own = Blade::render('<x-webx-form action="/a" steps steps-label="Parts" step-label="Part :current/:total" return-label="To :number" fix-label="See :number"><x-webx-form.step name="one" back-label="Earlier">x</x-webx-form.step></x-webx-form>');
        $this->assertStringContainsString('data-webx-steps-count="Part :current/:total" data-webx-steps-return="To :number" data-webx-steps-fix="See :number"', $own);
        $this->assertStringContainsString('aria-label="Parts"', $own);
        $this->assertStringContainsString('data-webx-step-back>Earlier</button>', $own);
        // No title: no heading, no label — the script names it «Step 1 of 1».
        $this->assertStringContainsString('<fieldset id="form-1-step-one" class="webx-form-step" data-webx-step="one">', $own);
    }

    #[Test]
    public function the_summary_is_printed_hidden_with_its_words_and_claims_its_script(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-webx-form action="/visit" id="visit" steps>
                <x-webx-form.step name="purpose" title="Purpose">
                    <x-webx-field name="fields.purpose" type="radio" label="Purpose" :choices="['agency' => 'Agency meeting']" />
                </x-webx-form.step>
                <x-webx-form.step name="contacts" title="Your details">
                    <x-webx-form.summary title="Your request" hint="Check it before sending." :fields="['fields.purpose']" />
                    <x-webx-field name="fields.name" label="Name" />
                </x-webx-form.step>
            </x-webx-form>
            BLADE);

        $this->assertContains('summary', Widgets::claimed());
        // Hidden: without JavaScript every answer is on the page above it; summary.js fills the list.
        $this->assertStringContainsString('<section id="visit-summary-1" class="webx-form-summary" data-webx-summary data-webx-summary-edit="Edit" data-webx-summary-field="Edit: :field" data-webx-summary-fields="[&quot;fields.purpose&quot;]" aria-labelledby="visit-summary-1-title" hidden>', $html);
        $this->assertStringContainsString('<p class="webx-form-summary__title" id="visit-summary-1-title" role="heading" aria-level="4">Your request</p>', $html);
        $this->assertStringContainsString('<p class="webx-form-summary__hint">Check it before sending.</p>', $html);
        $this->assertStringContainsString('<dl class="webx-form-summary__list"></dl>', $html);

        // No names: every field above it; the words in the page's language, or the props.
        $this->app->setLocale('ru');
        $plain = Blade::render('<x-webx-form action="/a"><x-webx-form.summary /></x-webx-form>');
        $this->assertStringNotContainsString('data-webx-summary-fields', $plain);
        $this->assertStringNotContainsString('aria-labelledby', $plain);
        $this->assertStringContainsString('data-webx-summary-edit="Изменить" data-webx-summary-field="Изменить: :field"', $plain);
        $own = Blade::render('<x-webx-form action="/a"><x-webx-form.summary edit-label="Change" edit-field-label="Change :field" :level="3" title="T" /></x-webx-form>');
        $this->assertStringContainsString('data-webx-summary-edit="Change" data-webx-summary-field="Change :field"', $own);
        $this->assertStringContainsString('aria-level="3"', $own);
    }

    #[Test]
    public function a_step_name_the_form_cannot_send_is_a_mistake_said_at_once(): void
    {
        try {
            Blade::render('<x-webx-form action="/a" steps><x-webx-form.step name="a step">x</x-webx-form.step></x-webx-form>');
            $this->fail('A name with a space was printed.');
        } catch (Throwable $exception) {
            $this->assertInstanceOf(ViewException::class, $exception);
            $this->assertStringContainsString('<x-webx-form.step name="a step">', $exception->getMessage());
        }
    }

    #[Test]
    public function without_javascript_the_page_comes_back_with_the_errors_under_the_fields_of_every_step(): void
    {
        $this->withSession([
            '_old_input' => ['_webx_form' => 'visit', 'fields' => ['purpose' => 'agency']],
            'errors' => (new ViewErrorBag)->put('default', new MessageBag([
                'fields.day' => ['Choose a weekday.'],
                'fields.name' => ['The name is required.'],
            ])),
        ]);
        $this->app['router']->middleware('web')->get('/visit', static fn (): string => Blade::render(self::FORM));

        $page = (string) $this->get('/visit')->assertOk()->getContent();

        $this->assertStringContainsString('data-webx-returned', $page);
        $this->assertMatchesRegularExpression('~data-webx-step="meeting".*Choose a weekday\..*data-webx-step="contacts".*The name is required\.~s', $page);
        $this->assertMatchesRegularExpression('~value="agency"[^>]*checked~', $page);
    }
}
