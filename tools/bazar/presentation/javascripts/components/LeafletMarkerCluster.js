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
      animate: true,
      spiderfyOnMaxZoom: true,
      spiderfyMaxCount: Infinity,
      spiderfyDistanceMultiplier: 1.1,
      chunkedLoading: true,
      iconCreateFunction: function (cluster) {
        const childCount = cluster.getChildCount();
        let size = childCount < 10 ? 'small' : childCount < 100 ? 'medium' : 'large'
        return new L.DivIcon({
          html: `<div><span>${childCount}</span></div>`,
          className: `marker-cluster ${size}`,
          iconSize: new L.Point(40, 40),
        });
      },
      maxClusterRadius: (zoom) => {
        if (zoom > 10) return 60;
        if (zoom > 7) return 70;
        else return 70;
      },
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
      if (!layers) return
      this.mapObject.clearLayers()
      this.mapObject.addLayers(layers)
    },
  },
  template: `
    <div style="display: none;">
      <slot v-if="ready"></slot>
    </div>
  `
};