@php
    // A picture deleted from the library resolves without an address: left out, not printed broken.
    $shown = array_values(array_filter(is_array($pictures) ? $pictures : [], static fn ($picture): bool => is_array($picture) && ! empty($picture['url'])));
    $across = max(1, min(6, (int) ($columns ?: 3)));
    $label = $heading ? wx_text($heading)->plain() : null;
    // One group per block: the arrows of the lightbox page through this gallery and no other.
    $group = 'gallery-'.$block->key;
    // The shape a picture opens in over the page; `auto`, and a block saved before the field, open it as it is.
    $frame = isset($ratio) && is_string($ratio) ? $ratio : null;
    // A slide's picture magnified under the mouse and two fingers (§14): the lightbox's link carries it, so a keyboard opens the picture as before.
    $hover = $zoom && $view === 'slider' && in_array($magnify, ['inner', 'side'], true) ? $magnify : null;
    // The slider's settings; a block saved before them gets what the slider always was: one picture, arrows, thumbnails.
    $at = in_array((int) ($per_view ?? 1), [1, 2, 3], true) ? (int) ($per_view ?? 1) : 1;
    // By the width of the column, as the grid's columns: one in a phone's column, more where they fit.
    $views = [1 => 1, 2 => ['sm' => 1, 'md' => 2], 3 => ['sm' => 1, 'md' => 2, 'lg' => 3]][$at];
    $fade = $at === 1 && ($effect ?? 'slide') === 'fade' ? 'fade' : 'slide';
    $turn = ! empty($autoplay) ? max(2, min(30, (int) ($interval ?? 5) ?: 5)) * 1000 : false;
@endphp
@if ($shown)
<section class="b-gallery b-gallery--{{ $view === 'slider' ? 'slider' : ($view === 'mosaic' ? 'mosaic' : 'grid') }}{{ $view === 'slider' && $at > 1 ? ' b-gallery--row' : '' }}" data-wx-block="gallery" style="--gallery-columns: {{ $across }}">
    @if ($heading)
        <h2 class="b-gallery__heading">{{ $heading }}</h2>
    @endif
    @if ($view === 'slider')
        <x-webx-slider variant="gallery" :label="$label" :per-view="$views" :effect="$fade" :arrows="(bool) ($arrows ?? true)" :pagination="(bool) ($dots ?? false)" :thumbs="$at === 1 && (bool) ($thumbs ?? true)" :loop="(bool) ($endless ?? false)" :autoplay="$turn">
            @foreach ($shown as $picture)
                <x-webx-slide :thumb="$picture['thumb'] ?? null">
                    <figure class="b-gallery__figure">
                        @if ($zoom)
                            <x-webx-lightbox :image="$picture" :group="$group" :ratio="$frame" :data-webx-zoom="$hover ? '' : null" :data-zoom-mode="$hover"><img class="b-gallery__image" src="{{ $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}" @if ($picture['width']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif></x-webx-lightbox>
                        @else
                            <img class="b-gallery__image" src="{{ $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}" @if ($picture['width']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif>
                        @endif
                        @if (! empty($picture['title']))
                            <figcaption class="b-gallery__caption">{{ $picture['title'] }}</figcaption>
                        @endif
                    </figure>
                </x-webx-slide>
            @endforeach
        </x-webx-slider>
    @elseif ($view === 'mosaic')
        @php
            // Three at a time, the first large; a carousel of threes only with more than three to page through.
            $turning = ($mosaic ?? 'still') === 'slider' && count($shown) > 3;
            $groups = $turning ? array_chunk($shown, 3) : [array_slice($shown, 0, 3)];
            // Still: the pictures past the third are not shown, but the lightbox pages through them all.
            $unseen = $turning || ! $zoom ? [] : array_slice($shown, 3);
            $tiles = [];
        @endphp
        @foreach ($groups as $three)
            @php ob_start(); @endphp
            <ul class="b-gallery__mosaic b-gallery__mosaic--{{ count($three) }}">
                @foreach ($three as $picture)
                    <li class="b-gallery__tile">
                        <figure class="b-gallery__figure">
                            <div class="b-gallery__shot">
                                @if ($zoom)
                                    <x-webx-lightbox :image="$picture" :group="$group" :ratio="$frame"><img class="b-gallery__image" src="{{ $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}" @if ($picture['width']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif></x-webx-lightbox>
                                @else
                                    <img class="b-gallery__image" src="{{ $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}" @if ($picture['width']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif>
                                @endif
                            </div>
                            @if (! empty($picture['title']))
                                <figcaption class="b-gallery__caption">{{ $picture['title'] }}</figcaption>
                            @endif
                        </figure>
                    </li>
                @endforeach
            </ul>
            @php $tiles[] = ob_get_clean(); @endphp
        @endforeach
        @if ($turning)
            <x-webx-slider variant="gallery" :label="$label" :per-view="1" :thumbs="false" :arrows="(bool) ($arrows ?? true)" :pagination="(bool) ($dots ?? false)" :loop="(bool) ($endless ?? false)" :autoplay="$turn">
                @foreach ($tiles as $tile)
                    <x-webx-slide>{!! $tile !!}</x-webx-slide>
                @endforeach
            </x-webx-slider>
        @else
            {!! $tiles[0] !!}
        @endif
        @if ($unseen)
            <div class="b-gallery__unseen" hidden>
                @foreach ($unseen as $picture)
                    <x-webx-lightbox :image="$picture" :group="$group" :ratio="$frame" :aria-label="$picture['alt'] ?? null"><span>{{ $picture['title'] ?? '' }}</span></x-webx-lightbox>
                @endforeach
            </div>
        @endif
    @else
        <ul class="b-gallery__grid">
            @foreach ($shown as $picture)
                @php
                    // The square preview of the library, and the picture itself where a cell is wider than it.
                    $sources = $picture['thumb'] && $picture['width'] ? $picture['thumb'].' 320w, '.$picture['url'].' '.$picture['width'].'w' : null;
                @endphp
                <li class="b-gallery__cell">
                    <figure class="b-gallery__figure">
                        @if ($zoom)
                            <x-webx-lightbox :image="$picture" :group="$group" :ratio="$frame"><img class="b-gallery__image" src="{{ $picture['thumb'] ?: $picture['url'] }}" @if ($sources) srcset="{{ $sources }}" sizes="(max-width: 40rem) 50vw, {{ (int) ceil(100 / $across) }}vw" @endif alt="{{ $picture['alt'] ?? '' }}" width="320" height="320" @if ($loop->index >= $across) loading="lazy" @endif></x-webx-lightbox>
                        @else
                            <img class="b-gallery__image" src="{{ $picture['thumb'] ?: $picture['url'] }}" @if ($sources) srcset="{{ $sources }}" sizes="(max-width: 40rem) 50vw, {{ (int) ceil(100 / $across) }}vw" @endif alt="{{ $picture['alt'] ?? '' }}" width="320" height="320" @if ($loop->index >= $across) loading="lazy" @endif>
                        @endif
                        @if (! empty($picture['title']))
                            <figcaption class="b-gallery__caption">{{ $picture['title'] }}</figcaption>
                        @endif
                    </figure>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endif
