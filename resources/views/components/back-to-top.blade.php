{{--
    Back to top (spec §14): a link to #top, which the script makes a round button in a corner of
    the window that comes after the page has been scrolled down.

    Classes: webx-back-to-top, --bottom-end | --bottom-start, __icon; is-shown (scrolled far enough).
--}}
<a {{ $attributes->class(['webx-back-to-top', "webx-back-to-top--{$corner}"]) }} href="#top" data-webx-back-to-top="{{ json_encode(['after' => $after], JSON_THROW_ON_ERROR) }}" aria-label="{{ $labelText }}"><x-webx-icon name="arrow-up" class="webx-back-to-top__icon" /></a>
