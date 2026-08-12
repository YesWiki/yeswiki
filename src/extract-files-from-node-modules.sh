#!/usr/bin/env bash

# Extract files that we need from the node_modules folder
# The extracted files are integrated to the repository, so production server don't need to
# have node installed

# Always run from the project root regardless of where the script is called from
cd "$(dirname "$0")/.." || exit 1

# Copy a JS file while stripping sourceMappingURL comments
copy_js() { sed '/^[[:space:]]*\/\/#[[:space:]]*sourceMappingURL=/d' "$1" > "$2"; }
# Copy a CSS file while stripping sourceMappingURL comments
copy_css() { sed '/^[[:space:]]*\/\*#[[:space:]]*sourceMappingURL=/d' "$1" > "$2"; }

# Tabler icon sprite (curated, committed): regenerate in case icon-map.json moved ahead
node src/build-icon-sprite.mjs
#  Vue 3 (global build for browser usage)
mkdir -p javascripts/vendor/vue && copy_js node_modules/vue/dist/vue.global.js javascripts/vendor/vue/vue.js
copy_js node_modules/vue/dist/vue.global.prod.js javascripts/vendor/vue/vue.min.js
# Vue Select
mkdir -p javascripts/vendor/vue-select && copy_js node_modules/vue-select/dist/vue-select.umd.js javascripts/vendor/vue-select/vue-select.min.js
mkdir -p styles/vendor/vue-select && copy_css node_modules/vue-select/dist/vue-select.css styles/vendor/vue-select/vue-select.css
# Vue draggable v4 (Vue 3 compatible)
mkdir -p javascripts/vendor/sortablejs && copy_js node_modules/sortablejs/Sortable.min.js javascripts/vendor/sortablejs/sortable.js
mkdir -p javascripts/vendor/vuedraggable && copy_js node_modules/vuedraggable/dist/vuedraggable.umd.js javascripts/vendor/vuedraggable/vuedraggable.js

