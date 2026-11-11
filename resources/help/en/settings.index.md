# fields

## consent-enabled

With the banner on, a new visitor is asked first, and counters, advertising and videos wait for the
answer. Without it nobody is asked and all of that starts for every visitor at once, so switch it
off only when you are sure the site needs no consent.

## consent-policy

Visitors get a «Cookie policy» link after the words of the banner. With nothing chosen there is no
link, and the same happens, without a warning, if the chosen page later leaves the site.

## consent-version

Visitors never see this number. Changing it by hand and pressing «Save» does what «Ask everyone
again» does: the banner comes back to everybody. Putting an old number back makes the answers given
to it count again.

## consent-ask-again

Careful: this cancels every answer given so far. After «Save» everybody gets the banner again, and
until a visitor answers, counters, advertising and videos stay off for them, so expect your
statistics to dip for a while.

## consent-categories

A ticked category is offered in the window behind «Customize» on every page, even where nothing
uses it. A category the open page needs is offered there anyway. Leaving one unticked switches
nothing off: «Accept all» agrees to every category, and whatever waits for it starts.

## consent-maps-at-once

Visitors see the map straight away, with no notice to get past. If nothing else on the site needs
«Embedded media» and it is not ticked in «Categories offered», the window with the categories stops
offering it. On a site without maps this changes nothing.

## consent-text-title

The heading of the banner, the first thing a visitor reads. A language left empty takes the words of
the site's main language, and with nothing written at all the visitor gets the ready words in their
own language.

## consent-text-text

The words of the banner, repeated at the top of the window with the categories. The visitor agrees
to what is written here, so say what the site really does. The link to the policy page follows them
by itself, and a link typed here stays plain text.

## consent-text-necessary

The line under «Necessary» in the window with the categories. This is the one category a visitor
cannot switch off, so say why the site does not work without it.

## consent-text-preferences

The line under «Preferences» in the window with the categories. A visitor sees it only while the
window offers this category. Name what the site remembers: the language, for example.

## consent-text-statistics

The line under «Statistics» in the window with the categories. A visitor sees it only while the
window offers this category. Say what is counted and why, because this is what people decide by.

## consent-text-marketing

The line under «Marketing» in the window with the categories. A visitor whose browser asks not to be
tracked gets a note there, and «Accept all» leaves this category off for them.

## consent-text-media

The line under «Embedded media» in the window with the categories. Until a visitor agrees, a notice
stands in the place of a video and of a map, unless maps are set to load without asking.

## captcha-provider

A captcha makes visitors prove they are not robots: less spam, one more step. Choose a service only
when both of its keys are ready, because until then whatever has the captcha on refuses everything
sent. «Off» is no way out: forms and a checkout with the captcha on then refuse everything too, so
turn it off there first.

## captcha-recaptcha-type

Visitors get a box to tick, or see nothing until they send the form, or with a score see nothing at
all. A visitor the score takes for a robot is refused, and has no way to prove otherwise. Another
kind means new keys, so change this together with the key.

## captcha-recaptcha-key

This key is half of the pair. The other half is set on the server by whoever looks after the site,
and without it the captcha is drawn on the page, yet every form that uses it refuses everything
sent. This tab gives no warning about that, so send a form yourself after saving.

## captcha-turnstile-mode

«Shown on the page» puts the check in the form from the start. «Invisible, run on submit» keeps the
form cleaner, and the check starts only when the visitor sends it, so sending takes a moment longer.

## captcha-turnstile-key

This key is half of the pair. The other half is set on the server by whoever looks after the site,
and without it the check is drawn on the page, yet every form that uses it refuses everything sent.
This tab gives no warning about that, so send a form yourself after saving.

## map-provider

Every map of the site is drawn by the service chosen here, the blocks «Map» and the contacts page
included. OpenStreetMap works at once and needs no account. Google Maps needs a key below: until
it has one, visitors see the address with a link to Google Maps where the map would be. Either
service waits for the visitor to agree to «Embedded media», unless maps are set to load without
asking on the «Cookie» tab.

## map-google-key

Create a key in the Google Cloud console with the Maps JavaScript API turned on and billing set up.
The key is a browser key: every visitor can see it in the page, so restrict it there to the site's
domains (HTTP referrers, such as `example.com/*`) and to the Maps JavaScript API alone. A key Google
refuses shows visitors the address with the words «The map could not be loaded».

## map-google-map-id

Leave it empty unless you made a Map ID in the Google Cloud console. With one, the pin is drawn as
an advanced marker and the map takes the style you gave that ID there, its dark variant included.
