#!/usr/bin/env bash

# Extract files that we need from the node_modules folder
# The extracted files are integrated to the repository, so production server don't need to
# have node installed

cd "$(dirname "$0")/.." || exit 1

copy_js() { sed '/^[[:space:]]*\/\/#[[:space:]]*sourceMappingURL=/d' "$1" > "$2"; }
copy_css() { sed '/^[[:space:]]*\/\*#[[:space:]]*sourceMappingURL=/d' "$1" > "$2"; }

node src/build-icon-sprite.mjs
mkdir -p javascripts/vendor/vue && copy_js node_modules/vue/dist/vue.global.js javascripts/vendor/vue/vue.js
copy_js node_modules/vue/dist/vue.global.prod.js javascripts/vendor/vue/vue.min.js
mkdir -p javascripts/vendor/vue-select && copy_js node_modules/vue-select/dist/vue-select.umd.js javascripts/vendor/vue-select/vue-select.min.js
mkdir -p styles/vendor/vue-select && copy_css node_modules/vue-select/dist/vue-select.css styles/vendor/vue-select/vue-select.css
mkdir -p javascripts/vendor/sortablejs && copy_js node_modules/sortablejs/Sortable.min.js javascripts/vendor/sortablejs/sortable.js
mkdir -p javascripts/vendor/vuedraggable && copy_js node_modules/vuedraggable/dist/vuedraggable.umd.js javascripts/vendor/vuedraggable/vuedraggable.js

mkdir -p javascripts/vendor/compressorjs && copy_js node_modules/compressorjs/dist/compressor.esm.js javascripts/vendor/compressorjs/compressor.js

