<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Widgets\Captcha as SiteCaptcha;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-captcha action="checkout">` — the site's captcha inside a `<x-webx-form>`: the widget,
 * the provider's script once per page, and the place for the server's word about it.
 *
 * A checkbox — reCAPTCHA v2 or Turnstile — is drawn by the provider's own script. An Invisible
 * reCAPTCHA, a v3 one and an invisible Turnstile are run by captcha.js when the form is sent,
 * holding it with the form engine's `detail.wait`; without JavaScript they are refused, and told
 * why. Every kind posts its answer under the provider's name, which `Captcha::check()` verifies.
 *
 * `data-webx-field="captcha"`: a refusal filed under `captcha` is put here by the form's script,
 * and by the page that comes back without JavaScript (`refusal`). Nothing is printed when the site
 * has no provider or no site key for it — the log says which.
 */
final class Captcha extends Component
{
    /**
     * @param  string  $action  what the form is, for a v3 token: one asked for another form is refused
     * @param  string|null  $provider  a provider of the form's own (the inbox's of before); null — the site's
     * @param  list<string>  $refusal  the refusal of a submission that came back without JavaScript
     */
    public function __construct(
        private readonly SiteCaptcha $captcha,
        public readonly string $action,
        public readonly ?string $provider = null,
        public readonly array $refusal = [],
        public readonly ?string $id = null,
    ) {}

    public function render(): View|string
    {
        $widget = $this->captcha->widget($this->action, $this->provider, $this->action);

        if ($widget === null) {
            return '';
        }

        Widgets::need('captcha');

        return view('webx-widgets::components.captcha', [
            'captcha' => $widget,
            'errorId' => ($this->id ?? 'webx-captcha-'.Widgets::sequence('captcha')).'-error',
            'script' => $this->captcha->script($widget['provider'], $widget['type'] === SiteCaptcha::V3 ? $widget['key'] : null),
        ]);
    }
}
