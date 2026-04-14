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

# Jquery
mkdir -p javascripts/vendor/jquery && copy_js node_modules/jquery/dist/jquery.min.js javascripts/vendor/jquery/jquery.min.js
# Fontawesome
mkdir -p styles/vendor/fontawesome && cp -f -r node_modules/@fortawesome/fontawesome-free/webfonts styles/vendor/fontawesome
mkdir -p styles/vendor/fontawesome/css && copy_css node_modules/@fortawesome/fontawesome-free/css/all.min.css styles/vendor/fontawesome/css/all.min.css
# Bootstrap
mkdir -p javascripts/vendor/bootstrap && copy_js node_modules/bootstrap/dist/js/bootstrap.min.js javascripts/vendor/bootstrap/bootstrap.min.js
mkdir -p styles/vendor/bootstrap/css &&
	copy_css node_modules/bootstrap/dist/css/bootstrap.min.css styles/vendor/bootstrap/css/bootstrap.min.css &&
	cp -f node_modules/bootstrap/dist/css/bootstrap.min.css.map styles/vendor/bootstrap/css
mkdir -p styles/vendor/bootstrap && cp -f -r node_modules/bootstrap/dist/fonts styles/vendor/bootstrap

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

# GoGoCartoJs
mkdir -p javascripts/vendor/gogocarto && copy_js node_modules/gogocarto-js/dist/gogocarto.js javascripts/vendor/gogocarto/gogocarto.min.js
mkdir -p styles/vendor/gogocarto && copy_css node_modules/gogocarto-js/dist/gogocarto.css styles/vendor/gogocarto/gogocarto.min.css
cp -f -r node_modules/gogocarto-js/dist/images styles/vendor/gogocarto
cp -f -r node_modules/gogocarto-js/dist/fonts styles/vendor/gogocarto

# Spectrum Color Picker
mkdir -p javascripts/vendor/spectrum-colorpicker2 && copy_js node_modules/spectrum-colorpicker2/dist/spectrum.min.js javascripts/vendor/spectrum-colorpicker2/spectrum.min.js
mkdir -p styles/vendor/spectrum-colorpicker2 && copy_css node_modules/spectrum-colorpicker2/dist/spectrum.min.css styles/vendor/spectrum-colorpicker2/spectrum.min.css

# formBuilder
mkdir -p javascripts/vendor/formBuilder && copy_js node_modules/formBuilder/dist/form-builder.min.js javascripts/vendor/formBuilder/form-builder.min.js

