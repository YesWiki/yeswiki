let BazarMap = {}
import LeafletMarkerCluster from './LeafletMarkerCluster.js'

if (document.querySelector('.bazar-map')) {
  BazarMap = {
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
        // if (!this.bounds) return []
        return this.$root.entriesToDisplay/*.filter(entry => {
          let latLng = L.latLng(entry.bf_latitude, entry.bf_longitude)
          return this.bounds.pad(1.2).contains(latLng)
        })*/
      },
    },
    methods: {
      markerClass(entry) {
        return `bazar-marker ${this.params.smallmarker} ${this.selectedEntry == entry ? 'selected' : ''}`
      },
      updateBounds() {
        if (!this.$refs.map) return
        this.bounds = this.$refs.map.mapObject.getBounds()
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
      }
    },
    watch: {
      selectedEntry() {
        if (this.selectedEntry) {
          this.$root.getEntryRender(this.selectedEntry)
          
        }
        this.$nextTick(function() {
          this.$refs.map.mapObject.invalidateSize(true)
          if (this.selectedEntry) 
            this.center = [this.selectedEntry.bf_latitude, this.selectedEntry.bf_longitude]
        })
      },
      params() {
        this.center = [this.params.latitude, this.params.longitude]
      },
      entries(newVal, oldVal) {
        console.log("entries changed")
        let newIds = newVal.map(e => e.id_fiche)
        let oldIds = oldVal.map(e => e.id_fiche)
        // TODO BazarListdynamic check wether or not use removeLayers instead
        if (!this.arraysEqual(newIds, oldIds)) {
          this.$nextTick(function() {
            this.$refs.cluster.addLayers(this.$refs.markers)
          })
        }
              
      }
    },
    template: `
    <div class="bazar-map-container" :style="{height: params.height}">
      <l-map v-if="center" ref="map" :zoom="params.zoom" :center="center"
             @update:center="updateBounds()" @ready="updateBounds()"
             @click="selectedEntry = null">
        
        <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" attribution="OSM !"></l-tile-layer>

        <l-marker-cluster ref="cluster">
          <l-marker v-for="entry in entries" :key="entry.id_fiche" ref="markers" :visible.sync="entry.visible" 
                    :lat-lng="[entry.bf_latitude, entry.bf_longitude]" @click="selectedEntry = entry">
            <l-icon :class-name="markerClass(entry)" 
                    :icon-size="JSON.parse(params.iconSize)" :icon-anchor="JSON.parse(params.iconAnchor)" 
                    :popup-anchor="JSON.parse(params.popupAnchor)">
              <div class="entry-name">
                <span :style="{'background-color': entry.color}">{{ entry.bf_titre }}</span>
              </div>
              <div class="bazar-entry" :style="{color: entry.color}">
                <i :class="entry.icon"></i>
              </div>
            </l-icon>
          </l-marker>
        </l-marker-cluster>
      </l-map>
      <div v-if="selectedEntry" class="entry-container">
        <div class="btn-close" @click="selectedEntry = null"><i class="fa fa-times"></i></div>
        <div v-html="selectedEntry.html_render"></div>
      </div>
    </div>
    `
  }
  Vue.component('BazarMap', BazarMap)
}
export default BazarMap