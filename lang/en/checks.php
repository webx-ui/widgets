<?php

declare(strict_types=1);

return [
    'widgets' => [
        'before_consent' => [
            'title' => 'Third parties that load before consent',
            'found' => 'A video player, a map, a counter or a pixel is asked for as the page loads, before the visitor has answered the cookie banner.',
            'why' => 'In the EU a third party that sets cookies or receives the visitor’s address may load only after consent to its category. The banner asks, but the page has already sent the request.',
            'fix' => 'Use the video or map block, which waits by itself, or wrap the code in <x-webx-consent category="…">. Pasted into content, the fix button makes it wait: an iframe gets data-src, a script type="text/plain", both data-webx-consent.',
        ],
        'banner_off' => [
            'title' => 'Cookie banner off with third parties on the site',
            'found' => 'The cookie banner is switched off, and the site has a video, a map, a counter or something else marked to wait for consent.',
            'why' => 'With the banner off everything third-party loads for every visitor with no question asked. That is allowed only for a site that needs no consent — outside the EU, with no visitors from it.',
            'fix' => 'Turn the banner on in Settings › Cookie (the fix button does it). If the site really needs no banner, hide this finding with the reason.',
        ],
        'lightbox_size' => [
            'title' => 'Lightbox links without the picture size',
            'found' => 'A link that opens a picture in the lightbox has no data-width and data-height.',
            'why' => 'Without the size the lightbox downloads the whole picture before it opens to measure it, and the picture jumps into place.',
            'fix' => 'Use <x-webx-lightbox :image> with a picture of the library — it prints the size — or write data-width and data-height of the full picture on the link.',
        ],
        'slider_pause' => [
            'title' => 'Moving sliders without a pause button',
            'found' => 'A slider moves by itself — autoplay or a running strip — and has no pause button.',
            'why' => 'Content that moves for more than five seconds must have a way to stop it (WCAG 2.2.2): it distracts, and some visitors cannot read it at all.',
            'fix' => 'The package’s view always has the button: a theme’s override of webx-widgets::components.slider lost .webx-slider__pause. Put it back, or remove the override.',
        ],
        'contact_both' => [
            'title' => 'Quick-contact button and bottom bar on one page',
            'found' => 'The page has both <x-webx-contact-button> and <x-webx-contact-bar>.',
            'why' => 'They offer the same calls and chats twice, and on a phone the button sits over the bar.',
            'fix' => 'Keep one of the two in the theme’s layout.',
        ],
    ],
];
