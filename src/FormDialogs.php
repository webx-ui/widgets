<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Support\Forms;
use WebxUi\Inbox\View\Components\Form as InboxForm;

/**
 * The forms a page opens in a dialog (spec §6.4): "Request a call", "Book" — a form of
 * `module-inbox` in a `<dialog>` at the end of `<body>`, once per slug however many buttons
 * lead to it.
 *
 * The form is printed the way it is printed anywhere — `<x-webx-inbox::form placement="modal">`
 * — so the intake, the antispam and the captcha are the module's, the submission remembers it
 * came from a dialog, and the form is an `<x-webx-form>` that behaves in a dialog the way any of
 * them does (spec §17.3): the thank-you in place of the fields, a button that closes it. The library does not require the module: without it, or with a slug
 * no enabled form has, there is no dialog and the opener stays a link to nowhere.
 */
final class FormDialogs
{
    /** What the opener is: `data-webx-form="<slug>"` on a link or a button, or a link to `#webx-form-<slug>`. */
    private const string OPENER = '~<(?:a|button)\b[^>]*>~i';

    private const string ATTRIBUTE = '~\sdata-webx-form=(["\'])([a-z0-9][a-z0-9_-]*)\1~i';

    private const string HREF = '~\shref=(["\'])#webx-form-([a-z0-9][a-z0-9_-]*)\1~i';

    /** `data-webx-sheet` on an opener: the dialog it opens is a sheet from the bottom on a phone. */
    private const string SHEET = '~\sdata-webx-sheet(?:=(["\'])(?!false\1)[^"\']*\1)?(?=[\s>/])~i';

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
        return array_keys(self::found($html));
    }

    /**
     * The slugs at least one of whose openers on the page says `data-webx-sheet`: those dialogs
     * open as a sheet from the bottom on a narrow screen (§6.4). One opener is enough — a dialog
     * is printed once per slug, and two buttons to one form asking for two shapes is a slip.
     *
     * @return list<string>
     */
    public static function sheets(string $html): array
    {
        return array_keys(array_filter(self::found($html)));
    }

    /**
     * @return array<string, bool> each slug the page asks for, and whether as a sheet
     */
    private static function found(string $html): array
    {
        preg_match_all(self::OPENER, $html, $tags);
        $slugs = [];

        foreach ($tags[0] as $tag) {
            if (preg_match(self::ATTRIBUTE, $tag, $match) === 1 || preg_match(self::HREF, $tag, $match) === 1) {
                $slugs[$match[2]] = ($slugs[$match[2]] ?? false) || preg_match(self::SHEET, $tag) === 1;
            }
        }

        return $slugs;
    }

    /**
     * The dialogs, one per slug. A slug in `$sheets` — or every one, with
     * `webx-widgets.form_dialog.sheet` on — is a sheet from the bottom on a narrow screen.
     *
     * @param  list<string>  $slugs
     * @param  list<string>  $sheets
     */
    public function render(array $slugs, array $sheets = []): string
    {
        $slugs = array_values(array_unique($slugs));
        $always = (bool) $this->config->get('webx-widgets.form_dialog.sheet', false);

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
                // The line under the title is the dialog's (under its own title, its description
                // for a screen reader), not printed a second time by the form inside.
                $body = Blade::render('<x-webx-inbox::form :form="$form" placement="modal" :description="false" />', ['form' => $form]);

                $dialogs[] = $this->views->make('webx-widgets::form-dialog', [
                    'form' => $form,
                    'id' => self::ID.$form->slug,
                    'title' => trim((string) $form->title) !== '' ? trim((string) $form->title) : $form->slug,
                    'body' => new HtmlString($body),
                    'description' => InboxForm::descriptionHtml($form),
                    'sheet' => $always || in_array($slug, $sheets, true),
                    // The form the page came back from says so itself; a view the site published
                    // before W6.4 does not, and is told by the session.
                    'open' => str_contains($body, 'data-webx-returned') || $this->answered($form),
                    // A view of before W6.4: no shell, so the dialog brings its own Close.
                    'legacy' => ! str_contains($body, 'data-webx-send'),
                ])->render();
            }
        }

        return implode("\n", $dialogs);
    }

    /**
     * Whether the page comes back from this form sent without JavaScript — with its thank-you
     * or its errors in the session — when the form is a view the site published before W6.4.
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