mkdir -p javascripts/vendor/leaflet && copy_js node_modules/leaflet/dist/leaflet.js javascripts/vendor/leaflet/leaflet.min.js
mkdir -p styles/vendor/leaflet && copy_css node_modules/leaflet/dist/leaflet.css styles/vendor/leaflet/leaflet.css
cp -f -r node_modules/leaflet/dist/images styles/vendor/leaflet
mkdir -p javascripts/vendor/leaflet-markercluster && copy_js node_modules/leaflet.markercluster/dist/leaflet.markercluster.js javascripts/vendor/leaflet-markercluster/leaflet-markercluster.min.js
mkdir -p styles/vendor/leaflet-markercluster && copy_css node_modules/leaflet.markercluster/dist/MarkerCluster.css styles/vendor/leaflet-markercluster/leaflet-markercluster.css
mkdir -p javascripts/vendor/leaflet-providers && copy_js node_modules/leaflet-providers/leaflet-providers.js javascripts/vendor/leaflet-providers/leaflet-providers.js
mkdir -p javascripts/vendor/leaflet-fullscreen && copy_js node_modules/leaflet.fullscreen/Control.FullScreen.js javascripts/vendor/leaflet-fullscreen/leaflet-fullscreen.js
mkdir -p styles/vendor/leaflet-fullscreen && copy_css node_modules/leaflet.fullscreen/Control.FullScreen.css styles/vendor/leaflet-fullscreen/leaflet-fullscreen.css
mkdir -p javascripts/vendor/leaflet-draw && copy_js node_modules/leaflet-draw/dist/leaflet.draw.js javascripts/vendor/leaflet-draw/leaflet.draw.js
mkdir -p styles/vendor/leaflet-draw && copy_css node_modules/leaflet-draw/dist/leaflet.draw.css styles/vendor/leaflet-draw/leaflet.draw.css
mkdir -p styles/vendor/leaflet-draw/images && cp -f -r node_modules/leaflet-draw/dist/images/* styles/vendor/leaflet-draw/images

mkdir -p javascripts/vendor/vditor/dist/js/lute &&
	copy_js node_modules/vditor/dist/index.min.js javascripts/vendor/vditor/index.min.js &&
	copy_js node_modules/vditor/dist/js/lute/lute.min.js javascripts/vendor/vditor/dist/js/lute/lute.min.js
mkdir -p styles/vendor/vditor && copy_css node_modules/vditor/dist/index.css styles/vendor/vditor/index.css
mkdir -p javascripts/vendor/vditor/dist/js/icons &&
	copy_js node_modules/vditor/dist/js/icons/ant.js javascripts/vendor/vditor/dist/js/icons/ant.js
mkdir -p javascripts/vendor/vditor/dist/js/i18n &&
	for locale in en_US es_ES fr_FR pt_BR; do
		copy_js "node_modules/vditor/dist/js/i18n/${locale}.js" "javascripts/vendor/vditor/dist/js/i18n/${locale}.js"
	done
mkdir -p javascripts/vendor/vditor/dist/js/i18n &&
	for lang in en_US es_ES fr_FR pt_BR; do
		copy_js "node_modules/vditor/dist/js/i18n/$lang.js" "javascripts/vendor/vditor/dist/js/i18n/$lang.js"
	done
mkdir -p javascripts/vendor/vditor/dist/css/content-theme &&
	copy_css node_modules/vditor/dist/css/content-theme/light.css javascripts/vendor/vditor/dist/css/content-theme/light.css &&
	copy_css node_modules/vditor/dist/css/content-theme/dark.css javascripts/vendor/vditor/dist/css/content-theme/dark.css
mkdir -p javascripts/vendor/vditor/dist/js/highlight.js/styles &&
	copy_js node_modules/vditor/dist/js/highlight.js/highlight.min.js javascripts/vendor/vditor/dist/js/highlight.js/highlight.min.js &&
	copy_js node_modules/vditor/dist/js/highlight.js/third-languages.js javascripts/vendor/vditor/dist/js/highlight.js/third-languages.js &&
	cp -f node_modules/vditor/dist/js/highlight.js/LICENSE javascripts/vendor/vditor/dist/js/highlight.js/LICENSE &&
	copy_css node_modules/vditor/dist/js/highlight.js/styles/github.min.css javascripts/vendor/vditor/dist/js/highlight.js/styles/github.min.css &&
	copy_css node_modules/vditor/dist/js/highlight.js/styles/github-dark.min.css javascripts/vendor/vditor/dist/js/highlight.js/styles/github-dark.min.css

mkdir -p styles/vendor/fullcalendar &&
	copy_css node_modules/fullcalendar/main.min.css styles/vendor/fullcalendar/main.min.css

mkdir -p javascripts/vendor/fullcalendar &&
	copy_js node_modules/fullcalendar/main.min.js javascripts/vendor/fullcalendar/main.min.js &&
	copy_js node_modules/fullcalendar/locales-all.min.js javascripts/vendor/fullcalendar/locales-all.min.js &&
	cp -f node_modules/fullcalendar/LICENSE.txt javascripts/vendor/fullcalendar &&
	cp -f node_modules/fullcalendar/README.md javascripts/vendor/fullcalendar

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
mkdir -p javascripts/vendor/docsify/prism && \
  copy_js node_modules/prismjs/components/prism-php.js javascripts/vendor/docsify/prism/prism-php.js && \
  copy_js node_modules/prismjs/components/prism-nginx.js javascripts/vendor/docsify/prism/prism-nginx.js
mkdir -p styles/vendor/docsify && \
  cat node_modules/docsify/themes/vue.css \
    | sed -E "s|(@import url\(\"https://fonts.googleapis.com)|/*  \n  This file has been modified just to remove google font import on first line\n  It's based on Vue theme maintained by docsify\n  https://cdn.jsdelivr.net/npm/docsify/themes/vue.css\n */\n/* \1|g" \
    | sed -E 's|("\);)(\*\{-webkit)|\1 */\n\2|g' \
    > styles/vendor/docsify/vue-theme-modified.min.css

mkdir -p javascripts/vendor/lazysizes &&
	copy_js node_modules/lazysizes/lazysizes.min.js javascripts/vendor/lazysizes/lazysizes.min.js &&
	cp -f node_modules/lazysizes/LICENSE javascripts/vendor/lazysizes &&
	cp -f node_modules/lazysizes/README.md javascripts/vendor/lazysizes

