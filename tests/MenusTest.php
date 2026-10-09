<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Widgets;

/**
 * The mobile menu and the header (spec §6.1, §6.2): the markup is the contract the runtime and
 * a theme read — the classes, the `data-*`, what a visitor without JavaScript gets.
 */
class MenusTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private static function tree(): array
    {
        return [
            ['label' => 'About', 'url' => '/about'],
            ['label' => 'Services', 'url' => '/services', 'children' => [
                ['label' => 'Freight', 'url' => '/services/freight', 'current' => true],
                ['label' => 'Storage', 'url' => '/services/storage', 'children' => [
                    ['label' => 'Cold', 'url' => '/services/storage/cold'],
                ]],
            ]],
            ['label' => 'Docs', 'url' => 'https://example.com/docs', 'variant' => 'button'],
        ];
    }

    #[Test]
    public function the_mobile_menu_is_a_trigger_and_a_dialog_of_three_zones_that_works_without_javascript(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-webx-mobile-menu side="left" :breakpoint="720">
                <x-slot:top><a href="/">Logo</a></x-slot:top>
                <p>Body</p>
                <x-slot:bottom><a href="tel:+100">+100</a></x-slot:bottom>
            </x-webx-mobile-menu>
            BLADE);

        $this->assertStringContainsString('data-webx-mobile-menu="webx-mobile-menu" data-side="left" data-close-on-navigate="true" data-history="true"', $html);
        $this->assertStringContainsString('<a id="webx-mobile-menu-trigger" class="webx-mobile-menu__trigger" href="#webx-mobile-menu" data-webx-dialog="webx-mobile-menu"', $html);
        $this->assertStringContainsString('aria-label="Open menu"', $html);
        $this->assertStringContainsString('<dialog id="webx-mobile-menu" class="webx-mobile-menu__panel webx-mobile-menu__panel--left" aria-label="Menu">', $html);
        $this->assertStringContainsString('@container (min-width: 720px) { #webx-mobile-menu-trigger { display: none; } }', $html);
        $this->assertMatchesRegularExpression('~<div class="webx-mobile-menu__top">\s*<a href="/">Logo</a>\s*<a class="webx-mobile-menu__close" href="#" data-webx-dialog-close aria-label="Close menu">~', $html);
        $this->assertStringContainsString('<div class="webx-mobile-menu__body"><p>Body</p></div>', $html);
        $this->assertStringContainsString('<div class="webx-mobile-menu__bottom"><a href="tel:+100">+100</a></div>', $html);
        $this->assertSame(['mobile-menu'], app(Widgets::class)->claimed());
    }

    #[Test]
    public function the_mobile_menu_keeps_its_close_button_and_drops_an_empty_bottom(): void
    {
        $html = Blade::render('<x-webx-mobile-menu :breakpoint="null" :history="false" :close-on-navigate="false">x</x-webx-mobile-menu>');

        $this->assertStringContainsString('webx-mobile-menu__close', $html);
        $this->assertStringNotContainsString('webx-mobile-menu__bottom', $html);
        $this->assertStringNotContainsString('<style>', $html);
        $this->assertStringContainsString('data-close-on-navigate="false" data-history="false"', $html);
    }

    #[Test]
    public function a_mobile_menu_from_nowhere_is_a_typo(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('A mobile menu opens from one of');

        Blade::render('<x-webx-mobile-menu side="middle">x</x-webx-mobile-menu>');
    }

    #[Test]
    public function the_mobile_navigation_opens_the_visitors_branch_and_marks_the_page(): void
    {
        $html = Blade::render('<x-webx-mobile-menu.nav :items="$items" />', ['items' => self::tree()]);

        $this->assertStringContainsString('<nav aria-label="Main" data-webx-mobile-nav="accordion" class="webx-mobile-nav webx-mobile-nav--accordion">', $html);
        $this->assertMatchesRegularExpression('~<details class="webx-mobile-nav__group"\s+open>\s*<summary class="webx-mobile-nav__toggle">Services</summary>~', $html);
        // The parent's own page is the branch's first entry; Storage is not on the way, so closed.
        $this->assertStringContainsString('<a class="webx-mobile-nav__link" href="/services">Services</a>', $html);
        $this->assertStringContainsString('<a class="webx-mobile-nav__link" href="/services/freight" aria-current="page">Freight</a>', $html);
        $this->assertMatchesRegularExpression('~<details class="webx-mobile-nav__group"\s*>\s*<summary class="webx-mobile-nav__toggle">Storage</summary>~', $html);
        $this->assertStringNotContainsString('webx-mobile-nav__back', $html);
    }

    #[Test]
    public function in_drill_a_branch_has_back_and_its_name_on_top(): void
    {
        app()->setLocale('de');
        $html = Blade::render('<x-webx-mobile-menu.nav :items="$items" mode="drill" />', ['items' => self::tree()]);

        $this->assertStringContainsString('class="webx-mobile-nav webx-mobile-nav--drill"', $html);
        $this->assertStringContainsString('<div class="webx-mobile-nav__level webx-mobile-nav__level--drill">', $html);
        $this->assertStringContainsString('<button type="button" class="webx-mobile-nav__back" data-webx-mobile-nav-back>Zurück</button>', $html);
        $this->assertStringContainsString('<a class="webx-mobile-nav__title" href="/services">Services</a>', $html);
        $this->assertStringNotContainsString('<a class="webx-mobile-nav__link" href="/services">', $html);
    }

    #[Test]
    public function the_header_navigation_draws_dropdowns_with_a_toggle_beside_the_link(): void
    {
        $html = Blade::render('<x-webx-header.nav :items="$items" />', ['items' => self::tree()]);

        $this->assertStringContainsString('<ul data-webx-header-nav="" class="webx-header-nav">', $html);
        $this->assertStringContainsString('<li class="webx-header-nav__item is-current">', $html);
        $this->assertMatchesRegularExpression('~<a class="webx-header-nav__link is-current" href="/services">Services</a>\s*<button type="button" class="webx-header-nav__toggle" aria-expanded="false" aria-controls="(webx-header-nav-\d+-1)" aria-label="Submenu: Services" data-webx-header-nav-toggle></button>\s*<ul class="webx-header-nav__dropdown" id="\1">~', $html);
        $this->assertStringContainsString('<a class="webx-header-nav__sublink is-current" href="/services/freight" aria-current="page">Freight</a>', $html);
        $this->assertStringContainsString('<ul class="webx-header-nav__sublist">', $html);
        $this->assertStringContainsString('<a class="webx-header-nav__link webx-header-nav__link--button" href="https://example.com/docs">Docs</a>', $html);
    }

    #[Test]
    public function an_item_named_in_mega_opens_the_themes_panel(): void
    {
        $this->app['view']->addNamespace('fixture', self::fixture('views'));

        $html = Blade::render('<x-webx-header.nav :items="$items" :mega="[\'services\' => \'fixture::mega\']" />', ['items' => self::tree()]);

        $this->assertStringContainsString('<li class="webx-header-nav__item webx-header-nav__item--mega is-current">', $html);
        $this->assertMatchesRegularExpression('~<div class="webx-header-nav__mega" id="webx-header-nav-\d+-1">Mega of Services with 2</div>~', $html);
        $this->assertStringNotContainsString('webx-header-nav__dropdown', $html);
    }

    #[Test]
    public function the_header_builds_its_mobile_menu_out_of_its_own_parts(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-webx-header sticky="hide-on-scroll" overlay mode="drill">
                <x-slot:topbar>Open 9–18</x-slot:topbar>
                <x-slot:brand><a href="/" class="logo">Harbor</a></x-slot:brand>
                <x-webx-header.nav :items="$items" />
                <x-slot:actions><a href="tel:+100">Call</a></x-slot:actions>
                <x-slot:mobile-bottom><a href="/privacy">Privacy</a></x-slot:mobile-bottom>
            </x-webx-header>
            BLADE, ['items' => self::tree()]);

        $this->assertStringStartsWith('<a class="webx-header__skip" href="#content">Skip to content</a>', trim($html));
        $this->assertStringContainsString('<header id="webx-header" data-webx-header="" data-collapse="auto" data-sticky="hide-on-scroll" class="webx-header webx-header--sticky webx-header--hide-on-scroll webx-header--overlay">', $html);
        $this->assertStringContainsString('<div class="webx-header__topbar">Open 9–18</div>', $html);
        $this->assertStringContainsString('<div class="webx-header__brand"><a href="/" class="logo">Harbor</a></div>', $html);
        $this->assertStringContainsString('<nav class="webx-header__nav" aria-label="Main"><ul data-webx-header-nav', $html);
        $this->assertStringContainsString('<div class="webx-header__actions"><a href="tel:+100">Call</a></div>', $html);
        $this->assertStringContainsString('@container webx-header (width < 960px)', $html);

        // The menu: brand on top, the same tree as a mobile navigation, actions then the rest at the bottom.
        $this->assertStringContainsString('class="webx-mobile-menu__trigger webx-header__trigger" href="#webx-mobile-menu"', $html);
        $this->assertStringContainsString('<div class="webx-mobile-menu__brand"><a href="/" class="logo">Harbor</a></div>', $html);
        $this->assertStringContainsString('class="webx-mobile-nav webx-mobile-nav--drill"', $html);
        $this->assertStringContainsString('<div class="webx-mobile-menu__bottom"><a href="tel:+100">Call</a><a href="/privacy">Privacy</a></div>', $html);
        $this->assertStringNotContainsString('@container (min-width', $html);
        $this->assertSame(['header', 'mobile-menu'], app(Widgets::class)->claimed());
    }

    #[Test]
    public function a_header_that_never_collapses_has_no_menu_and_one_without_skip_has_no_link(): void
    {
        $html = Blade::render('<x-webx-header collapse="never" :skip="false"><a href="/a">A</a></x-webx-header>');

        $this->assertStringNotContainsString('webx-mobile-menu', $html);
        $this->assertStringNotContainsString('webx-header__skip', $html);
        $this->assertStringNotContainsString('<style>', $html);
        $this->assertStringContainsString('<nav class="webx-header__nav" aria-label="Main"><a href="/a">A</a></nav>', $html);
    }

    #[Test]
    public function a_header_with_markup_of_its_own_repeats_it_in_the_menu_and_folds_at_a_number(): void
    {
        $html = Blade::render('<x-webx-header collapse="1100" id="top"><a href="/a">A</a></x-webx-header>');

        $this->assertStringContainsString('data-collapse="1100"', $html);
        $this->assertStringContainsString('@container webx-header (width < 1100px) { html:not(.webx-js) #top .webx-header__nav', $html);
        $this->assertStringContainsString('<div class="webx-mobile-menu__body"><a href="/a">A</a></div>', $html);
    }

    #[Test]
    public function a_mobile_slot_replaces_the_menu_the_header_would_build(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-webx-header>
                <x-webx-header.nav :items="$items" />
                <x-slot:mobile><x-webx-mobile-menu id="own" :breakpoint="null" trigger-class="webx-header__trigger">Own</x-webx-mobile-menu></x-slot:mobile>
            </x-webx-header>
            BLADE, ['items' => self::tree()]);

        $this->assertStringContainsString('<dialog id="own"', $html);
        $this->assertSame(1, substr_count($html, '<dialog'));
        $this->assertStringNotContainsString('webx-mobile-nav', $html);
    }

    #[Test]
    public function a_header_refuses_what_it_cannot_do(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('A header collapses');

        Blade::render('<x-webx-header collapse="sometimes">x</x-webx-header>');
    }

    #[Test]
    public function an_array_item_is_current_on_its_own_page(): void
    {
        $this->get('/services/freight');
        $html = Blade::render('<x-webx-header.nav :items="$items" />', ['items' => [
            ['label' => 'Freight', 'url' => 'http://localhost/services/freight'],
            ['label' => 'Other', 'url' => 'https://elsewhere.test/services/freight'],
        ]]);

        $this->assertStringContainsString('href="http://localhost/services/freight" aria-current="page"', $html);
        $this->assertStringNotContainsString('href="https://elsewhere.test/services/freight" aria-current', $html);
    }
}
