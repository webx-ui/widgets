<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Forms;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

/**
 * The answer of every form of the site (spec §17.4), in one line of its controller:
 *
 *     return FormResponse::message(__('Thank you'), '<p>We will call you back.</p>');
 *     return FormResponse::redirect(route('account'));
 *     return FormResponse::html(view('checkout.step-2', $data));
 *     return FormResponse::ok();
 *
 * To a request that wants JSON — `<x-webx-form>` sending in place — it is
 * `{ ok, heading, message, redirect, html }`, and the form goes there, shows the message, puts the
 * HTML in place of its fields or only says `webx:form:success`. To a plain POST — the same form
 * without JavaScript — it is a redirect: to the address, or back to the page with the message or
 * the HTML in the session, where the form that was sent prints them.
 *
 * The message is HTML and printed as it is: escape what a visitor wrote into it.
 */
final class FormResponse implements Responsable
{
    /** The session key the answer comes back to the page under. */
    public const string FLASH = 'webx-form';

    /** The hidden input that says which form of the page was sent. */
    public const string FORM = '_webx_form';

    private function __construct(
        private readonly ?string $heading = null,
        private readonly ?string $message = null,
        private readonly ?string $redirect = null,
        private readonly Htmlable|Renderable|string|null $html = null,
    ) {}

    /** Shown in place of nothing, under the form's fields: a request, a subscription. */
    public static function message(?string $heading, ?string $html = null): self
    {
        return new self(heading: $heading, message: $html);
    }

    /** Off to another page: a sign-in, an order, the payment. */
    public static function redirect(string $url): self
    {
        return new self(redirect: $url);
    }

    /** In place of the form's fields: the next step of a checkout. */
    public static function html(Htmlable|Renderable|string $view): self
    {
        return new self(html: $view);
    }

    /** Nothing to show: the page's own script listens to `webx:form:success`. */
    public static function ok(): self
    {
        return new self;
    }

    /**
     * @return array{ok: true, heading: ?string, message: ?string, redirect: ?string, html: ?string}
     */
    public function toArray(): array
    {
        return [
            'ok' => true,
            'heading' => $this->heading,
            'message' => $this->message,
            'redirect' => $this->redirect,
            'html' => match (true) {
                $this->html instanceof Htmlable => $this->html->toHtml(),
                $this->html instanceof Renderable => $this->html->render(),
                default => $this->html,
            },
        ];
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $answer = $this->toArray();

        if ($request->expectsJson()) {
            return new JsonResponse($answer);
        }

        if ($answer['redirect'] !== null) {
            return Redirect::to($answer['redirect']);
        }

        // Back to the page, where the form of this name prints what it was answered.
        return Redirect::back()->with(self::FLASH, [
            'form' => is_string($form = $request->input(self::FORM)) ? $form : null,
            'heading' => $answer['heading'],
            'message' => $answer['message'],
            'html' => $answer['html'],
        ]);
    }
}
