{{--
    "Cookie settings" (spec §9.1): a link to the consent dialog, opened by the runtime's dialog
    behaviour. Hidden without JavaScript, where the dialog's switches could not work.

    Classes: webx-consent-link.
--}}
<a {{ $attributes->class(['webx-consent-link'])->merge(['href' => '#'.$dialog, 'data-webx-dialog' => $dialog]) }}>{{ $text }}</a>
