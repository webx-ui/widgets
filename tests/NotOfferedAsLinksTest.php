<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Links\SiteRoutes;

/**
 * The routes this package registers are machinery, and a link field of the panel offers the
 * addresses of the site a visitor opens — found on a demo site, where the list started with them.
 */
final class NotOfferedAsLinksTest extends TestCase
{
    #[Test]
    public function its_routes_are_not_offered_as_addresses_of_the_site(): void
    {
        // Registered, or an empty answer would prove nothing.
        $routes = $this->app['router']->getRoutes();
        $this->assertNotNull($routes->getByName('webx-widgets.csrf'));
        $this->assertNotNull($routes->getByName('webx-widgets.showcase.suggest'));

        $paths = array_column($this->app->make(SiteRoutes::class)->all(), 'path');

        $this->assertNotContains('/webx/widgets/csrf', $paths);
        $this->assertNotContains('/webx/widgets/showcase/suggest', $paths);
    }
}
