<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Support\Forms;

/**
 * The forms a page opens in a dialog (spec §6.4): "Request a call", "Book" — a form of
 * `module-inbox` in a `<dialog>` at the end of `<body>`, once per slug however many buttons
 * lead to it.
 *
 * The form is printed the way it is printed anywhere — `<x-webx-inbox::form placement="modal">`
 * — so the intake, the antispam and the script are the module's, and the submission remembers
 * it came from a dialog. The library does not require the module: without it, or with a slug
 * no enabled form has, there is no dialog and the opener stays a link to nowhere.
 */
final class FormDialogs
{
    /** What the opener is: `data-webx-form="<slug>"` on a link or a button, or a link to `#webx-form-<slug>`. */
    private const string OPENER = '~<(?:a|button)\b[^>]*>~i';

    private const string ATTRIBUTE = '~\sdata-webx-form=(["\'])([a-z0-9][a-z0-9_-]*)\1~i';

    private const string HREF = '~\shref=(["\'])#webx-form-([a-z0-9][a-z0-9_-]*)\1~i';

    public const string ID = 'webx-form-';

    public function __construct(
        private readonly ViewFactory $views,
        private readonly Config $config,
    ) {}

    /**
     * The slugs the page's own links and buttons ask for. Not `<form data-webx-form>` — that is
     * the module's hook on the form itself, the same attribute on the other end.
     *
     * @return list<string>
     */
    public static function openers(string $html): array
    {
        preg_match_all(self::OPENER, $html, $tags);
        $slugs = [];

        foreach ($tags[0] as $tag) {
            if (preg_match(self::ATTRIBUTE, $tag, $match) === 1 || preg_match(self::HREF, $tag, $match) === 1) {
                $slugs[$match[2]] = true;
            }
        }

        return array_keys($slugs);
    }

    /**
     * The dialogs, one per slug.
     *
     * @param  list<string>  $slugs
     */
    public function render(array $slugs): string
    {
        $slugs = array_values(array_unique($slugs));

        if ($slugs === []) {
            return '';
        }

        if (! class_exists(Forms::class)) {
            return $this->config->get('app.debug') ? '<!-- webx-widgets: a form in a dialog needs webx-ui/module-inbox -->' : '';
        }

        $dialogs = [];

        foreach ($slugs as $slug) {
            $form = app(Forms::class)->enabled($slug);

            if ($form instanceof Form) {
                $dialogs[] = $this->views->make('webx-widgets::form-dialog', [
                    'form' => $form,
                    'id' => self::ID.$form->slug,
                    'title' => trim((string) $form->title) !== '' ? trim((string) $form->title) : $form->slug,
                    'open' => $this->answered($form),
                ])->render();
            }
        }

        return implode("\n", $dialogs);
    }

    /**
     * Whether the page comes back from this form sent without JavaScript — with its thank-you
     * or its errors in the session. Then the dialog is printed open: the answer is in it.
     */
    private function answered(Form $form): bool
    {
        // The request of the moment, not one held from construction: this is scoped, and a
        // test's second request may meet the instance its first one made.
        $request = request();

        if (! $request->hasSession()) {
            return false;
        }

        $session = $request->session();
        $flash = $session->get('webx-inbox');
        $old = $session->getOldInput();

        if (is_array($flash) && ($flash['form'] ?? null) === $form->slug) {
            return true;
        }

        return is_array($old) && ($old['webx_form'] ?? null) === $form->slug && ($old['webx_placement'] ?? null) === 'modal';
    }
}
