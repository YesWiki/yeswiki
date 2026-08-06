/**
 * Vue 3 Leaflet MarkerCluster component
 * A lightweight wrapper around Leaflet.markercluster
 */
export default {
  props: {
    options: {
      type: Object,
      default() {
        return {}
      },
    },
  },
  emits: ['ready'],
  data() {
    return {
      ready: false,
      mapObject: null,
      parentContainer: null,
    }
  },
  mounted() {
    this.mapObject = new L.MarkerClusterGroup({
      ...this.options,
      showCoverageOnHover: false,
      animate: true,
      spiderfyOnMaxZoom: true,
      spiderfyMaxCount: Infinity,
      spiderfyDistanceMultiplier: 1.1,
      chunkedLoading: true,
      iconCreateFunction(cluster) {
        const childCount = cluster.getChildCount()
        const size =
          childCount < 10 ? 'small' : childCount < 100 ? 'medium' : 'large'
        return new L.DivIcon({
          html: `<div><span>${childCount}</span></div>`,
          className: `marker-cluster ${size}`,
          iconSize: new L.Point(40, 40),
        })
      },
      maxClusterRadius: (zoom) => {
        if (zoom > 10) return 60
        if (zoom > 7) return 70
        return 70
      },
    })

    // Extract event listeners from $attrs (Vue 3 pattern)
    const eventListeners = {}
    Object.keys(this.$attrs).forEach((key) => {
      if (key.startsWith('on') && typeof this.$attrs[key] === 'function') {
        const eventName = key.slice(2).toLowerCase()
        eventListeners[eventName] = this.$attrs[key]
      }
    })
    L.DomEvent.on(this.mapObject, eventListeners)

    // Find parent map component
    let parent = this.$parent
    while (parent && !parent.mapObject) {
      parent = parent.$parent
    }
    this.parentContainer = parent

    // Add to parent map
    if (this.parentContainer && this.parentContainer.mapObject) {
      this.parentContainer.mapObject.addLayer(this.mapObject)
    }

    this.ready = true
    this.$nextTick(() => {
      this.$emit('ready', this.mapObject)
    })
  },
  beforeUnmount() {
    if (this.parentContainer && this.parentContainer.mapObject) {
      this.parentContainer.mapObject.removeLayer(this.mapObject)
    }
  },
  methods: {
    addLayers(layers) {
      if (!layers) return
      this.mapObject.clearLayers()
      this.mapObject.addLayers(layers)
    },
    clearLayers() {
      if (this.mapObject) {
        this.mapObject.clearLayers()
      }
    },
    refreshClusters() {
      if (this.mapObject) {
        this.mapObject.refreshClusters()
      }
    },
  },
  template: `
    <div style="display: none;">
      <slot v-if="ready"></slot>
    </div>
  `,
}