mkdir -p javascripts/vendor/ace &&
	copy_js node_modules/ace-builds/src-min-noconflict/ace.js javascripts/vendor/ace/ace.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/mode-html.js javascripts/vendor/ace/mode-html.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/worker-html.js javascripts/vendor/ace/worker-html.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/mode-markdown.js javascripts/vendor/ace/mode-markdown.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/mode-css.js javascripts/vendor/ace/mode-css.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/worker-css.js javascripts/vendor/ace/worker-css.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/mode-twig.js javascripts/vendor/ace/mode-twig.js &&
	copy_js node_modules/ace-builds/src-min-noconflict/ext-language_tools.js javascripts/vendor/ace/ext-language_tools.js
copy_js node_modules/ace-builds/src-min-noconflict/ext-searchbox.js javascripts/ext-searchbox.js

mkdir -p javascripts/vendor/iframe-resizer &&
	copy_js node_modules/iframe-resizer/js/iframeResizer.min.js javascripts/vendor/iframe-resizer/iframeResizer.min.js &&
	copy_js node_modules/iframe-resizer/js/iframeResizer.contentWindow.min.js javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js

mkdir -p javascripts/vendor/opening_hours
opening_hours_target=javascripts/vendor/opening_hours/opening_hours.js
if (set -o pipefail; curl -fsS --connect-timeout 5 --max-time 30 \
		https://openingh.openstreetmap.de/build/opening_hours+deps.min.js \
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

mkdir -p styles/vendor/animate && copy_css node_modules/animate.css/animate.min.css styles/vendor/animate/animate.min.css

mkdir -p javascripts/vendor/htmx && copy_js node_modules/htmx.org/dist/htmx.min.js javascripts/vendor/htmx/htmx.min.js

mkdir -p javascripts/vendor/altcha && copy_js node_modules/altcha/dist/main/altcha.i18n.min.js javascripts/vendor/altcha/altcha.i18n.min.js

copy_js node_modules/p5/lib/p5.min.js javascripts/vendor/p5.min.js

copy_js node_modules/html5-qrcode/html5-qrcode.min.js javascripts/vendor/html5-qrcode.min.js

mkdir -p javascripts/vendor/leaflet-ajax &&
	copy_js node_modules/leaflet-ajax/dist/leaflet.ajax.min.js javascripts/vendor/leaflet-ajax/leaflet.ajax.min.js
mkdir -p javascripts/vendor/leaflet-spiderfier &&
	copy_js node_modules/overlapping-marker-spiderfier-leaflet/dist/oms.js javascripts/vendor/leaflet-spiderfier/oms.min.js

mkdir -p javascripts/vendor/mermaid/chunks/mermaid.esm.min &&
	copy_js node_modules/mermaid/dist/mermaid.esm.min.mjs javascripts/vendor/mermaid/mermaid.esm.min.mjs
for f in node_modules/mermaid/dist/chunks/mermaid.esm.min/*; do
	case "$f" in
		*.map) continue ;;
	esac
	copy_js "$f" "javascripts/vendor/mermaid/chunks/mermaid.esm.min/$(basename "$f")"
done

mkdir -p javascripts/vendor/emoji-picker-element/i18n javascripts/vendor/emoji-picker-element/data &&
	for f in index picker database; do
		copy_js "node_modules/emoji-picker-element/$f.js" "javascripts/vendor/emoji-picker-element/$f.js"
	done &&
	cp -f node_modules/emoji-picker-element/LICENSE javascripts/vendor/emoji-picker-element/LICENSE
for pair in en:en es:es fr:fr it:it nl:nl pt:pt_PT ru:ru_RU; do
	lang="${pair%%:*}"
	copy_js "node_modules/emoji-picker-element/i18n/${pair##*:}.js" "javascripts/vendor/emoji-picker-element/i18n/$lang.js"
	flavour=cldr
	[ "$lang" = en ] && flavour=emojibase
	cp -f "node_modules/emoji-picker-element-data/$lang/$flavour/data.json" "javascripts/vendor/emoji-picker-element/data/$lang.json"
done
