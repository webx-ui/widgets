<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Tests\Fixtures\BlockPage;
use WebxUi\Widgets\Tests\Fixtures\BlockPageHandler;
use WebxUi\Widgets\Widgets as WidgetsService;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * A widget in a block looks in every preview of `module-blocks` as it does on the page: the
 * files it claims come with it however the block is drawn (§4).
 *
 * A page and a region's preview are pages, finished like any other. The block editor's stage
 * and a block swapped into the preview after a field changed are not: the block is drawn alone
 * and answered as JSON, into a page printed before it — and its map used to come without the
 * map's stylesheet and script. `blocks_render` is that drawing for an agent.
 */
final class BlockPreviewTest extends TestCase
{
    use MigratesOnce;

    /** The folder the widgets' files are published to, as the page links them: a path, whatever the host. */
    private string $base;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            BlocksServiceProvider::class,
            MediaServiceProvider::class,
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
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
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-seo.cache.enabled', false);
        $app['config']->set('webx-blocks.regions', ['footer' => ['title' => 'Footer']]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->migrateOnce();

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->blocks();
            $table->draft();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('webx:theme:sync')->assertSuccessful();
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['widgets']])->assertSuccessful();

        $layer = app(BottomLayers::class)->get(WidgetsService::NAME);
        $this->assertNotNull($layer);
        $this->base = '/themes/webx-ui/widgets/'.app(ThemeAssets::class)->current($layer);

        // The map of the block's sample is the primary address of the contacts, and the hours are
        // printed only when there are some.
        app(Settings::class)->save([
            'contacts.addresses' => [['address' => ['en' => '1 Example Street, Sample City'], 'latitude' => 12.34, 'longitude' => -34.56, 'primary' => true]],
            'contacts.hours' => [['days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'opens' => '09:00', 'closes' => '19:00']],
        ]);

        $hours = Block::query()->create(['slug' => 'opening', 'title' => 'Opening hours']);
        $hours->saveVersion(['template' => '<section class="b-opening" data-wx-block="opening"><x-webx-hours /></section>', 'schema' => []]);
        $hours->publish();

        $this->app->make(RouteTypes::class)->register(new RouteType(
            type: 'page',
            model: BlockPage::class,
            formatter: Slug::class,
            handler: BlockPageHandler::class,
        ));

        // Publishing the offered blocks drew their samples: claims no request of the test made.
        app(WidgetsService::class)->flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    #[Test]
    public function the_stage_gets_the_files_of_the_widgets_its_block_draws(): void
    {
        $map = $this->stage('map');

        $this->assertStringContainsString('data-webx-map=', $map['html']);
        $this->assertStringContainsString("{$this->base}/map.css\">", implode("\n", $map['assets']['head']));
        $this->assertStringContainsString("{$this->base}/map.js\"></script>", implode("\n", $map['assets']['body']));
        $this->assertStringContainsString("{$this->base}/runtime.css\">", implode("\n", $map['assets']['head']), 'the panel skips what the page has');

        $hours = $this->stage('opening');

        $this->assertStringContainsString('webx-hours', $hours['html']);
        $this->assertStringContainsString("{$this->base}/contacts.css\">", implode("\n", $hours['assets']['head']));
        $this->assertStringContainsString("{$this->base}/contacts.js\"></script>", implode("\n", $hours['assets']['body']));
        $this->assertStringNotContainsString("{$this->base}/map.css\">", implode("\n", $hours['assets']['head']), 'a request claims for itself');
    }

    #[Test]
    public function blocks_render_answers_with_them_too(): void
    {
        $tool = $this->app->make(ToolRegistry::class)->tool('blocks_render')->tool;
        $answer = ($tool->handler)(['slug' => 'map']);

        $this->assertIsArray($answer);
        $this->assertStringContainsString('data-webx-map=', $answer['html']);
        $this->assertStringContainsString("{$this->base}/map.css\">", implode("\n", $answer['assets']['head']));
        $this->assertStringContainsString("{$this->base}/map.js\"></script>", implode("\n", $answer['assets']['body']));
    }

    #[Test]
    public function the_preview_of_a_page_and_of_a_region_load_them_as_the_page_does(): void
    {
        $page = BlockPage::query()->create(['slug' => 'about', 'title' => 'About']);
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'map', 'values' => []]]]);
        $page->publish();

        $live = (string) $this->get('/about')->assertOk()->getContent();
        $preview = (string) $this->get(Preview::url($page))->assertOk()->getContent();

        foreach ([$live, $preview] as $html) {
            $this->assertSame(1, substr_count($html, "{$this->base}/map.css\">"));
            $this->assertSame(1, substr_count($html, "{$this->base}/map.js\"></script>"));
        }

        $this->assertStringNotContainsString('contacts.css', $preview);

        $region = Region::query()->firstOrNew(['name' => 'footer']);
        $region->saveDraft(['blocks' => [['key' => 'f1', 'type' => 'opening', 'values' => []]]]);

        $footer = (string) $this->get(Preview::regionUrl('footer', at: '/about'))->assertOk()->getContent();

        $this->assertStringContainsString('webx-hours', $footer, 'the draft of the region, on the page');
        $this->assertStringContainsString("{$this->base}/contacts.css\">", $footer);
        $this->assertStringContainsString("{$this->base}/contacts.js\"></script>", $footer);
        $this->assertStringContainsString("{$this->base}/map.css\">", $footer);
    }

    /**
     * The block as the editor's stage asks for it: drawn on its sample.
     *
     * @return array{html: string, assets: array{head: list<string>, body: list<string>}}
     */
    private function stage(string $slug): array
    {
        $block = Block::query()->where('slug', $slug)->firstOrFail();

        $data = $this->actingAs($this->editor(), 'cms')
            ->postJson("/api/cms/blocks/{$block->getKey()}/render")
            ->assertOk()
            ->json('data');

        $this->assertIsArray($data);

        /** @var array{html: string, assets: array{head: list<string>, body: list<string>}} $data */
        return $data;
    }

    private function editor(): CmsUser
    {
        $found = CmsUser::query()->where('email', 'editor@example.test')->first();

        if ($found instanceof CmsUser) {
            return $found;
        }

        $user = CmsUser::query()->create([
            'name' => 'Editor',
            'email' => 'editor@example.test',
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => 'editor', 'name' => 'Editor', 'permissions' => ['blocks.view', 'blocks.manage']]);
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