# Leaflet
mkdir -p javascripts/vendor/leaflet && copy_js node_modules/leaflet/dist/leaflet.js javascripts/vendor/leaflet/leaflet.min.js
mkdir -p styles/vendor/leaflet && copy_css node_modules/leaflet/dist/leaflet.css styles/vendor/leaflet/leaflet.css
cp -f -r node_modules/leaflet/dist/images styles/vendor/leaflet
# Leaflet Markercluster
mkdir -p javascripts/vendor/leaflet-markercluster && copy_js node_modules/leaflet.markercluster/dist/leaflet.markercluster.js javascripts/vendor/leaflet-markercluster/leaflet-markercluster.min.js
mkdir -p styles/vendor/leaflet-markercluster && copy_css node_modules/leaflet.markercluster/dist/MarkerCluster.css styles/vendor/leaflet-markercluster/leaflet-markercluster.css
# Leaflet Providers
mkdir -p javascripts/vendor/leaflet-providers && copy_js node_modules/leaflet-providers/leaflet-providers.js javascripts/vendor/leaflet-providers/leaflet-providers.js
# Leaflet Fullscreen
mkdir -p javascripts/vendor/leaflet-fullscreen && copy_js node_modules/leaflet.fullscreen/Control.FullScreen.js javascripts/vendor/leaflet-fullscreen/leaflet-fullscreen.js
mkdir -p styles/vendor/leaflet-fullscreen && copy_css node_modules/leaflet.fullscreen/Control.FullScreen.css styles/vendor/leaflet-fullscreen/leaflet-fullscreen.css
# Leaflet Draw
mkdir -p javascripts/vendor/leaflet-draw && copy_js node_modules/leaflet-draw/dist/leaflet.draw.js javascripts/vendor/leaflet-draw/leaflet.draw.js
mkdir -p styles/vendor/leaflet-draw && copy_css node_modules/leaflet-draw/dist/leaflet.draw.css styles/vendor/leaflet-draw/leaflet.draw.css
mkdir -p styles/vendor/leaflet-draw/images && cp -f -r node_modules/leaflet-draw/dist/images/* styles/vendor/leaflet-draw/images


# Vditor (WYSIWYG/Markdown editor, replaces summernote). The main bundle/CSS sit directly
# under vditor/, matching this script's usual per-library layout; lute.min.js/i18n/icons stay
# under a dist/js/... subpath because Vditor's own runtime hardcodes that path relative to
# whatever base URL is passed as its `cdn` option (see javascripts/vditor-textarea.js).
mkdir -p javascripts/vendor/vditor/dist/js/lute &&
	copy_js node_modules/vditor/dist/index.min.js javascripts/vendor/vditor/index.min.js &&
	copy_js node_modules/vditor/dist/js/lute/lute.min.js javascripts/vendor/vditor/dist/js/lute/lute.min.js
mkdir -p styles/vendor/vditor && copy_css node_modules/vditor/dist/index.css styles/vendor/vditor/index.css
mkdir -p javascripts/vendor/vditor/dist/js/icons &&
	copy_js node_modules/vditor/dist/js/icons/ant.js javascripts/vendor/vditor/dist/js/icons/ant.js
mkdir -p javascripts/vendor/vditor/dist/js/i18n &&
	for lang in en_US es_ES fr_FR pt_BR; do
		copy_js "node_modules/vditor/dist/js/i18n/$lang.js" "javascripts/vendor/vditor/dist/js/i18n/$lang.js"
	done
# the typography of the editing surface itself, which Vditor fetches at runtime from the
# same `cdn` base -- and which every editor in the wiki has been failing to load (the
# server answers the missing path with text/plain, so the browser refuses the stylesheet)
mkdir -p javascripts/vendor/vditor/dist/css/content-theme &&
	copy_css node_modules/vditor/dist/css/content-theme/light.css javascripts/vendor/vditor/dist/css/content-theme/light.css &&
	copy_css node_modules/vditor/dist/css/content-theme/dark.css javascripts/vendor/vditor/dist/css/content-theme/dark.css

# fullcalendar
mkdir -p styles/vendor/fullcalendar &&
	copy_css node_modules/fullcalendar/main.min.css styles/vendor/fullcalendar/main.min.css

mkdir -p javascripts/vendor/fullcalendar &&
	copy_js node_modules/fullcalendar/main.min.js javascripts/vendor/fullcalendar/main.min.js &&
	copy_js node_modules/fullcalendar/locales-all.min.js javascripts/vendor/fullcalendar/locales-all.min.js &&
	cp -f node_modules/fullcalendar/LICENSE.txt javascripts/vendor/fullcalendar &&
	cp -f node_modules/fullcalendar/README.md javascripts/vendor/fullcalendar

# Moment
mkdir -p javascripts/vendor/moment &&
	copy_js node_modules/moment/min/moment-with-locales.min.js javascripts/vendor/moment/moment-with-locales.min.js &&
	cp -f node_modules/moment/min/moment-with-locales.min.js.map javascripts/vendor/moment

# Docsify
mkdir -p javascripts/vendor/docsify && \
  copy_js node_modules/docsify/dist/docsify.min.js javascripts/vendor/docsify/docsify.min.js && \
  cp -f node_modules/docsify/LICENSE javascripts/vendor/docsify && \
  cp -f node_modules/docsify/README.md javascripts/vendor/docsify
mkdir -p javascripts/vendor/docsify/plugins && \
  copy_js node_modules/docsify/dist/plugins/search.min.js javascripts/vendor/docsify/plugins/search.js && \
  copy_js node_modules/docsify/dist/plugins/zoom-image.min.js javascripts/vendor/docsify/plugins/zoom-image.min.js && \
  for f in node_modules/docsify-copy-code/dist/*.min.js; do
    copy_js "$f" "javascripts/vendor/docsify/plugins/$(basename "$f")"
  done
cp -f node_modules/docsify-copy-code/LICENSE javascripts/vendor/docsify/plugins/LICENSE-docisfy-copy-code
# Prism language components needed by docsify for syntax highlighting
mkdir -p javascripts/vendor/docsify/prism && \
  copy_js node_modules/prismjs/components/prism-php.js javascripts/vendor/docsify/prism/prism-php.js && \
  copy_js node_modules/prismjs/components/prism-nginx.js javascripts/vendor/docsify/prism/prism-nginx.js
mkdir -p styles/vendor/docsify && \
  cat node_modules/docsify/themes/vue.css \
    | sed -E "s|(@import url\(\"https://fonts.googleapis.com)|/*  \n  This file has been modified just to remove google font import on first line\n  It's based on Vue theme maintained by docsify\n  https://cdn.jsdelivr.net/npm/docsify/themes/vue.css\n */\n/* \1|g" \
    | sed -E 's|("\);)(\*\{-webkit)|\1 */\n\2|g' \
    > styles/vendor/docsify/vue-theme-modified.min.css

