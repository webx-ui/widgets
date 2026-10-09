// Builds resources/js and resources/css into dist/: one file of JS and one of CSS per widget, and
// the shared runtime — the files `php artisan webx:theme:sync` publishes and the page loads only
// where they are claimed (spec §4). dist/ is committed — a site installs the package from
// Composer and has no Node to build it with — so the build also writes dist/sources.json, the
// hashes of what it was built from, and the package's test fails when the sources changed and
// dist/ did not.
//
// Nothing imported but Node's own: the config runs with whichever Vite builds it, from the
// monorepo's root or from a site's, and has nothing of its own to install.

import { createHash } from 'node:crypto'
import { readFileSync, readdirSync } from 'node:fs'
import { join, relative, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('.', import.meta.url))

// The same walk and the same hash as tests/DistTest.php: every source file but the tests, line
// endings normalised, so a Windows checkout and CI agree on what "built from" means.
function sources() {
  const files = {}
  const walk = (directory) => {
    for (const entry of readdirSync(directory, { withFileTypes: true })) {
      const path = join(directory, entry.name)

      if (entry.isDirectory()) {
        walk(path)
      } else if (!entry.name.includes('.test.')) {
        const content = readFileSync(path, 'utf8').replace(/\r\n/g, '\n')
        files[relative(root, path).split(sep).join('/')] = createHash('sha256')
          .update(content)
          .digest('hex')
      }
    }
  }

  return {
    name: 'webx-widgets-sources',
    apply: 'build',
    generateBundle() {
      // Walked at the end of each build, so `vite build --watch` hashes what it just built.
      for (const name of Object.keys(files)) delete files[name]
      walk(join(root, 'resources/js'))
      walk(join(root, 'resources/css'))

      const sorted = Object.fromEntries(Object.entries(files).sort(([a], [b]) => (a < b ? -1 : 1)))

      this.emitFile({
        type: 'asset',
        fileName: 'sources.json',
        source: JSON.stringify({ algorithm: 'sha256', files: sorted }, null, 2) + '\n',
      })
    },
  }
}

// Leaflet's stylesheet points at PNGs — the default pin, the layers control — that the map never
// shows: its pin is the package's SVG. Inlined, they would be kilobytes of base64 in map.css. The
// VML rule of Internet Explorer goes too: no browser the site serves draws VML.
function leafletWithoutPictures() {
  return {
    name: 'webx-widgets-leaflet-css',
    enforce: 'pre',
    transform(code, id) {
      if (!id.replace(/\\/g, '/').endsWith('leaflet/dist/leaflet.css')) return null
      return code
        .replace(/background-image:\s*url\(images\/[^)]+\);?/g, '')
        .replace(/\.lvml\s*\{[^}]*\}/g, '')
    },
  }
}

export default {
  root,
  publicDir: false,
  logLevel: 'warn',
  plugins: [sources(), leafletWithoutPictures()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    assetsDir: '',
    // Each widget is its own file: a shared chunk would be one more request on every page.
    modulePreload: false,
    rollupOptions: {
      input: {
        runtime: join(root, 'resources/js/runtime.js'),
        consent: join(root, 'resources/js/consent.js'),
        contacts: join(root, 'resources/js/contacts.js'),
        // No script of its own: the dropdown it opens is the runtime's.
        'language-switcher': join(root, 'resources/css/language-switcher.css'),
        // Swiper is built in here, and only here: a page without a slider never loads it.
        slider: join(root, 'resources/js/slider.js'),
        // PhotoSwipe, the same way: only where a picture opens over the page.
        lightbox: join(root, 'resources/js/lightbox.js'),
        // The facade and the notice before consent: no player library, the provider's is in its iframe.
        video: join(root, 'resources/js/video.js'),
        // Leaflet, built in here and only here: tiles wait for consent to media (§11).
        map: join(root, 'resources/js/map.js'),
      },
      output: {
        entryFileNames: '[name].js',
        assetFileNames: '[name][extname]',
      },
    },
  },
}
