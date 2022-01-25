// Extract files that we need from the node_modules folder
// The extracted files are integrated to the repository, so production server don't need to
// have node installed

// Include fs and path module

const fs = require('fs-extra')
const path = require('path');
const basePath = path.join(__dirname, '../../');

function copySync(src,dest,opts){
  if (fs.existsSync( src )){
    fs.copySync(path.join(basePath,src),path.join(basePath,dest),opts);
  } else {
    console.log(src+" is not existing !");
  }
}

// Jquery
copySync('node_modules/jquery/dist/jquery.min.js','javascripts/vendor/jquery/jquery.min.js',{overwrite:true});

// Fontawesome
copySync('node_modules/@fortawesome/fontawesome-free/webfonts','styles/vendor/fontawesome/webfonts',{overwrite:true});
copySync('node_modules/@fortawesome/fontawesome-free/css/all.min.css','styles/vendor/fontawesome/css/all.min.css',{overwrite:true});

// Bootstrap
copySync('node_modules/bootstrap/dist/js/bootstrap.min.js','javascripts/vendor/bootstrap/bootstrap.min.js',{overwrite:true});
copySync('node_modules/bootstrap/dist/css/bootstrap.min.css','styles/vendor/bootstrap/css/bootstrap.min.css',{overwrite:true});
copySync('node_modules/bootstrap/dist/fonts','styles/vendor/bootstrap/fonts',{overwrite:true});

//  Vue
copySync('node_modules/vue/dist/vue.js','javascripts/vendor/vue/vue.js',{overwrite:true});
copySync('node_modules/vue/dist/vue.min.js','javascripts/vendor/vue/vue.min.js',{overwrite:true});

// Vue Select
copySync('node_modules/vue-select/dist/vue-select.js','javascripts/vendor/vue-select/vue-select.min.js',{overwrite:true});
copySync('node_modules/vue-select/dist/vue-select.css','styles/vendor/vue-select/vue-select.css',{overwrite:true});

// Vue Leaflet
copySync('node_modules/vue2-leaflet/dist/vue2-leaflet.min.js','javascripts/vendor/vue2-leaflet/vue2-leaflet.js',{overwrite:true});

// Leaflet
copySync('node_modules/leaflet/dist/leaflet.js','javascripts/vendor/leaflet/leaflet.min.js',{overwrite:true});
copySync('node_modules/leaflet/dist/leaflet.css','styles/vendor/leaflet/leaflet.css',{overwrite:true});
copySync('node_modules/leaflet/dist/images','styles/vendor/leaflet/images',{overwrite:true});

// Leaflet Markercluster
copySync('node_modules/leaflet.markercluster/dist/leaflet.markercluster.js','javascripts/vendor/leaflet-markercluster/leaflet-markercluster.min.js',{overwrite:true});
copySync('node_modules/leaflet.markercluster/dist/MarkerCluster.css','styles/vendor/leaflet-markercluster/leaflet-markercluster.css',{overwrite:true});
// Leaflet Providers
copySync('node_modules/leaflet-providers/leaflet-providers.js','javascripts/vendor/leaflet-providers/leaflet-providers.js',{overwrite:true});
// Leaflet Fullscreen
copySync('node_modules/leaflet.fullscreen/Control.FullScreen.js','javascripts/vendor/leaflet-fullscreen/leaflet-fullscreen.js',{overwrite:true});
copySync('node_modules/leaflet.fullscreen/Control.FullScreen.css','styles/vendor/leaflet-fullscreen/leaflet-fullscreen.css',{overwrite:true});

// GoGoCartoJs
copySync('node_modules/gogocarto-js/dist/gogocarto.js','javascripts/vendor/gogocarto/gogocarto.min.js',{overwrite:true});
copySync('node_modules/gogocarto-js/dist/gogocarto.css','styles/vendor/gogocarto/gogocarto.min.css',{overwrite:true});
copySync('node_modules/gogocarto-js/dist/images','styles/vendor/gogocarto/images',{overwrite:true});
copySync('node_modules/gogocarto-js/dist/fonts','styles/vendor/gogocarto/fonts',{overwrite:true});

// Spectrum Color Picker
copySync('node_modules/spectrum-colorpicker2/dist/spectrum.min.js','javascripts/vendor/spectrum-colorpicker2/spectrum.min.js',{overwrite:true});
copySync('node_modules/spectrum-colorpicker2/dist/spectrum.min.css','styles/vendor/spectrum-colorpicker2/spectrum.min.css',{overwrite:true});

// formBuilder
copySync('node_modules/formBuilder/dist/form-builder.min.js','javascripts/vendor/formBuilder/form-builder.min.js',{overwrite:true});

// formbuilder-languages
copySync('node_modules/formbuilder-languages/','javascripts/vendor/formbuilder-languages/',{overwrite:true,
  filter: function (src,dest) {
    return fs.statSync(src).isDirectory() || path.extname(src) == ".lang";
  }});

// jquery-ui-sortable
copySync('node_modules/jquery-ui-sortable/jquery-ui.min.js','javascripts/vendor/jquery-ui-sortable/jquery-ui.min.js',{overwrite:true});
