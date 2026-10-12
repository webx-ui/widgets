<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-webx-form.submit>` — the button of a form (spec §17.3): 44px, the site's accent, and the
 * one the form's script holds back while a submission is on its way. Any attribute goes on the
 * `<button>` — `name` and `value` too, for a form with two ways out.
 */
final class FormSubmit extends Component
{
    public function render(): View
    {
        return view('webx-widgets::components.form.submit');
    }
}
