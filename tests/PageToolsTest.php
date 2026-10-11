<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Prose\Tables;
use WebxUi\Widgets\Widgets as Package;

/**
 * Spec §14, W5.1: the tables of prose in a scroller the server puts them in, the reveal on scroll
 * in the runtime, back to top and share — each claiming its file only where it stands.
 */
final class PageToolsTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        // What a rich text field stores — a table with no attribute — inside a block's markup.
        $router->get('/prose', static fn (): string => Blade::render('<x-layout><section class="b-text"><table><caption>Prices <em>2026</em></caption><tbody><tr><td>A</td></tr></tbody></table></section></x-layout>'));
        $router->get('/template-table', static fn (): string => Blade::render('<x-layout><table class="webx-hours__table"><tr><td>Mon</td></tr></table></x-layout>'));
        $router->get('/tools', static fn (): string => Blade::render('<x-layout><x-webx-back-to-top /><x-webx-share url="https://example.test/a" /></x-layout>'));
        $router->get('/plain', static fn (): string => Blade::render('<x-layout><p data-webx-reveal="up">Hello</p></x-layout>'));
    }

    #[Test]
    public function a_table_of_prose_goes_into_its_scroller_and_its_caption_stays_out_of_it(): void
    {
        [$html, $count] = Tables::wrap('<p>a</p><table><caption>Prices <em>2026</em></caption><thead><tr><th>A</th></tr></thead></table><p>b</p>', 'Table');

        $this->assertSame(1, $count);
        $this->assertSame(
            '<p>a</p><div class="webx-table" data-webx-table style="--webx-table-columns: 1"><div class="webx-table__caption" id="webx-table-1">Prices <em>2026</em></div>'
            .'<div class="webx-table__frame"><div class="webx-table__scroller" tabindex="0" role="region" aria-labelledby="webx-table-1">'
            .'<table class="webx-table__table" aria-labelledby="webx-table-1"><thead><tr><th>A</th></tr></thead></table>'
            .'</div></div></div><p>b</p>',
            $html,
        );
    }

    #[Test]
    public function a_table_without_a_caption_is_called_table_and_keeps_what_it_had(): void
    {
        [$html] = Tables::wrap('<TABLE style="min-width: 50px"><tr><td>1</td></tr></TABLE>', 'Tabelle');

        $this->assertStringContainsString('<div class="webx-table__scroller" tabindex="0" role="region" aria-label="Tabelle">', $html);
        $this->assertStringContainsString('<table class="webx-table__table" style="min-width: 50px"><tr><td>1</td></tr></TABLE></div></div></div>', $html);
        $this->assertStringNotContainsString('webx-table__caption', $html);

        // The columns of the first row, spans counted: the stylesheet keeps each from squeezing.
        [$spans] = Tables::wrap('<table><thead><tr><th colspan="2">Name</th><th>Price</th><th colspan=3>Sizes</th></tr></thead><tr><td>1</td></tr></table>', 'Table');
        $this->assertStringContainsString('<div class="webx-table" data-webx-table style="--webx-table-columns: 6">', $spans);
    }

    #[Test]
    public function only_the_outermost_tables_without_a_class_are_wrapped_and_text_that_mentions_one_is_not_a_table(): void
    {
        $page = '<table class="cal"><tr><td><table><tr><td>in a template</td></tr></table></td></tr></table>'
            .'<table><tr><td><table><tr><td>nested in prose</td></tr></table></td></tr></table>'
            .'<script>const t = "<table><tr><td>x</td></tr></table>"</script>'
            .'<!-- <table></table> --><template><table></table></template><textarea><table></table></textarea>'
            .'<table><tr><td>second</td></tr></table>';

        [$html, $count] = Tables::wrap($page, 'Table');

        $this->assertSame(2, $count);
        $this->assertSame(2, substr_count($html, 'data-webx-table'));
        $this->assertStringContainsString('<table class="cal"><tr><td><table><tr><td>in a template', $html);
        $this->assertStringContainsString('<div class="webx-table__scroller" tabindex="0" role="region" aria-label="Table"><table class="webx-table__table"><tr><td><table><tr><td>nested in prose</td></tr></table></td></tr></table></div></div></div>', $html);
        $this->assertStringContainsString('<script>const t = "<table><tr><td>x</td></tr></table>"</script><!-- <table></table> --><template><table></table></template><textarea><table></table></textarea>', $html);

        // A second pass changes nothing: the wrapped table has a class now.
        $this->assertSame([$html, 0], Tables::wrap($html, 'Table'));
        // An unclosed table is left as the browser will read it.
        $this->assertSame(['<table><tr><td>x', 0], Tables::wrap('<table><tr><td>x', 'Table'));
    }

    #[Test]
    public function the_page_wraps_its_tables_of_prose_and_loads_the_shadows_only_then(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = (string) $this->get('/prose')->assertOk()->getContent();
        $this->assertStringContainsString('<section class="b-text"><div class="webx-table" data-webx-table style="--webx-table-columns: 1"><div class="webx-table__caption" id="webx-table-1">Prices <em>2026</em></div>', $html);
        $this->assertMatchesRegularExpression('~<link rel="stylesheet" href="[^"]+/table\.css">~', $html);
        $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/table\.js"></script>~', $html);

        $template = (string) $this->get('/template-table')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-webx-table', $template);
        $this->assertStringNotContainsString('table.js', $template);
    }

    #[Test]
    public function the_scroller_says_table_in_the_language_of_the_page(): void
    {
        app()->setLocale('ru');

        [$html] = Tables::wrap('<table><tr><td>1</td></tr></table>', (string) __('webx-widgets::widgets.table.label'));

        $this->assertStringContainsString('aria-label="Таблица"', $html);
    }

    #[Test]
    public function the_reveal_is_the_runtimes_and_hides_nothing_on_the_server(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = (string) $this->get('/plain')->assertOk()->getContent();

        // The attribute as written: no class, no style, nothing hidden before the script runs.
        $this->assertStringContainsString('<p data-webx-reveal="up">Hello</p>', $html);
        $this->assertStringContainsString('runtime.js', $html);
        $this->assertDoesNotMatchRegularExpression('~/(?:reveal|table|share|back-to-top)\.js~', $html);
        $this->assertContains('reveal', Package::RUNTIME);
        $this->assertStringContainsString('.webx-reveal.is-pending', (string) file_get_contents(Package::path().'/dist/runtime.css'));
    }

    #[Test]
    public function back_to_top_is_a_link_to_the_top_with_its_words_and_its_corner(): void
    {
        $html = Blade::render('<x-webx-back-to-top />');

        $this->assertStringContainsString('<a class="webx-back-to-top webx-back-to-top--bottom-end" href="#top" data-webx-back-to-top="{&quot;after&quot;:2}" aria-label="Back to top">', $html);
        $this->assertStringContainsString('<svg class="webx-icon webx-back-to-top__icon"', $html);
        $this->assertContains('back-to-top', Widgets::claimed());

        app()->setLocale('de');
        $start = Blade::render('<x-webx-back-to-top corner="bottom-start" :after="1.5" />');
        $this->assertStringContainsString('webx-back-to-top--bottom-start', $start);
        $this->assertStringContainsString('{&quot;after&quot;:1.5}', $start);
        $this->assertStringContainsString('aria-label="Nach oben"', $start);

        $this->assertStringContainsString('aria-label="Up"', Blade::render('<x-webx-back-to-top label="Up" />'));
    }

    #[Test]
    public function share_is_plain_links_to_each_network_with_the_address_and_the_title_encoded(): void
    {
        $html = Blade::render('<x-webx-share url="https://example.test/a?b=1" title="Ten & one" />');

        $this->assertStringContainsString('role="group" aria-label="Share"', $html);
        $this->assertStringContainsString('href="https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fexample.test%2Fa%3Fb%3D1" target="_blank" rel="noopener" aria-label="Share on Facebook"', $html);
        $this->assertStringContainsString('href="https://x.com/intent/post?url=https%3A%2F%2Fexample.test%2Fa%3Fb%3D1&amp;text=Ten%20%26%20one"', $html);
        $this->assertStringContainsString('href="https://wa.me/?text=Ten%20%26%20one%20https%3A%2F%2Fexample.test%2Fa%3Fb%3D1"', $html);
        $this->assertStringContainsString('aria-label="Share on LinkedIn"', $html);
        $this->assertStringContainsString('aria-label="Share on Telegram"', $html);
        // E-mail opens the mail program in place, not a tab.
        $this->assertStringContainsString('<a class="webx-share__link webx-share__link--email" href="mailto:?subject=Ten%20%26%20one&amp;body=https%3A%2F%2Fexample.test%2Fa%3Fb%3D1" aria-label="Send by email">', $html);
        $this->assertStringContainsString('<button type="button" class="webx-share__link webx-share__link--copy" data-webx-share-copy aria-label="Copy link">', $html);
        $this->assertStringContainsString('<button type="button" class="webx-share__native" data-webx-share-native hidden>', $html);
        $this->assertStringContainsString('&quot;copied&quot;:&quot;Link copied&quot;', $html);
        // No script of a network's, no picture from one.
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertContains('share', Widgets::claimed());
    }

    #[Test]
    public function share_takes_its_networks_in_order_and_the_page_address_by_default(): void
    {
        $this->get('/tools');
        app()->setLocale('ru');

        $html = Blade::render('<x-webx-share :networks="[\'telegram\', \'email\', \'telegram\']" label="Отправить" />');

        $this->assertSame(2, substr_count($html, 'class="webx-share__link '));
        $this->assertLessThan(strpos($html, 'mailto:'), (int) strpos($html, 't.me/share'));
        $this->assertStringContainsString('aria-label="Поделиться в Telegram"', $html);
        $this->assertStringContainsString('aria-label="Отправить"', $html);
        $this->assertStringNotContainsString('webx-share__link--copy', $html);
        // The address of the page it is on, without a query.
        $this->assertStringContainsString('url=http%3A%2F%2Flocalhost', $html);
    }

    #[Test]
    public function the_page_loads_the_files_of_back_to_top_and_share_where_they_stand(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = (string) $this->get('/tools')->assertOk()->getContent();

        foreach (['back-to-top', 'share'] as $widget) {
            $this->assertMatchesRegularExpression("~<link rel=\"stylesheet\" href=\"[^\"]+/{$widget}\.css\">~", $html);
            $this->assertMatchesRegularExpression("~<script type=\"module\" src=\"[^\"]+/{$widget}\.js\"></script>~", $html);
        }
    }

    #[Test]
    public function a_typo_says_what_it_takes(): void
    {
        foreach ([
            '<x-webx-back-to-top corner="top-end" />' => 'bottom-end or bottom-start',
            '<x-webx-back-to-top :after="-1" />' => '0 to 20',
            '<x-webx-share :networks="[\'myspace\']" />' => '"myspace" is none of',
            '<x-webx-share url="/a" />' => 'an absolute http(s) address',
        ] as $template => $message) {
            try {
                Blade::render($template);
                $this->fail("{$template} rendered.");
            } catch (Throwable $error) {
                $error = $error instanceof ViewException ? ($error->getPrevious() ?? $error) : $error;
                $this->assertInstanceOf(InvalidArgumentException::class, $error, $template);
                $this->assertStringContainsString($message, $error->getMessage(), $template);
            }
        }
    }
}