# formbuilder-languages
mkdir -p javascripts/vendor/formbuilder-languages && cp -f node_modules/formbuilder-languages/*.lang javascripts/vendor/formbuilder-languages

#jquery-ui-sortable
mkdir -p javascripts/vendor/jquery-ui-sortable && copy_js node_modules/jquery-ui-sortable/jquery-ui.min.js javascripts/vendor/jquery-ui-sortable/jquery-ui.min.js

# DataTables
mkdir -p javascripts/vendor/datatables-full &&
	cat node_modules/datatables.net/js/jquery.dataTables.min.js \
		node_modules/datatables.net-bs/js/dataTables.bootstrap.min.js \
		node_modules/datatables.net-buttons/js/dataTables.buttons.min.js \
		node_modules/datatables.net-buttons/js/buttons.colVis.min.js \
		node_modules/datatables.net-buttons/js/buttons.flash.min.js \
		node_modules/datatables.net-buttons/js/buttons.html5.min.js \
		node_modules/datatables.net-buttons/js/buttons.print.min.js \
		node_modules/datatables.net-buttons-bs/js/buttons.bootstrap.min.js \
		node_modules/datatables.net-fixedheader/js/dataTables.fixedHeader.min.js \
		node_modules/datatables.net-fixedheader-bs/js/fixedHeader.bootstrap.min.js \
		node_modules/datatables.net-responsive/js/dataTables.responsive.min.js \
		node_modules/datatables.net-responsive-bs/js/responsive.bootstrap.min.js \
		| sed '/^[[:space:]]*\/\/#[[:space:]]*sourceMappingURL=/d' \
		>javascripts/vendor/datatables-full/jquery.dataTables.min.js

mkdir -p styles/vendor/datatables-full &&
	cat node_modules/datatables.net-bs/css/dataTables.bootstrap.min.css \
		node_modules/datatables.net-buttons-bs/css/buttons.bootstrap.min.css \
		node_modules/datatables.net-fixedheader-bs/css/fixedHeader.bootstrap.min.css \
		node_modules/datatables.net-responsive-bs/css/responsive.bootstrap.min.css \
		| sed '/^[[:space:]]*\/\*#[[:space:]]*sourceMappingURL=/d' \
		>styles/vendor/datatables-full/dataTables.bootstrap.min.css

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
# Sept 2022: Docsify have vulnerability warnings, so we remove it from our dependency
# so the github warning disappear. Please include it back in package.json when issue is fixed
# mkdir -p javascripts/vendor/docsify && \
#   cp -f node_modules/docsify/lib/docsify.min.js javascripts/vendor/docsify && \
#   cp -f node_modules/docsify/LICENSE javascripts/vendor/docsify && \
#   cp -f node_modules/docsify/README.md javascripts/vendor/docsify
mkdir -p javascripts/vendor/docsify/plugins &&
	# cp -f node_modules/docsify/lib/plugins/*.min.js javascripts/vendor/docsify/plugins && \
	for f in node_modules/docsify-copy-code/dist/*.min.js; do
		copy_js "$f" "javascripts/vendor/docsify/plugins/$(basename "$f")"
	done
cp -f node_modules/docsify-copy-code/LICENSE javascripts/vendor/docsify/plugins/LICENSE-docisfy-copy-code
# mkdir -p styles/vendor/docsify && \
#   cat node_modules/docsify/lib/themes/vue.css \
#     | sed -E "s|(@import url\(\"https://fonts.googleapis.com)|/*  \n  This file has been modified just to remove google font import on first line\n  It's based on Vue theme maintained by docsify\n  https://cdn.jsdelivr.net/npm/docsify/lib/themes/vue.css\n */\n/* \1|g" \
#     | sed -E 's|("\);)(\*\{-webkit)|\1 */\n\2|g' \
#     > styles/vendor/docsify/vue-theme-modified.min.css

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
	copy_js node_modules/ace-builds/src-min-noconflict/ext-language_tools.js javascripts/vendor/ace/ext-language_tools.js
# This one need to be in the same folder than aceditor otherwise it's not working
copy_js node_modules/ace-builds/src-min-noconflict/ext-searchbox.js tools/aceditor/presentation/javascripts/ext-searchbox.js

# iframe-resizer
mkdir -p javascripts/vendor/iframe-resizer &&
	copy_js node_modules/iframe-resizer/js/iframeResizer.min.js javascripts/vendor/iframe-resizer/iframeResizer.min.js &&
	copy_js node_modules/iframe-resizer/js/iframeResizer.contentWindow.min.js javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js

# opening_hours
mkdir -p javascripts/vendor/opening_hours &&
	curl -s https://openingh.openstreetmap.de/opening_hours.js/opening_hours+deps.min.js \
		| sed '/^[[:space:]]*\/\/#[[:space:]]*sourceMappingURL=/d' \
		> javascripts/vendor/opening_hours/opening_hours.js

# mermaid
mkdir -p javascripts/vendor/mermaid/chunks/mermaid.esm.min &&
	copy_js node_modules/mermaid/dist/mermaid.esm.min.mjs javascripts/vendor/mermaid/mermaid.esm.min.mjs
for f in node_modules/mermaid/dist/chunks/mermaid.esm.min/*; do
	copy_js "$f" "javascripts/vendor/mermaid/chunks/mermaid.esm.min/$(basename "$f")"
done
