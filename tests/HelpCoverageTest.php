<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\AssertsHelp;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * Every screen this package draws or patches is explained, field by field (WEBX_UI_PANEL_HELP.md §6).
 */
final class HelpCoverageTest extends TestCase
{
    use AssertsHelp;

    /**
     * The panel and the settings too: the tabs of this package are patches of somebody else's screen,
     * and without its owner nothing would hold their descriptions.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), LocalizationServiceProvider::class, AdminServiceProvider::class, MediaServiceProvider::class, SettingsServiceProvider::class];
    }

    #[Test]
    public function every_screen_and_field_is_explained(): void
    {
        $this->assertScreensHaveHelp();
    }
}
