<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Crawl\PageReaders;
use WebxUi\Audit\Fixes\AuditFixes;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\Contracts\HeadPart;
use WebxUi\Widgets\Audit\BannerOff;
use WebxUi\Widgets\Audit\BannerOn;
use WebxUi\Widgets\Audit\BeforeConsent;
use WebxUi\Widgets\Audit\ContactBoth;
use WebxUi\Widgets\Audit\LightboxSize;
use WebxUi\Widgets\Audit\SliderPause;
use WebxUi\Widgets\Audit\WaitForConsent;
use WebxUi\Widgets\Audit\WidgetsPageReader;
use WebxUi\Widgets\Video\Posters;
use WebxUi\Widgets\Video\VideoProviders;
use WebxUi\Widgets\View\Components\ConsentGate;
use WebxUi\Widgets\View\Components\ConsentLink;
use WebxUi\Widgets\View\Components\ContactBar;
use WebxUi\Widgets\View\Components\ContactButton;
use WebxUi\Widgets\View\Components\Dialog;
use WebxUi\Widgets\View\Components\Dropdown;
use WebxUi\Widgets\View\Components\Header;
use WebxUi\Widgets\View\Components\HeaderNav;
use WebxUi\Widgets\View\Components\Icon;
use WebxUi\Widgets\View\Components\LanguageSwitcher;
use WebxUi\Widgets\View\Components\Lightbox;
use WebxUi\Widgets\View\Components\Map;
use WebxUi\Widgets\View\Components\MobileMenu;
use WebxUi\Widgets\View\Components\MobileMenuNav;
use WebxUi\Widgets\View\Components\OpeningHours;
use WebxUi\Widgets\View\Components\Phones;
use WebxUi\Widgets\View\Components\Slide;
use WebxUi\Widgets\View\Components\Slider;
use WebxUi\Widgets\View\Components\Socials;
use WebxUi\Widgets\View\Components\Tabs;
use WebxUi\Widgets\View\Components\TabsPanel;
use WebxUi\Widgets\View\Components\Video;
use WebxUi\Widgets\View\HeaderNavigation;
use WebxUi\Widgets\View\Sliders;

/**
 * The widgets as the bottom layer of the theme chain (spec §3, THEMES §7.1, §11):
 *
 *     views    webx-widgets::components.<name>, overridden in <layer>/views/vendor/webx-widgets/
 *     words    webx-widgets::widgets.<name>.*
 *     files    dist/, published by webx:theme:sync next to the themes'
 *     head     first in the cascade, before any theme's stylesheet
 */
class WidgetsServiceProvider extends ServiceProvider
{
    /** `module-settings`, named rather than imported: the package does not require it. */
    private const string SETTINGS = 'WebxUi\Settings\Settings';

    private const string SETTINGS_SAVED = 'WebxUi\Settings\Events\SettingsSaved';

    private const string ASK_AGAIN = 'consent.ask-again';

    /** What `webx:blocks:offered --module=` calls the blocks this package offers. */
    public const string OFFERS = 'widgets';

    public function register(): void
    {
        // Scoped: what one request claimed must not load on the next one of a long-lived worker.
        $this->app->scoped(Widgets::class);
        $this->app->scoped(HeaderNavigation::class);
        $this->app->scoped(Sliders::class);
        $this->app->scoped(FormDialogs::class);
        // Scoped too: the answer is read once from the request it came with.
        $this->app->scoped(Consent::class);
        // Scoped: a request puts off the fetch of each missing poster once.
        $this->app->scoped(Posters::class);
        $this->app->singleton(VideoProviders::class);
        $this->app->tag([Widgets::class], HeadPart::TAG);

        $this->mergeConfigFrom(Widgets::path().'/config/webx-widgets.php', 'webx-widgets');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(Widgets::path().'/resources/views', 'webx-widgets');
        $this->loadTranslationsFrom(Widgets::path().'/lang', 'webx-widgets');

        // The banner's script writes the answer and the server reads it back: a cookie the
        // framework encrypted would be one neither of them could read.
        EncryptCookies::except(Consent::COOKIE);

        $this->registerConsentSettings();
        $this->offerBlocks();
        $this->registerAudit();

        $this->app->make(BottomLayers::class)->add(Widgets::NAME, Widgets::path());

        Blade::component('webx-dialog', Dialog::class);
        Blade::component('webx-tabs', Tabs::class);
        Blade::component('webx-tabs.panel', TabsPanel::class);
        Blade::component('webx-mobile-menu', MobileMenu::class);
        Blade::component('webx-mobile-menu.nav', MobileMenuNav::class);
        Blade::component('webx-header', Header::class);
        Blade::component('webx-header.nav', HeaderNav::class);
        Blade::component('webx-consent', ConsentGate::class);
        Blade::component('webx-consent-link', ConsentLink::class);
        Blade::component('webx-dropdown', Dropdown::class);
        Blade::component('webx-icon', Icon::class);
        Blade::component('webx-phones', Phones::class);
        Blade::component('webx-hours', OpeningHours::class);
        Blade::component('webx-contact-button', ContactButton::class);
        Blade::component('webx-contact-bar', ContactBar::class);
        Blade::component('webx-socials', Socials::class);
        Blade::component('webx-language-switcher', LanguageSwitcher::class);
        Blade::component('webx-slider', Slider::class);
        Blade::component('webx-slide', Slide::class);
        Blade::component('webx-lightbox', Lightbox::class);
        Blade::component('webx-video', Video::class);
        Blade::component('webx-map', Map::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([Widgets::path().'/config/webx-widgets.php' => config_path('webx-widgets.php')], 'webx-widgets-config');
        }

        // The route's response, before the session middleware saves the session and drops the
        // flash a form in a dialog prints (§6.4). Prepared twice — inside the middleware and
        // outside it — and the dialogs go in on the first.
        $this->app->make(Dispatcher::class)->listen(ResponsePrepared::class, function (ResponsePrepared $event): void {
            $response = $event->response;

            if ($response instanceof Response && self::isPage($response)) {
                $response->setContent($this->app->make(Widgets::class)->withDialogs((string) $response->getContent()));
            }
        });

        // The whole page has rendered, the header and the footer too: every claim is known.
        $this->app->make(Dispatcher::class)->listen(RequestHandled::class, function (RequestHandled $event): void {
            $widgets = $this->app->make(Widgets::class);
            $response = $event->response;

            if ($response instanceof Response && self::isPage($response)) {
                $response->setContent($widgets->finish((string) $response->getContent()));
            }

            $widgets->flush();
            $this->app->make(Consent::class)->flush();
        });
    }

