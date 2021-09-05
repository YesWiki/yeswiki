let BazarMap = {}
if (document.querySelector('.bazar-map')) {
  BazarMap = {
    props: [ 'params' ],
    data() {
      return {
        selectedEntry: null,
        center: null
      }
    },
    components: {
      'l-map': window.Vue2Leaflet.LMap,
      'l-tile-layer': window.Vue2Leaflet.LTileLayer,
      'l-marker': window.Vue2Leaflet.LMarker,
      'l-icon': window.Vue2Leaflet.LIcon
    },
    computed: {
      entries() {
        return this.$root.entriesToDisplay
      },
    },
    methods: {
      markerClass(entry) {
        return `bazar-marker ${this.params.smallmarker} ${this.selectedEntry == entry ? 'selected' : ''}`
      }
    },
    watch: {
      selectedEntry() {
        if (this.selectedEntry) {
          this.$root.getEntryRender(this.selectedEntry)
        }
        this.$nextTick(function() {
          this.$refs.map.mapObject.invalidateSize(true)
        })
      },
      params() {
        this.center = [this.params.latitude, this.params.longitude]
      }
    },
    template: `
    <div class="bazar-map-container" :style="{height: params.height}">
      <l-map v-if="center" ref="map" :zoom="params.zoom" :center="center"
             @click="selectedEntry = null">
        
        <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" attribution="OSM !"></l-tile-layer>

        <l-marker v-for="entry in entries" :key="entry.id_fiche" :visible.sync="entry.visible" 
                  :lat-lng="[entry.bf_latitude, entry.bf_longitude]" @click="selectedEntry = entry">
          <l-icon :class-name="markerClass(entry)" 
                  :icon-size="JSON.parse(params.iconSize)" :icon-anchor="JSON.parse(params.iconAnchor)" 
                  :popup-anchor="JSON.parse(params.popupAnchor)">
            <div class="entry-name" :style="{'background-color': entry.color}">
              {{ entry.bf_titre }}
            </div>
            <div class="bazar-entry" :style="{color: entry.color}">
              <i :class="entry.icon"></i>
            </div>
          </l-icon>
        </l-icon>
        </l-marker>
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