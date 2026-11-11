{{--
    The photos of a card (spec §14). One photo: a plain picture with the attributes given. Two and
    more: a frame of the photos' shape, every photo in it, the first shown; the rest are `hidden`
    by the stylesheet until the script is ready, so without it the first stands alone. The bars and
    the pause button are printed `hidden` and shown by the script, which would otherwise leave
    buttons that do nothing.

    Classes: webx-photos, __frame, __photo, __bars, __bar, __fill, __pause, __pause-icon,
    __play-icon; is-ready, is-paused, is-active, is-done — the script's but the first `is-active`.
--}}
@if (count($photos) === 1)
<img {{ $attributes }} src="{{ $photos[0]['url'] }}" alt="{{ $photos[0]['alt'] }}" @unless ($eager) loading="lazy" @endunless @if ($photos[0]['width'] && $photos[0]['height']) width="{{ $photos[0]['width'] }}" height="{{ $photos[0]['height'] }}" @endif>
@else
<div {{ $attributes->class(['webx-photos'])->merge([
    'style' => "--webx-photos-ratio: {$shape}; --webx-photos-interval: ".($delay ?: \WebxUi\Widgets\View\Components\Photos::DELAY).'ms',
    'role' => 'group',
    'aria-roledescription' => __('webx-widgets::widgets.slider.carousel'),
    'aria-label' => $name,
    'data-webx-photos' => json_encode(['autoplay' => $delay, 'words' => ['pause' => $words['pause'], 'play' => $words['play']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]) }}>
    <div class="webx-photos__frame">
        @foreach ($photos as $photo)
            <img @class(['webx-photos__photo', 'is-active' => $loop->first]) src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" @if (! $loop->first) data-webx-later @endif @if (! $loop->first || ! $eager) loading="lazy" @endif decoding="async" draggable="false" @if ($photo['width'] && $photo['height']) width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" @endif>
        @endforeach
    </div>
    <div class="webx-photos__bars" hidden>
        @foreach ($photos as $photo)
            <button type="button" class="webx-photos__bar" aria-label="{{ $bar($loop->iteration) }}"><span class="webx-photos__fill"></span></button>
        @endforeach
    </div>
    @if ($delay > 0)
        <button type="button" class="webx-photos__pause" aria-label="{{ $words['pause'] }}" hidden>
            <x-webx-icon name="pause" class="webx-photos__pause-icon" />
            <x-webx-icon name="play" class="webx-photos__play-icon" />
        </button>
    @endif
</div>
@endif
