#!/bin/bash

# Extract files that we need from the node_modules folder
# The extracted files are integrated to the repository, so production server don't need to
# have node installed

# Jquery
mkdir -p javascripts/vendor/jquery && cp -f node_modules/jquery/dist/jquery.min.js javascripts/vendor/jquery/jquery.min.js
# Fontawesome
mkdir -p styles/vendor/fontawesome && cp -f -r node_modules/@fortawesome/fontawesome-free/webfonts styles/vendor/fontawesome
mkdir -p styles/vendor/fontawesome/css && cp -f node_modules/@fortawesome/fontawesome-free/css/all.min.css styles/vendor/fontawesome/css
# Bootstrap
mkdir -p javascripts/vendor/bootstrap && cp -f node_modules/bootstrap/dist/js/bootstrap.min.js javascripts/vendor/bootstrap/bootstrap.min.js
mkdir -p styles/vendor/bootstrap/css && \
  cp -f node_modules/bootstrap/dist/css/bootstrap.min.css styles/vendor/bootstrap/css/bootstrap.min.css && \
  cp -f node_modules/bootstrap/dist/css/bootstrap.min.css.map styles/vendor/bootstrap/css
mkdir -p styles/vendor/bootstrap && cp -f -r node_modules/bootstrap/dist/fonts styles/vendor/bootstrap

#  Vue
mkdir -p javascripts/vendor/vue && cp -f node_modules/vue/dist/{vue.js,vue.min.js} javascripts/vendor/vue
# Vue Select
mkdir -p javascripts/vendor/vue-select && cp -f node_modules/vue-select/dist/vue-select.js javascripts/vendor/vue-select/vue-select.min.js
mkdir -p styles/vendor/vue-select && cp -f node_modules/vue-select/dist/vue-select.css styles/vendor/vue-select
# Vue Leaflet
mkdir -p javascripts/vendor/vue2-leaflet && cp -f node_modules/vue2-leaflet/dist/vue2-leaflet.min.js javascripts/vendor/vue2-leaflet/vue2-leaflet.js
# Vue draggable
mkdir -p javascripts/vendor/sortablejs && cp -f node_modules/sortablejs/Sortable.min.js javascripts/vendor/sortablejs/sortable.js
mkdir -p javascripts/vendor/vuedraggable && cp -f node_modules/vuedraggable/dist/vuedraggable.umd.js javascripts/vendor/vuedraggable/vuedraggable.js

# Leaflet
mkdir -p javascripts/vendor/leaflet && cp -f node_modules/leaflet/dist/leaflet.js javascripts/vendor/leaflet/leaflet.min.js
mkdir -p styles/vendor/leaflet && cp -f node_modules/leaflet/dist/leaflet.css styles/vendor/leaflet
cp -f -r node_modules/leaflet/dist/images styles/vendor/leaflet
# Leaflet Markercluster
mkdir -p javascripts/vendor/leaflet-markercluster && cp -f node_modules/leaflet.markercluster/dist/leaflet.markercluster.js javascripts/vendor/leaflet-markercluster/leaflet-markercluster.min.js
mkdir -p styles/vendor/leaflet-markercluster && cp -f node_modules/leaflet.markercluster/dist/MarkerCluster.css styles/vendor/leaflet-markercluster/leaflet-markercluster.css
# Leaflet Providers
mkdir -p javascripts/vendor/leaflet-providers && cp -f node_modules/leaflet-providers/leaflet-providers.js javascripts/vendor/leaflet-providers
# Leaflet Fullscreen
mkdir -p javascripts/vendor/leaflet-fullscreen && cp -f node_modules/leaflet.fullscreen/Control.FullScreen.js javascripts/vendor/leaflet-fullscreen/leaflet-fullscreen.js
mkdir -p styles/vendor/leaflet-fullscreen && cp -f node_modules/leaflet.fullscreen/Control.FullScreen.css styles/vendor/leaflet-fullscreen/leaflet-fullscreen.css

# GoGoCartoJs
mkdir -p javascripts/vendor/gogocarto && cp -f node_modules/gogocarto-js/dist/gogocarto.js javascripts/vendor/gogocarto/gogocarto.min.js
mkdir -p styles/vendor/gogocarto && cp -f node_modules/gogocarto-js/dist/gogocarto.css styles/vendor/gogocarto/gogocarto.min.css
cp -f -r node_modules/gogocarto-js/dist/images styles/vendor/gogocarto
cp -f -r node_modules/gogocarto-js/dist/fonts styles/vendor/gogocarto

# Spectrum Color Picker
mkdir -p javascripts/vendor/spectrum-colorpicker2 && cp -f node_modules/spectrum-colorpicker2/dist/spectrum.min.js javascripts/vendor/spectrum-colorpicker2/spectrum.min.js
mkdir -p styles/vendor/spectrum-colorpicker2 && cp -f node_modules/spectrum-colorpicker2/dist/spectrum.min.css styles/vendor/spectrum-colorpicker2/spectrum.min.css

# formBuilder
mkdir -p javascripts/vendor/formBuilder && cp -f node_modules/formBuilder/dist/form-builder.min.js javascripts/vendor/formBuilder

