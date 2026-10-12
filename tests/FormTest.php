<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;
use WebxUi\Widgets\Forms\ShowcaseController;

/**
 * Spec §17.3, §17.4, W6.2: the shell of a form — the token and the method printed by itself, a
 * door that guards itself without one, the answer of FormResponse to a request that wants JSON
 * and to a plain POST, the page that comes back printing it in the form that was sent, an error
 * of no field in the form's alert, a dialog opened again around it, the route of a fresh token
 * and the showcase's door that is there only with the demo.
 */
final class FormTest extends TestCase
{
    private string $journal;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The web group encrypts its cookies: the session a form comes back through needs a key.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));

        $this->journal = str_replace('\\', '/', sys_get_temp_dir()).'/webx-widgets-demo-'.bin2hex(random_bytes(6)).'.json';
        $app->singleton(DemoLedger::class, fn ($app): DemoLedger => new DemoLedger(new Filesystem, $app->make(FilesystemFactory::class), $this->journal));
    }

    protected function tearDown(): void
    {
        @unlink($this->journal);

        parent::tearDown();
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(static function (Router $router): void {
            // Two forms on one page — a subscription in the footer and the request — as a layout has them.
            $router->get('/contact', static fn (): string => Blade::render(<<<'BLADE'
                <x-layout>
                    <x-webx-form action="/subscribe" id="subscribe">
                        <x-webx-field name="email" type="email" label="Email" />
                        <x-webx-form.submit>Subscribe</x-webx-form.submit>
                    </x-webx-form>
                    <x-webx-form action="/contact" id="request">
                        <x-webx-field name="fields.name" label="Name" required />
                        <x-webx-field name="fields.email" type="email" label="Email" required />
                        <x-webx-form.submit>Send</x-webx-form.submit>
                    </x-webx-form>
                    <x-webx-dialog id="callback" title="Call me back">
                        <x-webx-form action="/callback" id="callback-form">
                            <x-webx-field name="phone" type="tel" label="Phone" required />
                            <x-webx-form.submit>Call me</x-webx-form.submit>
                        </x-webx-form>
                    </x-webx-dialog>
                </x-layout>
                BLADE));
            $router->get('/two', static fn (): string => Blade::render(<<<'BLADE'
                <x-webx-form action="/contact" id="request">
                    <x-webx-field name="email" type="email" label="Email" />
                </x-webx-form>
                <x-webx-form action="/subscribe" id="subscribe">
                    <x-webx-field name="email" type="email" label="Email" value="you@example.test" />
                    <x-webx-field name="news" type="checkbox" label="News" checked />
                </x-webx-form>
                BLADE));
            $router->post('/contact', static function (Request $request): FormResponse {
                $request->validate([
                    'fields.name' => ['required'],
                    'fields.email' => ['required', 'email'],
                    // A rule about something the form has no field for: said in the form's alert.
                    'captcha' => ['required'],
                ], ['captcha.required' => 'Prove you are not a robot.']);

                return FormResponse::message('Thank you', '<p>We will write back.</p>');
            });
            $router->post('/subscribe', static fn (Request $request): FormResponse => match ($request->input('answer')) {
                'redirect' => FormResponse::redirect('/welcome'),
                'html' => FormResponse::html(new HtmlString('<p class="step">Step two</p>')),
                'nothing' => FormResponse::ok(),
                default => FormResponse::message('Subscribed', '<p>See you.</p>'),
            });
            $router->post('/callback', static function (Request $request): FormResponse {
                $request->validate(['phone' => ['required']]);

                return FormResponse::message('We will call');
            });
        });
    }

    private static function form(string $props, string $slot = ''): string
    {
        return Blade::render("<x-webx-form {$props}>{$slot}</x-webx-form>");
    }

    #[Test]
    public function the_form_prints_its_token_its_method_and_which_form_it_is(): void
    {
        $html = self::form('action="/orders/7" method="put" class="order"', '<x-webx-form.submit>Save</x-webx-form.submit>');

        $this->assertStringContainsString('<form id="form-1" action="/orders/7" method="post" data-webx-send="" data-webx-csrf="http://localhost/webx/widgets/csrf" class="webx-form order">', $html);
        $this->assertStringContainsString('<input type="hidden" name="_token"', $html);
        $this->assertStringContainsString('<input type="hidden" name="_method" value="PUT">', $html);
        $this->assertStringContainsString('<input type="hidden" name="_webx_form" value="form-1">', $html);
        $this->assertStringContainsString('<button type="submit" class="webx-form__submit">Save</button>', $html);
        // The places of the answer, there before there is anything to say.
        $this->assertStringContainsString('<div class="webx-form__message" role="status" aria-live="polite"></div>', $html);
        $this->assertMatchesRegularExpression('~<div class="webx-form__error" role="alert" data-webx-failed="[^"]+" data-webx-expired="[^"]+"></div>~', $html);
        $this->assertStringContainsString('<button type="button" class="webx-form__done" data-webx-dialog-close hidden>Close</button>', $html);
        $this->assertContains('form', Widgets::claimed());

        // POST is POST: nothing to spoof. Another form on the page is the next one.
        $post = self::form('action="/orders"');
        $this->assertStringNotContainsString('name="_method"', $post);
        $this->assertStringContainsString('id="form-2"', $post);
    }

    #[Test]
    public function a_door_that_guards_itself_gets_no_token_and_a_search_is_a_plain_form(): void
    {
        $intake = self::form('action="/webx/forms/contact" :csrf="false"');
        $this->assertStringNotContainsString('name="_token"', $intake);
        $this->assertStringNotContainsString('data-webx-csrf', $intake);
        $this->assertStringContainsString('data-webx-send', $intake);

        $search = self::form('action="/search" method="get"', '<x-webx-field name="q" label="Search" />');
        $this->assertStringContainsString('method="get"', $search);
        foreach (['name="_token"', 'name="_webx_form"', 'data-webx-send', 'webx-form__message'] as $absent) {
            $this->assertStringNotContainsString($absent, $search);
        }
    }

    #[Test]
    public function a_form_with_a_file_is_multipart_and_its_words_are_the_page_language(): void
    {
        $this->assertStringContainsString('enctype="multipart/form-data"', self::form('action="/cv"', '<x-webx-field name="cv" type="file" label="CV" />'));
        $this->assertStringNotContainsString('enctype', self::form('action="/cv"', '<x-webx-field name="name" label="Name" />'));

        app()->setLocale('ru');
        $this->assertStringContainsString('>Закрыть</button>', self::form('action="/x"'));
        $this->assertStringContainsString('data-webx-failed="Не удалось отправить форму.', self::form('action="/x"'));
        $this->assertStringContainsString('data-webx-failed="Nope"', self::form('action="/x" failed-label="Nope"'));
    }

    #[Test]
    public function a_method_the_form_does_not_know_is_a_mistake_said_at_once(): void
    {
        try {
            self::form('action="/x" method="send"');
            $this->fail('A method the browser cannot send was accepted.');
        } catch (Throwable $e) {
            $this->assertInstanceOf(ViewException::class, $e);
            $this->assertStringContainsString('<x-webx-form method="send">', $e->getMessage());
        }
    }

    #[Test]
    public function every_field_says_its_validators_name_where_the_script_looks_for_it(): void
    {
        $html = self::form('action="/x"', '<x-webx-field name="items[0][qty]" type="number" label="How many" /><x-webx-field name="extras" type="checkbox" label="Extras" :choices="[\'a\' => \'A\']" />');

        $this->assertStringContainsString('data-webx-field="items.0.qty"', $html);
        $this->assertMatchesRegularExpression('~<fieldset[^>]+data-webx-field="extras">~', $html);
    }

    #[Test]
    public function the_answer_to_a_request_that_wants_json_is_the_one_convention(): void
    {
        $this->postJson('/subscribe', ['answer' => 'message'])->assertExactJson(['ok' => true, 'heading' => 'Subscribed', 'message' => '<p>See you.</p>', 'redirect' => null, 'html' => null]);
        $this->postJson('/subscribe', ['answer' => 'redirect'])->assertExactJson(['ok' => true, 'heading' => null, 'message' => null, 'redirect' => '/welcome', 'html' => null]);
        $this->postJson('/subscribe', ['answer' => 'html'])->assertExactJson(['ok' => true, 'heading' => null, 'message' => null, 'redirect' => null, 'html' => '<p class="step">Step two</p>']);
        $this->postJson('/subscribe', ['answer' => 'nothing'])->assertExactJson(['ok' => true, 'heading' => null, 'message' => null, 'redirect' => null, 'html' => null]);

        // And the validator's 422, Laravel's as it is: the script lays it out by field.
        $this->postJson('/contact', [])->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['fields.name', 'fields.email', 'captcha']]);
    }

    #[Test]
    public function a_view_is_rendered_into_the_answer(): void
    {
        $answer = FormResponse::html(view('webx-widgets::components.form.submit', ['slot' => new HtmlString('Pay'), 'attributes' => new ComponentAttributeBag]))->toArray();

        $this->assertStringContainsString('<button type="submit" class="webx-form__submit">Pay</button>', (string) $answer['html']);
    }

    #[Test]
    public function without_javascript_the_answer_comes_back_to_the_form_that_was_sent_and_only_to_it(): void
    {
        $this->get('/contact')->assertOk();

        $this->from('/contact')->post('/subscribe', ['answer' => 'redirect', '_webx_form' => 'subscribe'])->assertRedirect('/welcome');

        $page = (string) $this->followingRedirects()->from('/contact')->post('/subscribe', ['answer' => 'message', '_webx_form' => 'subscribe'])->getContent();

        $this->assertMatchesRegularExpression('~<form id="subscribe"[^>]*class="webx-form is-sent" data-webx-returned>~', $page);
        $this->assertMatchesRegularExpression('~<div class="webx-form__message" role="status" aria-live="polite"><div class="webx-form__answer">\s*<p class="webx-form__heading">Subscribed</p>\s*<div class="webx-form__text"><p>See you.</p></div>~', $page);
        // The request on the same page said nothing and was not the one sent.
        $this->assertSame(1, substr_count($page, 'Subscribed'));
        $this->assertMatchesRegularExpression('~<form id="request"[^>]*class="webx-form">~', $page);

        $step = (string) $this->followingRedirects()->from('/contact')->post('/subscribe', ['answer' => 'html', '_webx_form' => 'subscribe'])->getContent();
        $this->assertMatchesRegularExpression('~id="subscribe".*?<div class="webx-form__body">\s*<p class="step">Step two</p>\s*</div>~s', $step);
        $this->assertStringNotContainsString('id="field-email"', $step);
    }

    #[Test]
    public function without_javascript_an_error_of_no_field_is_said_in_the_alert_of_the_form_that_was_sent(): void
    {
        $page = (string) $this->followingRedirects()->from('/contact')->post('/contact', ['fields' => ['email' => 'nope'], '_webx_form' => 'request'])->getContent();

        $this->assertMatchesRegularExpression('~<form id="request"[^>]*class="webx-form" data-webx-returned>~', $page);
        $this->assertMatchesRegularExpression('~id="field-fields-name-error" >[^<]+</p>~', $page);
        $this->assertMatchesRegularExpression('~id="field-fields-email-error" >[^<]+</p>~', $page);
        $this->assertStringContainsString('role="alert" data-webx-failed', $page);
        $this->assertSame(1, substr_count($page, '<p class="webx-form__refusal">Prove you are not a robot.</p>'));
        // The subscription is another form: no alert of the request in it, not returned.
        $this->assertMatchesRegularExpression('~<form id="subscribe"[^>]*class="webx-form">~', $page);
    }

    #[Test]
    public function a_field_of_another_form_keeps_what_the_page_gave_it(): void
    {
        // Two forms with an `email` each — a request refused in the body, a subscription in the
        // footer: what came back is the request's, and the footer stays as the page printed it.
        $this->withSession([
            '_old_input' => ['_webx_form' => 'request', 'email' => 'ada@', 'news' => '1'],
            'errors' => (new ViewErrorBag)->put('default', new MessageBag(['email' => ['Not an address.']])),
        ]);

        $page = (string) $this->get('/two')->getContent();

        $this->assertMatchesRegularExpression('~<form id="request".*id="field-email" name="email" value="ada@".*Not an address\.~s', $page);
        $footer = (string) strstr($page, '<form id="subscribe"');
        $this->assertStringContainsString('name="email" value="you@example.test"', $footer);
        $this->assertStringNotContainsString('Not an address.', $footer);
        $this->assertStringNotContainsString('is-invalid', $footer);
        // A tick the page ticks stays ticked: the subscription did not come back with it unticked.
        $this->assertMatchesRegularExpression('~name="news" value="1" checked~', $footer);
    }

    #[Test]
    public function a_dialog_whose_form_came_back_is_printed_open(): void
    {
        $closed = (string) $this->get('/contact')->getContent();
        $this->assertMatchesRegularExpression('~<dialog id="callback" aria-labelledby="callback-title" class="webx-dialog">~', $closed);

        $back = (string) $this->followingRedirects()->from('/contact')->post('/callback', ['_webx_form' => 'callback-form'])->getContent();
        $this->assertMatchesRegularExpression('~<dialog id="callback" aria-labelledby="callback-title" class="webx-dialog" open >~', $back);
        $this->assertMatchesRegularExpression('~id="field-phone-error" >[^<]+</p>~', $back);

        $thanks = (string) $this->followingRedirects()->from('/contact')->post('/callback', ['phone' => '+48 600', '_webx_form' => 'callback-form'])->getContent();
        $this->assertMatchesRegularExpression('~<dialog id="callback"[^>]* open >~', $thanks);
        $this->assertStringContainsString('<p class="webx-form__heading">We will call</p>', $thanks);
    }

    #[Test]
    public function the_page_of_a_form_loads_its_script(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();
        $html = (string) $this->get('/contact')->getContent();

        $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/form\.js"></script>~', $html);
    }

    #[Test]
    public function the_token_route_starts_a_session_and_hands_the_token_in_its_cookie(): void
    {
        $response = $this->get('/webx/widgets/csrf');

        $response->assertNoContent();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertCookie('XSRF-TOKEN');
        $this->assertNotSame('', (string) session()->token());
    }

    #[Test]
    public function the_showcase_door_is_there_only_with_the_demo(): void
    {
        $this->assertFalse(ShowcaseController::available());
        $this->postJson('/webx/widgets/showcase', [])->assertNotFound();

        file_put_contents($this->journal, '{"version":1,"entries":[]}');
        $this->assertTrue(ShowcaseController::available());

        $refused = $this->postJson('/webx/widgets/showcase', ['labels' => ['name' => 'Your name']])->assertStatus(422);
        $this->assertSame(['showcase.name', 'showcase.email', 'showcase.answer', 'showcase.agree'], array_keys((array) $refused->json('errors')));
        $this->assertStringContainsString('Your name', (string) $refused->json('errors')['showcase.name'][0]);

        $sent = ['showcase' => ['name' => '<b>Ann</b>', 'email' => 'ann@example.com', 'agree' => '1'], 'words' => ['heading' => 'Thanks', 'message' => 'Got it, :name.', 'step' => 'Next, :name.'], 'to' => '/kitchen-sink'];

        $this->postJson('/webx/widgets/showcase', array_replace_recursive($sent, ['showcase' => ['answer' => 'message']]))
            ->assertJson(['ok' => true, 'heading' => 'Thanks', 'message' => '<p>Got it, &lt;b&gt;Ann&lt;/b&gt;.</p>']);
        $this->postJson('/webx/widgets/showcase', array_replace_recursive($sent, ['showcase' => ['answer' => 'redirect']]))
            ->assertJson(['redirect' => '/kitchen-sink']);
        // Never another site's address: the demo is not an open redirect.
        $this->postJson('/webx/widgets/showcase', array_replace_recursive($sent, ['showcase' => ['answer' => 'redirect'], 'to' => '//evil.example']))
            ->assertJson(['redirect' => 'http://localhost']);
        $this->assertStringContainsString('Next, &lt;b&gt;Ann&lt;/b&gt;.', (string) $this->postJson('/webx/widgets/showcase', array_replace_recursive($sent, ['showcase' => ['answer' => 'html']]))->json('html'));
        $this->postJson('/webx/widgets/showcase', array_replace_recursive($sent, ['showcase' => ['answer' => 'nothing']]))
            ->assertExactJson(['ok' => true, 'heading' => null, 'message' => null, 'redirect' => null, 'html' => null]);

        // Removed with the demo: the journal is gone, the door with it.
        unlink($this->journal);
        $this->postJson('/webx/widgets/showcase', [])->assertNotFound();
    }

    #[Test]
    public function the_validation_showcase_door_says_what_the_browser_said(): void
    {
        $this->postJson('/webx/widgets/showcase/validation', [])->assertNotFound();
        file_put_contents($this->journal, '{"version":1,"entries":[]}');

        $labels = ['name' => 'Your name', 'phone' => 'Phone', 'photos' => 'Photos'];
        $browser = FormRules::for(ShowcaseController::CHECKS, ShowcaseController::attributes($labels));

        $refused = $this->postJson('/webx/widgets/showcase/validation', ['labels' => $labels, 'check' => ['name' => 'admin', 'phone' => '512', 'password' => 'secret12', 'password_confirmation' => 'other']])->assertStatus(422);
        $errors = (array) $refused->json('errors');

        // What the browser checks, the server says in the same words.
        $phone = array_column($browser->field('check.phone')['checks'], 'message', 'rule');
        $this->assertSame([$phone['regex']], $errors['check.phone']);
        $password = array_column($browser->field('check.password')['checks'], 'message', 'rule');
        $this->assertSame([$password['confirmed']], $errors['check.password']);
        // What only the server knows: the name passes the browser and not the server.
        $this->assertNotContains('not_in', array_column($browser->field('check.name')['checks'], 'rule'));
        $this->assertStringContainsString('Your name', (string) $errors['check.name'][0]);

        $this->postJson('/webx/widgets/showcase/validation', [
            'words' => ['heading' => 'All right', 'message' => 'Checked twice.'],
            'check' => ['name' => 'Ann', 'email' => 'ann@example.com', 'phone' => '+48 512 345 678', 'day' => '2026-05-01', 'password' => 'secret12', 'password_confirmation' => 'secret12', 'plan' => 'team', 'agree' => '1'],
        ])->assertJson(['ok' => true, 'heading' => 'All right', 'message' => '<p>Checked twice.</p>']);
    }

    #[Test]
    public function the_suggestions_showcase_door_answers_html_from_the_pages_goods_and_words(): void
    {
        $items = "Oak desk | €349\nOak <shelf> | €289\nDesk lamp | €79\n\nLinen curtains";
        $words = ['products' => 'Products (:count)', 'all' => 'See all: :count', 'nothing' => 'Nothing for “:q”.'];

        $this->get('/webx/widgets/showcase/suggest?q=oak')->assertNotFound();
        file_put_contents($this->journal, '{"version":1,"entries":[]}');

        $cards = $this->get('/webx/widgets/showcase/suggest?'.http_build_query(['q' => 'OAK', 'items' => $items, 'words' => $words, 'to' => '/kitchen-sink/forms/suggestions']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $html = (string) $cards->getContent();
        $this->assertStringContainsString('no-store', (string) $cards->headers->get('Cache-Control'));
        $this->assertStringContainsString('<div class="b-showcase-suggest__found" data-webx-section="products"><p class="b-showcase-suggest__found-heading" data-webx-heading>Products (2)</p>', $html);
        $this->assertStringContainsString('<a class="b-showcase-suggest__card" href="/kitchen-sink/forms/suggestions?q=Oak%20desk" data-webx-option><span class="b-showcase-suggest__card-body"><span class="b-showcase-suggest__card-name"><mark class="b-showcase-suggest__mark">Oak</mark> desk</span><span class="b-showcase-suggest__card-price">€349</span></span></a>', $html);
        // The page's goods are escaped, the typed words marked as they stand in the name.
        $this->assertStringContainsString('<mark class="b-showcase-suggest__mark">Oak</mark> &lt;shelf&gt;</span>', $html);
        $this->assertStringContainsString('<a class="b-showcase-suggest__all" href="/kitchen-sink/forms/suggestions?q=OAK" data-webx-option>See all: 2</a>', $html);

        $values = (string) $this->get('/webx/widgets/showcase/suggest?'.http_build_query(['q' => 'lin', 'items' => $items, 'as' => 'values']))->assertOk()->getContent();
        $this->assertSame('<ul class="b-showcase-suggest__values"><li class="b-showcase-suggest__value" data-webx-option="Linen curtains"><mark class="b-showcase-suggest__mark">Lin</mark>en curtains</li></ul>', $values);

        $nothing = (string) $this->get('/webx/widgets/showcase/suggest?'.http_build_query(['q' => '<zebra>', 'items' => $items, 'words' => $words]))->assertOk()->getContent();
        $this->assertSame('<p class="b-showcase-suggest__nothing">Nothing for “&lt;zebra&gt;”.</p>', $nothing);

        // Another site's address is not a «see all»: the demo is not an open redirect.
        $foreign = (string) $this->get('/webx/widgets/showcase/suggest?'.http_build_query(['q' => 'desk', 'items' => $items, 'to' => '//evil.example']))->getContent();
        $this->assertStringContainsString('href="/?q=desk"', $foreign);

        $this->get('/webx/widgets/showcase/suggest?q=+')->assertNoContent();
        $this->assertSame([['name' => 'Oak desk', 'price' => '€349'], ['name' => 'Linen curtains', 'price' => '']], ShowcaseController::found(ShowcaseController::items("Oak desk | €349\r\nLinen curtains\n | 5"), 'e'));
    }
}
