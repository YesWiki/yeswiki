const props = {
  options: {
    type: Object,
    default() { return {}; },
  }
}

export default {
  props: props,
  data() {
    return {
      ready: false,
    };
  },
  mounted() {
    this.mapObject = new L.MarkerClusterGroup({...this.options, ...{
      showCoverageOnHover: false,
      animate: false
    } });
    L.DomEvent.on(this.mapObject, this.$listeners);
    window.Vue2Leaflet.propsBinder(this, this.mapObject, props);
    this.ready = true;
    this.parentContainer = window.Vue2Leaflet.findRealParent(this.$parent);
    this.parentContainer.addLayer(this);
    this.$nextTick(() => {
      this.$emit('ready', this.mapObject);
    });
  },
  beforeDestroy() {
    this.parentContainer.removeLayer(this);
  },
  methods: {
    addLayers(layers) {
      console.log("add layers", layers)
      if (!layers) return
      this.mapObject.clearLayers()
      this.mapObject.addLayers(layers.map(l => l.mapObject))
    },
    addLayer(layer, alreadyAdded) {
      // do nothing so we can add layers with addLayers bulk method
    },
    removeLayer(layer, alreadyRemoved) {
      // do nothing so we can remove layers with clearLayers bulk method
    }
  },
  template: `
    <div style="display: none;">
      <slot v-if="ready"></slot>
    </div>
  `
};