# Lazysizes
mkdir -p javascripts/vendor/lazysizes &&
	copy_js node_modules/lazysizes/lazysizes.min.js javascripts/vendor/lazysizes/lazysizes.min.js &&
	cp -f node_modules/lazysizes/LICENSE javascripts/vendor/lazysizes &&
	cp -f node_modules/lazysizes/README.md javascripts/vendor/lazysizes

# Ace
mkdir -p javascripts/vendor/ace &&
	copy_js node_modules/ace-builds/src-min-noconflict/ace.js javascripts/vendor/ace/ace.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/mode-html.js javascripts/vendor/ace/mode-html.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/worker-html.js javascripts/vendor/ace/worker-html.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/mode-markdown.js javascripts/vendor/ace/mode-markdown.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/mode-css.js javascripts/vendor/ace/mode-css.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/worker-css.js javascripts/vendor/ace/worker-css.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/mode-twig.js javascripts/vendor/ace/mode-twig.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/ext-language_tools.js javascripts/vendor/ace/ext-language_tools.js
# This one need to be in the same folder than aceditor otherwise it's not working
copy_js node_modules/ace-builds/src-min-noconflict/ext-searchbox.js javascripts/ext-searchbox.js

# iframe-resizer
mkdir -p javascripts/vendor/iframe-resizer &&
	copy_js node_modules/iframe-resizer/js/iframeResizer.min.js javascripts/vendor/iframe-resizer/iframeResizer.min.js &&
	copy_js node_modules/iframe-resizer/js/iframeResizer.contentWindow.min.js javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js

# opening_hours
#
# The one asset here fetched from the open internet rather than from node_modules, and the
# only reason this script can hang. It had no timeout at all: when openingh.openstreetmap.de
# is slow, down or behind a proxy, `curl` waits forever, `yarn postinstall` never returns and
# the docker entrypoint never reaches php-fpm -- so nginx serves 502 to the e2e reset and the
# whole browser suite is unrunnable. Ticket 25 recorded that as the reason the suite went
# unrun for three tickets; this is that single line.
#
# Bounded now, and non-fatal: a build that cannot reach the host keeps whatever copy is
# already vendored and says so, rather than truncating the file to nothing (`>` opens the
# target before curl runs, so a failed fetch used to leave an empty file behind).
#
# `pipefail` is what makes that true, and it is not decoration. A shell pipeline reports the
# exit status of its LAST command, so `curl … | sed > tmp` succeeded whenever `sed` did --
# which is always, including when curl died mid-download. `-s tmp` then found a non-empty
# file and moved it into place: a 15929-byte prefix of a 332965-byte library, syntactically
# broken, vendored, and silent. Setting it around this one fetch rather than for the whole
# script keeps the `cp -f -r` calls above free to fail the way they always have.
mkdir -p javascripts/vendor/opening_hours
opening_hours_target=javascripts/vendor/opening_hours/opening_hours.js
if (set -o pipefail; curl -sS --connect-timeout 5 --max-time 30 \
		https://openingh.openstreetmap.de/opening_hours.js/opening_hours+deps.min.js \
		| sed '/^[[:space:]]*\/\/#[[:space:]]*sourceMappingURL=/d' \
		> "${opening_hours_target}.tmp") && [ -s "${opening_hours_target}.tmp" ]; then
	mv "${opening_hours_target}.tmp" "${opening_hours_target}"
else
	rm -f "${opening_hours_target}.tmp"
	if [ -s "${opening_hours_target}" ]; then
		echo "warning: could not fetch opening_hours.js; keeping the vendored copy" >&2
	else
		echo "warning: could not fetch opening_hours.js and none is vendored; the opening-hours field will not work" >&2
	fi
fi

# htmx
mkdir -p javascripts/vendor/htmx && copy_js node_modules/htmx.org/dist/htmx.min.js javascripts/vendor/htmx/htmx.min.js

# mermaid
mkdir -p javascripts/vendor/mermaid/chunks/mermaid.esm.min &&
	copy_js node_modules/mermaid/dist/mermaid.esm.min.mjs javascripts/vendor/mermaid/mermaid.esm.min.mjs
for f in node_modules/mermaid/dist/chunks/mermaid.esm.min/*; do
	copy_js "$f" "javascripts/vendor/mermaid/chunks/mermaid.esm.min/$(basename "$f")"
done
