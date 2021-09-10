#!/bin/bash

# Extract files that we need from the node_modules folder
# The extracted files are integrated to the repository, so production server don't need to
# have node installed

mkdir -p javascripts/vendor/jquery && cp -f node_modules/jquery/dist/jquery.min.js javascripts/vendor/jquery

mkdir -p javascripts/vendor/vue && cp -f node_modules/vue/dist/{vue.js,vue.min.js} javascripts/vendor/vue

mkdir -p styles/vendor/fontawesome && cp -f -r node_modules/@fortawesome/fontawesome-free/webfonts styles/vendor/fontawesome
mkdir -p styles/vendor/fontawesome/css && cp -f node_modules/@fortawesome/fontawesome-free/css/all.min.css styles/vendor/fontawesome/css

mkdir -p javascripts/vendor/spectrum-colorpicker2 && cp -f node_modules/spectrum-colorpicker2/dist/spectrum.min.js javascripts/vendor/spectrum-colorpicker2
mkdir -p styles/vendor/spectrum-colorpicker2 && cp -f node_modules/spectrum-colorpicker2/dist/spectrum.min.css styles/vendor/spectrum-colorpicker2

mkdir -p javascripts/vendor/bootstrap && cp -f node_modules/bootstrap/dist/js/bootstrap.min.js javascripts/vendor/bootstrap
mkdir -p styles/vendor/bootstrap/css && cp -f node_modules/bootstrap/dist/css/bootstrap.min.css styles/vendor/bootstrap/css
mkdir -p styles/vendor/bootstrap && cp -f -r node_modules/bootstrap/dist/fonts styles/vendor/bootstrap

mkdir -p javascripts/vendor/vue-select && cp -f node_modules/vue-select/dist/vue-select.js javascripts/vendor/vue-select
mkdir -p styles/vendor/vue-select && cp -f node_modules/vue-select/dist/vue-select.css styles/vendor/vue-select

mkdir -p javascripts/vendor/leaflet && cp -f node_modules/leaflet/dist/leaflet.js javascripts/vendor/leaflet
mkdir -p styles/vendor/leaflet && cp -f node_modules/leaflet/dist/leaflet.css styles/vendor/leaflet
cp -f -r node_modules/leaflet/dist/images styles/vendor/leaflet

mkdir -p javascripts/vendor/leaflet-markercluster && cp -f node_modules/leaflet.markercluster/dist/leaflet.markercluster.js javascripts/vendor/leaflet-markercluster
mkdir -p styles/vendor/leaflet-markercluster && cp -f node_modules/leaflet.markercluster/dist/MarkerCluster.css styles/vendor/leaflet-markercluster

mkdir -p javascripts/vendor/vue2-leaflet && cp -f node_modules/vue2-leaflet/dist/vue2-leaflet.min.js javascripts/vendor/vue2-leaflet