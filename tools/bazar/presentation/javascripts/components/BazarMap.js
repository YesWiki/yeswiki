import LeafletMarkerCluster from './LeafletMarkerCluster.js'

Vue.component('BazarMap', {
  props: [ 'params' ],
  components: {
    'l-map': window.Vue2Leaflet.LMap,
    'l-tile-layer': window.Vue2Leaflet.LTileLayer,
    'l-marker': window.Vue2Leaflet.LMarker,
    'l-icon': window.Vue2Leaflet.LIcon,
    'l-marker-cluster': LeafletMarkerCluster
  },
  data() {
    return {
      selectedEntry: null,
      center: null,
      bounds: null
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
          title: 'Mode plein écran', // change the title of the button, default Full Screen
          titleCancel: 'Retour à la vue normale', // change the title of the button when fullscreen is on, default Exit Full Screen
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
    createTileLayer() {
      if (!this.map) return
      let provideOptions = this.params.provider_credentials ? JSON.parse(this.params.provider_credentials) : {}
      L.tileLayer.provider(this.params.provider, provideOptions).addTo(this.map)
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
        entry.marker.setIcon(
          L.divIcon({
            className: `bazar-marker ${this.params.smallmarker}`,
            iconSize: JSON.parse(this.params.iconSize),
            iconAnchor: JSON.parse(this.params.iconAnchor),
            html: `
              <div class="entry-name">
                <span style="background-color: ${entry.color}">
                  ${this.$root.field(entry, 'markerhover', 'bf_titre')}
                </span>
              </div>
              <div class="bazar-entry" style="color: ${entry.color}">
                <i class="${entry.icon || 'fa fa-bullseye'}"></i>
              </div>`,
          })
        );
        entry.marker.on('click', (ev) => {
          this.selectedEntry = entry
        });
        return entry.marker
      } catch(e) {
        entry.marker = null
        console.error(`Entry ${entry.id_fiche} has invalid geolocation`, entry, e)
      }
    }
  },
  watch: {
    selectedEntry: function (newVal, oldVal) {
      if (oldVal) oldVal.marker._icon.classList.remove('selected')
      if (this.selectedEntry) {
        if (this.params.entrydisplay == 'modal')
          this.$root.openEntryModal(this.selectedEntry)
        else
          this.$root.getEntryRender(this.selectedEntry)
        
        this.$nextTick(function() {
          this.selectedEntry.marker._icon.classList.add('selected')
        })
      }
    },
    params() {
      this.center = [this.params.latitude, this.params.longitude]
    },
    entries(newVal, oldVal) {
      let newIds = newVal.map(e => e.id_fiche)
      let oldIds = oldVal.map(e => e.id_fiche)
      if (!this.arraysEqual(newIds, oldIds)) {
        this.$nextTick(function() {
          this.entries.forEach(entry => this.createMarker(entry))
          let entries = this.entries.filter(entry => entry.marker) // remove entries without marker (prob error creating it)
          if (this.params.cluster) {
            this.$refs.cluster.addLayers(entries.map(entry => entry.marker))
          } else {
            oldVal.filter(entry => entry.marker).forEach(entry => entry.marker.remove())
            entries.forEach(entry => {
              try { entry.marker.addTo(this.map) }
              catch(error) { console.error(`Entry ${entry.id_fiche} has invalid geolocation`, error) }
            })
          }
        })
      }
    }
  },
  template: `
    <div class="bazar-map-container" :style="{height: params.height}"
        :class="{'small-width': $el ? $el.offsetWidth < 800 : true, 'small-height': $el ? $el.offsetHeight < 600 : true }">
      
        <l-map v-if="center" ref="map" :zoom="params.zoom" :center="center"
            :options="mapOptions"
            @update:center="updateBounds()" @ready="updateBounds(); createTileLayer()"
            @click="selectedEntry = null">
        <l-marker-cluster ref="cluster" ></l-marker-cluster>
      </l-map>

      <!-- SideNav to display entry -->
      <div v-if="selectedEntry && this.params.entrydisplay == 'sidebar'" class="entry-container">
        <div class="btn-close" @click="selectedEntry = null"><i class="fa fa-times"></i></div>
        <div v-html="selectedEntry.html_render"></div>
      </div>
    </div>
  `
})
