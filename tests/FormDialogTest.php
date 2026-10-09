<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Facades\Widgets as WidgetsFacade;
use WebxUi\Widgets\FormDialogs;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * §6.4: a form of `module-inbox` in a dialog — printed once per slug at the end of `<body>`,
 * the module's own form inside it placed `modal`, opened by any button that names it.
 */
final class FormDialogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            InboxServiceProvider::class,
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-inbox.antispam.min_seconds', 0);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $page = <<<'BLADE'
            <x-layout>
                <a href="#webx-form-callback" data-webx-form="callback">Request a call</a>
                <button type="button" data-webx-form="callback" data-webx-form-value-service="12">Book</button>
                <a href="#webx-form-contact">Write to us</a>
                <a href="/x" data-webx-form="nothing">No such form</a>
                <x-webx-inbox::form slug="contact" />
            </x-layout>
            BLADE;

        $router->middleware('web')->group(function (Router $router) use ($page): void {
            $router->get('/page', static fn (): string => Blade::render($page));
            $router->get('/claimed', static fn (): string => Blade::render("<x-layout>@php(WebxUi\\Widgets\\Facades\\Widgets::form('callback'))<p>Hi</p></x-layout>"));
            $router->get('/none', static fn (): string => Blade::render('<x-layout><p>Hi</p></x-layout>'));
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['callback' => 'Request a call', 'contact' => 'Contact us'] as $slug => $title) {
            $form = Form::query()->create(['slug' => $slug, 'title' => ['en' => $title], 'is_enabled' => true, 'options' => []]);
            $form->fields()->create(['name' => 'name', 'type' => FieldType::Text, 'is_enabled' => true, 'is_required' => true, 'title' => ['en' => 'Name'], 'position' => 0]);
            $form->fields()->create(['name' => 'service', 'type' => FieldType::Hidden, 'is_enabled' => true, 'is_required' => false, 'title' => ['en' => 'Service'], 'position' => 1]);
        }
    }

    #[Test]
    public function every_form_the_page_opens_is_printed_once_at_the_end_of_the_body(): void
    {
        $html = (string) $this->get('/page')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<dialog id="webx-form-callback"'));
        $this->assertSame(1, substr_count($html, '<dialog id="webx-form-contact"'));
        $this->assertStringNotContainsString('webx-form-nothing', $html);

        // In the dialog: the module's own form, placed `modal`, under the form's title.
        $this->assertStringContainsString('<h2 class="webx-dialog__title webx-form-dialog__title" id="webx-form-callback-title">Request a call</h2>', $html);
        $this->assertMatchesRegularExpression('~<form[^>]*data-webx-form="callback"[^>]*class="wx-form wx-form--modal"~', $html);
        $this->assertStringContainsString('<input type="hidden" name="webx_placement" value="modal">', $html);
        $this->assertStringContainsString('data-webx-dialog-close hidden>Close</button>', $html);

        // After the page, before the scripts; the form on the page itself keeps its plain ids.
        $this->assertLessThan(strrpos($html, '</body>'), strrpos($html, '</dialog>'));
        $this->assertGreaterThan(strpos($html, 'No such form'), strpos($html, '<dialog id="webx-form-callback"'));
        $this->assertStringContainsString('id="wx-form-contact-name"', $html);
        $this->assertStringContainsString('id="wx-form-contact-2-name"', $html);
        $this->assertSame(1, substr_count($html, '/inbox.js'));
        $this->assertStringNotContainsString(' open>', $html);
    }

    #[Test]
    public function a_form_claimed_in_code_gets_its_dialog_without_an_opener_on_the_page(): void
    {
        $this->assertStringContainsString('<dialog id="webx-form-callback"', (string) $this->get('/claimed')->getContent());
        $this->assertStringNotContainsString('<dialog', (string) $this->get('/none')->getContent());
    }

    #[Test]
    public function a_switched_off_form_has_no_dialog(): void
    {
        Form::query()->where('slug', 'callback')->update(['is_enabled' => false]);

        $html = (string) $this->get('/page')->getContent();

        $this->assertStringNotContainsString('<dialog id="webx-form-callback"', $html);
        $this->assertStringContainsString('<dialog id="webx-form-contact"', $html);
    }

    #[Test]
    public function the_submission_remembers_it_came_from_a_dialog(): void
    {
        $this->post('/webx/forms/callback', [
            'webx_form' => 'callback',
            'webx_placement' => 'modal',
            'fields' => ['name' => 'Ann', 'service' => '12'],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('modal', Submission::query()->sole()->placement);
    }

    #[Test]
    public function the_page_back_from_a_form_sent_without_javascript_prints_its_dialog_open_with_the_answer(): void
    {
        // Sent and accepted: the intake flashed the thank-you, and this is the page after the
        // redirect — the flash is on its last request, and the session drops it when it saves,
        // before the whole response is handled. The dialog is printed before that.
        $html = (string) $this->withSession([
            'webx-inbox' => ['ok' => true, 'form' => 'callback', 'heading' => 'We will call you', 'message' => null],
            '_flash' => ['old' => ['webx-inbox'], 'new' => []],
        ])->get('/page')->getContent();

        $this->assertMatchesRegularExpression('~<dialog id="webx-form-callback"[^>]* open>~', $html);
        $this->assertDoesNotMatchRegularExpression('~<dialog id="webx-form-contact"[^>]* open>~', $html);
        $this->assertMatchesRegularExpression('~<dialog id="webx-form-callback".*data-webx-message-heading>We will call you<~s', $html);
        $this->assertFalse(app('session.store')->has('webx-inbox'));

        // Refused: the errors come back to the dialog the form was sent from.
        $html = (string) $this->withSession([
            '_old_input' => ['webx_form' => 'callback', 'webx_placement' => 'modal', 'fields' => ['name' => '']],
            'errors' => (new ViewErrorBag)->put('default', new MessageBag(['fields.name' => ['Name is required.']])),
            '_flash' => ['old' => ['_old_input', 'errors'], 'new' => []],
        ])->get('/page')->getContent();

        $this->assertMatchesRegularExpression('~<dialog id="webx-form-callback"[^>]* open>~', $html);
        $this->assertMatchesRegularExpression('~<dialog id="webx-form-callback".*is-invalid.*Name is required\.~s', $html);

        // The next page is a page like any other.
        $this->assertStringNotContainsString(' open>', (string) $this->get('/page')->getContent());
    }

    #[Test]
    public function openers_are_links_and_buttons_naming_a_form_not_the_form_itself(): void
    {
        $this->assertSame(['callback', 'contact', 'call-me'], FormDialogs::openers(<<<'HTML'
            <a href="#" data-webx-form="callback">One</a>
            <button data-webx-form='callback'>Two</button>
            <a class="b" href="#webx-form-contact">Three</a>
            <form data-webx-form="subscribe"></form>
            <div data-webx-form="ignored"></div>
            <A HREF="#webx-form-call-me">Four</A>
            HTML));
    }

    #[Test]
    public function a_slug_that_is_not_one_is_a_typo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WidgetsFacade::form('call me');
    }
}
