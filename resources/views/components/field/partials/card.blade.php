{{-- What a card says beside its input: the picture, the label, the description. The picture is
     decorative — the label says what the choice is — so its alt is empty. --}}
@if ($option['image'] !== null)
    <img {{ new \Illuminate\View\ComponentAttributeBag(array_filter([
        'class' => 'webx-field__card-image',
        'src' => $option['image']['src'],
        'alt' => '',
        'loading' => 'lazy',
        'decoding' => 'async',
        'srcset' => $option['image']['srcset'],
        'sizes' => $option['image']['srcset'] !== null ? '(min-width: 40rem) 20rem, 100vw' : null,
        'width' => $option['image']['width'],
        'height' => $option['image']['height'],
    ], static fn (mixed $value): bool => $value !== null)) }}>
@endif
<span class="webx-field__card-title">{{ $option['label'] }}</span>
@if ($option['description'] !== null)
    <span class="webx-field__card-text">{{ $option['description'] }}</span>
@endif