    /**
     * The "Cookie" tab of the settings screen (§9.5), patched in from here: the panel and the
     * settings are not required, and a patch on a screen nobody registered is never applied —
     * a site without `module-settings` keeps the config. Its keys are settings like any other,
     * so `settings_get` / `settings_set` reach them over MCP.
     */
    private function registerConsentSettings(): void
    {
        if (! class_exists(ScreenRegistry::class)) {
            return;
        }

        $this->app->make(ScreenRegistry::class)->extend('settings.index', Widgets::path().'/resources/screens/settings.consent.json');

        // "Ask everyone again" is a switch saved with the tab: the save raises the version of
        // the policy — every answer given so far is to an older one — and turns the switch off.
        $this->app->make(Dispatcher::class)->listen(self::SETTINGS_SAVED, function (object $event): void {
            $settings = $this->app->make(self::SETTINGS);

            if (in_array(self::ASK_AGAIN, (array) ($event->keys ?? []), true) && (bool) ($settings->raw()[self::ASK_AGAIN] ?? false)) {
                $settings->save([
                    'consent.version' => (int) $this->app->make(Consent::class)->version() + 1,
                    self::ASK_AGAIN => false,
                ]);
            }
        });
    }

    /**
     * The blocks `gallery`, `logos` and `video` (§15.1), offered to `module-blocks` when the site
     * has it: the slider, the lightbox and the video are what they are made of, and a site would
     * otherwise put them together again in a block of its own. Offered, not installed —
     * `webx:blocks:offered --install --module=widgets` (and `webx:setup`) puts them on the site
     * once, and a type the site already has by that name is never touched.
     *
     * Their pictures and videos are fields of the media library, so without `module-media` there
     * is nothing to offer: a type with a field nobody registered would not survive the panel's save.
     */
    private function offerBlocks(): void
    {
        if (! class_exists(BlockOffers::class) || ! $this->app->bound(BlockOffers::class) || ! class_exists(MediaServiceProvider::class)) {
            return;
        }

        $this->app->make(BlockOffers::class)->offer(self::OFFERS, Widgets::path().'/resources/blocks');
    }

    /**
     * The checks of §15.2, when the site has `module-audit`: what loads before consent, the banner
     * switched off with something third-party on the site, a lightbox link without the picture's
     * size, a moving slider without its pause button, both quick-contact widgets on one page. The
     * page's HTML is not kept by the audit, so the reader takes what they need while it parses.
     */
    private function registerAudit(): void
    {
        if (! class_exists(AuditChecks::class) || ! class_exists(PageReaders::class)) {
            return;
        }

        $this->app->make(PageReaders::class)->register(new WidgetsPageReader);

        $checks = $this->app->make(AuditChecks::class);

        foreach ([new BeforeConsent, new BannerOff, new LightboxSize, new SliderPause, new ContactBoth] as $check) {
            $checks->register($check);
        }

        $fixes = $this->app->make(AuditFixes::class);
        $fixes->register($this->app->make(WaitForConsent::class));
        $fixes->register(new BannerOn);
    }

    /** HTML with the marker: JSON could carry a rendered page inside a string, and must stay JSON. */
    private static function isPage(Response $response): bool
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse || $response instanceof JsonResponse) {
            return false;
        }

        $type = (string) $response->headers->get('Content-Type', '');
        $content = $response->getContent();

        return ($type === '' || str_contains($type, 'html')) && is_string($content) && str_contains($content, Widgets::MARKER);
    }
}
