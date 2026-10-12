<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Forms;

use Illuminate\Http\Response;

/**
 * `GET {path}/csrf` — a fresh token for a form whose page was cached with an old one (spec §17.3).
 *
 * Nothing in the body: the `web` group around it starts the session — a new one when the old has
 * expired — and sets the XSRF-TOKEN cookie on the way out, which is what the form's script reads
 * and sends back as `X-XSRF-TOKEN`. Never cached, or the next visitor would get this one's token.
 */
final class CsrfController
{
    public function __invoke(): Response
    {
        return new Response('', 204, ['Cache-Control' => 'no-store, private']);
    }
}
