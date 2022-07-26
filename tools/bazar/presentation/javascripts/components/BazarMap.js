
import LeafletMarkerCluster from './LeafletMarkerCluster.js'
import SpinnerLoader from './SpinnerLoader.js'

// New for VueJs3
const { nextTick } = Vue
// allow usage of wiki in templates
Vue.prototype.wiki = wiki;

window.bazarVueApp.component('BazarMap', {
  props: [ 'params' ],
  components: {
    'l-map': window['vue-leaflet'].LMap,
    'l-tile-layer': window['vue-leaflet'].LTileLayer,
    'l-marker': window['vue-leaflet'].LMarker,
    'l-icon': window['vue-leaflet'].LIcon,
    'l-marker-cluster': LeafletMarkerCluster,
    'spinner-loader': SpinnerLoader
  },
  data() {
    return {
      selectedEntry: null,
      center: null,
      bounds: null,
      layers: {}
    }
  },
  computed: {
    entries() {
      return this.$root.entriesToDisplay.filter(entry => entry.bf_latitude && entry.bf_longitude)
    },
    map() {
      return this.$refs.map ? this.$refs.map.mapObject : null
    },
    mapOptions() {
      return {
        scrollWheelZoom: this.params.zoom_molette,
        zoomControl: this.params.navigation,
        fullscreenControl: this.params.fullscreen,
        fullscreenControlOptions: {
          forceSeparateButton: true,
          title: _t('BAZ_FULLSCREEN'), // change the title of the button, default Full Screen
          titleCancel: _t('BAZ_BACK_TO_NORMAL_VIEW'), // change the title of the button when fullscreen is on, default Exit Full Screen
          // content: '<i class="fa fa-expand-alt"></i>', // change the content of the button, can be HTML, default null
          forceSeparateButton: true, // force seperate button to detach from zoom buttons, default false
        },
        maxZoom: 18
      }
    }
  },
  methods: {
    updateBounds() {
      if (!this.$refs.map) return
      this.bounds = this.map.getBounds()        
    },
    createTileLayers() {
      if (!this.map) return
      let provideOptions = this.params.provider_credentials ? JSON.parse(this.params.provider_credentials) : {}
      L.tileLayer.provider(this.params.provider, provideOptions).addTo(this.map)

      for (let layer of this.params.layers) {
        let [label, type, options, url] = layer.split('|')
        if (!url) { url = options; options = ""; }
        switch (type.toLowerCase()) {
          case 'tiles':
            this.layers[label] = L.tileLayer(url).addTo(this.map)
            break;
          case 'geojson':
            this.layers[label] = L.geoJson.ajax(url, {
              style: function (feature, latlng) {
                if (feature.geometry.type == "Point") return
                let props = feature.properties || {};
                // convert options string "color: blue; fill: red" to object
                options.split(';').forEach(o => {
                  if (!0) return
                  let [key, value] = o.split(':')
                  props[key.trim()] = value.trim().replaceAll("'", '')
                })
                return { ...{
                  fillColor: wiki.cssVar('--primary-color'),
                  fillOpacity: 0.1,
                  color: wiki.cssVar('--primary-color'),
                  opacity: 1,
                  weight: 3,
                }, ...props };
              },
              pointToLayer: function (feature, latlng) {
                return L.circleMarker(latlng);
              },
            }).addTo(this.map)
            break;
          default:
            alert(`Error in Layers parameter: type ${type} is unknown` )
            break;
        }
      }
    },
    arraysEqual(a, b) {
      if (a === b) return true;
      if (a == null || b == null) return false;
      if (a.length !== b.length) return false;

      a.sort(); b.sort()
      for (var i = 0; i < a.length; ++i) {
        if (a[i] !== b[i]) return false;
      }
      return true;
    },
    createMarker(entry) {
      if (entry.marker) return entry.marker
      try {
        entry.marker = L.marker([entry.bf_latitude, entry.bf_longitude], { riseOnHover: true });
        let isLink = (this.isModalDisplay() || this.isDirectLinkDisplay() || this.isNewTabDisplay());
        let tagName =  isLink ? 'a' : 'div';
        let url = entry.url + (this.isModalDisplay() ? '/iframe':'');
        let modalData = this.isModalDisplay() ? 'data-size="modal-lg" data-iframe="1"' : '';
        entry.marker.setIcon(
          L.divIcon({
            className: `bazar-marker ${this.params.smallmarker}`,
            iconSize: JSON.parse(this.params.iconSize),
            iconAnchor: JSON.parse(this.params.iconAnchor),
            popupAnchor: JSON.parse(this.params.popupAnchor),
            html: `
              <div class="entry-name">
                <span style="background-color: ${entry.color}">
                  ${entry.markerhover || entry.bf_titre}
                </span>
              </div>
              <${tagName} class="bazar-entry${this.isModalDisplay() ? ' modalbox': ''}" `+
              `${isLink ? `href="${url}" title="${entry.bf_titre}"`:''} style="color: ${entry.color}" ${modalData}>
                <i class="${entry.icon || 'fa fa-bullseye'}"></i>
              </${tagName}>`,
          })
        );
        if (this.isDirectLinkDisplay()){
          let BazarMap = this;
          entry.marker.on('click', function() {
            event.preventDefault();
            window.location = entry.url + (BazarMap.$root.isInIframe() ? '/iframe' : '');
          });
        } else if (this.isNewTabDisplay()){
          entry.marker.on('click', function() {
            event.preventDefault();
            window.open(entry.url);
            this.selectedEntry = entry
          });
        } else if (!isLink ){
          entry.marker.on('click', (ev) => {
            this.selectedEntry = entry
          });
        }
        return entry.marker
      } catch(e) {
        entry.marker = null
        console.error(`Entry ${entry.id_fiche} has invalid geolocation`, entry, e)
      }
    },  
    isModalDisplay: function (){
      return (this.params.entrydisplay != undefined && this.params.entrydisplay == 'modal');
    },
    isNewTabDisplay: function (){
      return (this.params.entrydisplay != undefined && this.params.entrydisplay == 'newtab');
    },
    isDirectLinkDisplay: function (){
      return (this.params.entrydisplay != undefined && this.params.entrydisplay == 'direct');
    },
    openPopup(entry) {
      if (entry.marker == undefined) {
        return false;
      }
      let bazarMap = this;
      if (this.$scopedSlots.popupentrywithhtmlrender != undefined){
        if (entry.html_render == undefined) {
          let url = "";
          let excludeFields = "";
          if (this.params.popupselectedfields && this.params.popupselectedfields.length > 0){
            let necessaryFieldsArray = this.params.popupselectedfields.split(',');
            let keys = Object.keys(this.$root.formFields);
            for (let index = 0; index < keys.length; index++) {
              const key = keys[index];
              if (['id_fiche','id_typeannonce','url','color','icon','visual','marker'].indexOf(key) == -1 &&
                  necessaryFieldsArray.indexOf(key) == -1) {
                excludeFields = excludeFields.length == 0 ? key : excludeFields + ',' + key;
              }
            }
          }
          if (this.$root.isExternalUrl(entry)){
            url = entry.url.replace(new RegExp(`${entry.id_fiche}$`),`api/entries/html/${entry.id_fiche}`);
            if (excludeFields.length > 0){
              url = url + (url.match('?') ? '&' : '?') + 
              `excludeFields=${excludeFields}`;
            }
          } else {
            url = wiki.url(`?api/entries/html/${entry.id_fiche}`, {
              ...{
                fields: 'html_output',
              },
              ...(
                (excludeFields.length > 0)
                ? {
                  excludeFields:excludeFields
                }
                : {}
              )
            });
          }
          $.getJSON(url, function(data) {
            Vue.set(entry, 'html_render', (data[entry.id_fiche] && data[entry.id_fiche].html_output) ? data[entry.id_fiche].html_output : 'error')
            nextTick(function () {
              /**
               * Triggers when the component is ready
               * */
               bazarMap.definePopupContent(entry);
            });
          })
        } else {
          nextTick(function () {
            /**
             * Triggers when the component is ready
             * */
             bazarMap.definePopupContent(entry);
          });
        }
      } else if (this.$scopedSlots.popupentry != undefined){
        nextTick(function () {
          /**
           * Triggers when the component is ready
           * */
           bazarMap.definePopupContent(entry);
        });
      }
    },
    definePopupContent: function(entry){
      let renderedHtml = (this.$scopedSlots.popupentrywithhtml != undefined)
        ? $(this.$el).find('.popupentry-container.with-html-render > div').first().html()
        : $(this.$el).find('.popupentry-container > div').first().html();
      if (entry.marker.popup == undefined){
        if (renderedHtml != undefined && renderedHtml.length != 0){
          entry.marker.bindPopup(renderedHtml,{keepInView:true}).openPopup();
        }
      } else {
        entry.marker.popup.openPopup();
      }
    }
  },
  watch: {
    selectedEntry: function (newVal, oldVal) {
      if (oldVal && oldVal.marker && oldVal.marker._icon) oldVal.marker._icon.classList.remove('selected')
      if (this.selectedEntry) {
        if (this.params.entrydisplay == 'newtab') {
          this.$root.openEntry(this.selectedEntry)
        } else if (this.params.entrydisplay == 'sidebar') {
          this.$root.getEntryRender(this.selectedEntry)
        } else if (this.params.entrydisplay == 'popup') {
          this.openPopup(this.selectedEntry)
        }
        let bazarMap = this;
        
        nextTick(function() {
          bazarMap.selectedEntry.marker._icon.classList.add('selected')
        })
      }
    },
    params: {
      handler() {
        this.center = [this.params.latitude, this.params.longitude]
      },
      deep: true
    },
    entries: {
      handler(newVal, oldVal) {
        let newIds = newVal.map(e => e.id_fiche)
        let oldIds = oldVal.map(e => e.id_fiche)
        if (!this.arraysEqual(newIds, oldIds)) {
          let bazarMap = this;
          nextTick(function() {
            bazarMap.entries.forEach(entry => bazarMap.createMarker(entry))
            let entries = bazarMap.entries.filter(entry => entry.marker) // remove entries without marker (prob error creating it)
            if (bazarMap.params.cluster) {
              if (bazarMap.$refs.cluster){
                // cluster could be undefined before mounting component
                bazarMap.$refs.cluster.addLayers(entries.map(entry => entry.marker))
              }
            } else {
              oldVal.filter(entry => entry.marker).forEach(entry => entry.marker.remove())
              entries.forEach(entry => {
                try { entry.marker.addTo(bazarMap.map) }
                catch(error) { console.error(`Entry ${entry.id_fiche} has invalid geolocation`, error) }
              })
            }
          })
        }
      },
      deep: true
    }
  },
  template: `
    <div class="bazar-map-container" :style="{height: params.height}"
        :class="{'small-width': $el ? $el.offsetWidth < 800 : true, 'small-height': $el ? $el.offsetHeight < 600 : true }">
      
      <l-map v-if="center" ref="map" :zoom="params.zoom" :center="center"
             :options="mapOptions"
             @update:center="updateBounds()" @ready="updateBounds(); createTileLayers()"
             @click="selectedEntry = null">
        <l-marker-cluster ref="cluster" ></l-marker-cluster>
      </l-map>
      

      <!-- SideNav to display entry -->
      <div v-if="selectedEntry && this.params.entrydisplay == 'sidebar'" class="entry-container">
        <div class="btn-close" @click="selectedEntry = null"><i class="fa fa-times"></i></div>
        <div v-html="selectedEntry.html_render"></div>
      </div>`+
      // popup content
      `<div v-if="selectedEntry && this.params.entrydisplay == 'popup'" class="popupentry-container with-html-render">
        <slot name="popupentrywithhtmlrender" v-bind="{entry:selectedEntry}"></slot>
      </div>
      <div v-if="selectedEntry && this.params.entrydisplay == 'popup'" class="popupentry-container">
        <slot name="popupentry" v-bind="{entry:selectedEntry}"></slot>
      </div>

      <spinner-loader v-if="this.$root.isLoading || !this.$root.ready" class="overlay super-overlay"></spinner-loader>
    </div>
  `
})
