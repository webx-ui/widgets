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
        'video_pause' => [
            'title' => 'Background videos without a pause button',
            'found' => 'A background video plays by itself and has no pause button.',
            'why' => 'Motion that lasts more than five seconds must have a way to stop it (WCAG 2.2.2): it distracts, and some visitors cannot read the text over it.',
            'fix' => 'The package’s view always has the button: a theme’s override of webx-widgets::components.video lost .webx-video__pause. Put it back, or remove the override.',
        ],
        'counter_number' => [
            'title' => 'Counters without their number',
            'found' => 'A counter’s markup does not hold the number it counts to.',
            'why' => 'Search engines, screen readers and a page without JavaScript read the markup: they get a zero or nothing instead of the number.',
            'fix' => 'The package’s view prints the final number and the script counts up to it: a theme’s override of webx-widgets::components.counter prints something else. Print the number, or remove the override.',
        ],
        'compare_range' => [
            'title' => 'Before and after without its slider',
            'found' => 'A before-and-after divider has no range input: only a mouse and a finger can move it.',
            'why' => 'A keyboard cannot reach the divider and a screen reader cannot name it: part of the picture stays hidden from those visitors.',
            'fix' => 'The package’s view makes the divider an <input type="range">: a theme’s override of webx-widgets::components.compare lost it. Put it back, or remove the override.',
        ],
        'toc_target' => [
            'title' => 'Table of contents links to nowhere',
            'found' => 'A link of a table of contents leads to a section the page does not have.',
            'why' => 'A visitor clicks a section and nothing happens: the page does not move, and the list looks broken.',
            'fix' => 'The server makes the list from the page’s headings and gives them their ids. A list written by hand, or a theme’s override of the list, points at an id that is gone: use <x-webx-toc>, or fix the link.',
        ],
        'load_more_link' => [
            'title' => 'Show more without a link to the next page',
            'found' => 'A “Show more” list has a next page but no link to it: only its button leads there.',
            'why' => 'Search engines do not press buttons, and a page without JavaScript cannot either: the items past the first page are not found.',
            'fix' => 'The package prints the links of the pages under the list and the button only covers them: a theme’s override of webx-widgets::components.load-more, or an empty links slot, lost them. Put them back, or remove the override.',
        ],
        'zoom_link' => [
            'title' => 'Zoom without a link',
            'found' => 'A picture magnified under the mouse is no link and holds none: a keyboard cannot open it.',
            'why' => 'A keyboard and a screen reader have no hover: the link to the picture, opened in the lightbox, is their only way to see it closer.',
            'fix' => 'The package’s view makes the picture a link to the original: a theme’s override of webx-widgets::components.zoom lost it, or data-webx-zoom was written on a bare picture. Put the attribute on the picture’s lightbox link, or remove the override.',
        ],
    ],
];
