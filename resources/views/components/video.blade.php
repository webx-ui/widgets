{{--
    A video in the page (spec §10). YouTube and Vimeo: a link to the video with its poster, which
    the script makes a play button that puts the player in; before consent to media the notice of
    §9.4 stands over it, its buttons the script's. A file of the site: <video preload="none">.

    Classes: webx-video, --youtube | --vimeo | --file, __facade, __poster, __play, __icon,
    __consent, __notice, __actions, __button, __button--always, __frame, __player;
    is-blocked (before consent), is-ready (the script took it), is-playing (the player is in).
--}}
@if ($kind === 'file')
<div {{ $attributes->class(['webx-video', 'webx-video--file'])->merge(['style' => "--webx-video-ratio: {$ratio}"]) }}>
    <video class="webx-video__player" controls preload="none" playsinline aria-label="{{ $name }}" @if ($posterUrl) poster="{{ $posterUrl }}" @endif>
        <source src="{{ $href }}" @if ($fileType) type="{{ $fileType }}" @endif>
        <a href="{{ $href }}">{{ $name }}</a>
    </video>
</div>
@else
<div {{ $attributes->class(['webx-video', "webx-video--{$kind}", 'is-blocked' => $blocked])->merge(['style' => "--webx-video-ratio: {$ratio}"]) }} data-webx-video="{{ json_encode($config, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) }}" data-webx-consent="media">
    <a class="webx-video__facade" href="{{ $href }}" aria-label="{{ __('webx-widgets::widgets.video.play', ['title' => $name]) }}">
        @if ($posterUrl)<img class="webx-video__poster" src="{{ $posterUrl }}" alt="" loading="lazy" decoding="async" @if ($posterWidth && $posterHeight) width="{{ $posterWidth }}" height="{{ $posterHeight }}" @endif>@endif
        <span class="webx-video__play"><x-webx-icon name="play" class="webx-video__icon" /></span>
    </a>
    @if ($blocked)
    <div class="webx-video__consent" role="group" aria-label="{{ $name }}">
        <p class="webx-video__notice">{{ $notice }}</p>
        <div class="webx-video__actions">
            <button type="button" class="webx-video__button" data-webx-video-load>{{ __('webx-widgets::widgets.video.load') }}</button>
            <button type="button" class="webx-video__button webx-video__button--always" data-webx-video-always>{{ __('webx-widgets::widgets.video.always') }}</button>
        </div>
    </div>
    @endif
</div>
@endif
