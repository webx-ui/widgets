<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use WebxUi\Routing\RouteHandler;

/**
 * The page in the theme's layout, a footer region under its blocks: the address and the
 * preview alike, as a content module answers both.
 */
final class BlockPageHandler implements RouteHandler
{
    public function handle(Request $request, object $entity, string $tail): BaseResponse
    {
        /** @var BlockPage $entity */
        $html = Blade::render(
            '<x-layout><main>{{ $content }}</main><x-webx-blocks::region name="footer" /></x-layout>',
            ['content' => $entity->renderBlocks()],
        );

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }
}