# formbuilder-languages
mkdir -p javascripts/vendor/formbuilder-languages && cp -f node_modules/formbuilder-languages/*.lang javascripts/vendor/formbuilder-languages

#jquery-ui-sortable
mkdir -p javascripts/vendor/jquery-ui-sortable && cp -f node_modules/jquery-ui-sortable/jquery-ui.min.js javascripts/vendor/jquery-ui-sortable

# DataTables
mkdir -p javascripts/vendor/datatables-full && \
  cat node_modules/datatables.net/js/jquery.dataTables.min.js \
      node_modules/datatables.net-bs/js/dataTables.bootstrap.min.js \
      node_modules/datatables.net-buttons/js/dataTables.buttons.min.js \
      node_modules/datatables.net-buttons/js/buttons.colVis.min.js \
      node_modules/datatables.net-buttons/js/buttons.flash.min.js \
      node_modules/datatables.net-buttons/js/buttons.html5.min.js \
      node_modules/datatables.net-buttons/js/buttons.print.min.js  \
      node_modules/datatables.net-buttons-bs/js/buttons.bootstrap.min.js  \
      node_modules/datatables.net-fixedheader/js/dataTables.fixedHeader.min.js  \
      node_modules/datatables.net-fixedheader-bs/js/fixedHeader.bootstrap.min.js  \
      node_modules/datatables.net-responsive/js/dataTables.responsive.min.js  \
      node_modules/datatables.net-responsive-bs/js/responsive.bootstrap.min.js  \
      > javascripts/vendor/datatables-full/jquery.dataTables.min.js 

mkdir -p styles/vendor/datatables-full && \
  cat node_modules/datatables.net-bs/css/dataTables.bootstrap.min.css \
      node_modules/datatables.net-buttons-bs/css/buttons.bootstrap.min.css  \
      node_modules/datatables.net-fixedheader-bs/css/fixedHeader.bootstrap.min.css  \
      node_modules/datatables.net-responsive-bs/css/responsive.bootstrap.min.css  \
      > styles/vendor/datatables-full/dataTables.bootstrap.min.css 

# fullcalendar
mkdir -p styles/vendor/fullcalendar && \
  cp -f node_modules/fullcalendar/main.min.css styles/vendor/fullcalendar

mkdir -p javascripts/vendor/fullcalendar && \
  cp -f node_modules/fullcalendar/main.min.js javascripts/vendor/fullcalendar && \
  cp -f node_modules/fullcalendar/locales-all.min.js javascripts/vendor/fullcalendar && \
  cp -f node_modules/fullcalendar/LICENSE.txt javascripts/vendor/fullcalendar && \
  cp -f node_modules/fullcalendar/README.md javascripts/vendor/fullcalendar

# Moment
mkdir -p javascripts/vendor/moment && \
  cp -f node_modules/moment/min/moment-with-locales.min.js javascripts/vendor/moment && \
  cp -f node_modules/moment/min/moment-with-locales.min.js.map javascripts/vendor/moment

# Docsify
# Sept 2022: Docsify have vulnerability warnings, so we remove it from our dependency
# so the github warning disappear. Please include it back in package.json when issue is fixed
# mkdir -p javascripts/vendor/docsify && \
#   cp -f node_modules/docsify/lib/docsify.min.js javascripts/vendor/docsify && \
#   cp -f node_modules/docsify/LICENSE javascripts/vendor/docsify && \
#   cp -f node_modules/docsify/README.md javascripts/vendor/docsify
mkdir -p javascripts/vendor/docsify/plugins && \
  # cp -f node_modules/docsify/lib/plugins/*.min.js javascripts/vendor/docsify/plugins && \
  cp -f node_modules/docsify-copy-code/dist/*.min.js javascripts/vendor/docsify/plugins
  cp -f node_modules/docsify-copy-code/LICENSE javascripts/vendor/docsify/plugins/LICENSE-docisfy-copy-code
# mkdir -p styles/vendor/docsify && \
#   cat node_modules/docsify/lib/themes/vue.css \
#     | sed -E "s|(@import url\(\"https://fonts.googleapis.com)|/*  \n  This file has been modified just to remove google font import on first line\n  It's based on Vue theme maintained by docsify\n  https://cdn.jsdelivr.net/npm/docsify/lib/themes/vue.css\n */\n/* \1|g" \
#     | sed -E 's|("\);)(\*\{-webkit)|\1 */\n\2|g' \
#     > styles/vendor/docsify/vue-theme-modified.min.css

# Lazysizes
mkdir -p javascripts/vendor/lazysizes && \
  cp -f node_modules/lazysizes/lazysizes.min.js javascripts/vendor/lazysizes && \
  cp -f node_modules/lazysizes/LICENSE javascripts/vendor/lazysizes && \
  cp -f node_modules/lazysizes/README.md javascripts/vendor/lazysizes

# Ace
mkdir -p javascripts/vendor/ace && \
  cp -f node_modules/ace-builds/src-min-noconflict/ace.js javascripts/vendor/ace && \
  cp -f node_modules/ace-builds/src-min-noconflict/mode-html.js javascripts/vendor/ace && \
  cp -f node_modules/ace-builds/src-min-noconflict/worker-html.js javascripts/vendor/ace && \
  cp -f node_modules/ace-builds/src-min-noconflict/mode-markdown.js javascripts/vendor/ace &&\
  cp -f node_modules/ace-builds/src-min-noconflict/ext-language_tools.js javascripts/vendor/ace
# This one need to be in the same folder than aceditor otherwise it's not working
cp -f node_modules/ace-builds/src-min-noconflict/ext-searchbox.js tools/aceditor/presentation/javascripts

# iframe-resizer
mkdir -p javascripts/vendor/iframe-resizer && \
  cp -f node_modules/iframe-resizer/js/iframeResizer.min.js javascripts/vendor/iframe-resizer && \
  cp -f node_modules/iframe-resizer/js/iframeResizer.contentWindow.min.js javascripts/vendor/iframe-resizer && \
  cp -f node_modules/iframe-resizer/LICENSE javascripts/vendor/iframe-resizer
