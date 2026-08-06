/**
 * Simple Vue 3 Leaflet Map component
 * A lightweight wrapper around Leaflet that works without a bundler
 */
export default {
  name: 'LMap',
  props: {
    zoom: {
      type: Number,
      default: 13,
    },
    center: {
      type: Array,
      default: () => [0, 0],
    },
    options: {
      type: Object,
      default: () => ({}),
    },
  },
  emits: ['ready', 'click', 'update:center', 'update:zoom'],
  data() {
    return {
      mapObject: null,
      ready: false,
    }
  },
  mounted() {
    this.$nextTick(() => {
      this.initMap()
    })
  },
  beforeUnmount() {
    if (this.mapObject) {
      this.mapObject.remove()
      this.mapObject = null
    }
  },
  watch: {
    center: {
      handler(newCenter) {
        if (this.mapObject && newCenter) {
          const currentCenter = this.mapObject.getCenter()
          if (
            currentCenter.lat !== newCenter[0] ||
            currentCenter.lng !== newCenter[1]
          ) {
            this.mapObject.setView(newCenter, this.mapObject.getZoom())
          }
        }
      },
      deep: true,
    },
    zoom(newZoom) {
      if (this.mapObject && newZoom !== this.mapObject.getZoom()) {
        this.mapObject.setZoom(newZoom)
      }
    },
  },
  methods: {
    initMap() {
      if (!this.$refs.mapContainer) return

      // Merge default options with provided options
      const mapOptions = {
        center: this.center,
        zoom: this.zoom,
        ...this.options,
      }

      // Create the Leaflet map
      this.mapObject = L.map(this.$refs.mapContainer, mapOptions)

      // Set up event listeners
      this.mapObject.on('click', (e) => {
        this.$emit('click', e)
      })

      this.mapObject.on('moveend', () => {
        const center = this.mapObject.getCenter()
        this.$emit('update:center', [center.lat, center.lng])
      })

      this.mapObject.on('zoomend', () => {
        this.$emit('update:zoom', this.mapObject.getZoom())
      })

      this.ready = true
      this.$emit('ready', this.mapObject)
    },
    // Expose methods that might be needed
    setView(center, zoom) {
      if (this.mapObject) {
        this.mapObject.setView(center, zoom)
      }
    },
    fitBounds(bounds, options) {
      if (this.mapObject) {
        this.mapObject.fitBounds(bounds, options)
      }
    },
    getBounds() {
      return this.mapObject ? this.mapObject.getBounds() : null
    },
    getZoom() {
      return this.mapObject ? this.mapObject.getZoom() : this.zoom
    },
    getCenter() {
      return this.mapObject ? this.mapObject.getCenter() : this.center
    },
  },
  template: `
    <div ref="mapContainer" style="width: 100%; height: 100%;">
      <slot v-if="ready"></slot>
    </div>
  `,
}
