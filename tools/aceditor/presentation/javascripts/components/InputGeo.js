// ext/Number/Color/slider
export default {
  props: [ 'value', 'config', 'values' ],
  data() {
    return {
      defaultLatitude: 46.22763,
      defaultLongitude: 2.21374,
      defaultZoom: 5,
      map: null
    }
  },
  mounted() {
    this.map = L.map('center-position-map', {
	    center: new L.LatLng(this.defaultLatitude, this.defaultLongitude),
	    zoom: this.defaultZoom,
	    zoomControl: true,
	    scrollWheelZoom : false,
      layers: [L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
      })]
	  });

    this.map.on('load moveend', () => {
      this.$emit('input', this.getValues())
    })
  },
  methods: {
    resetValues() {
      this.map.panTo(new L.LatLng(this.defaultLatitude, this.defaultLongitude))
      this.map.setZoom(this.defaultZoom)
    },
    parseNewValues(newValues) {
      this.map.panTo(new L.LatLng(newValues.lat || this.defaultLatitude, newValues.lon || this.defaultLongitude))
      this.map.setZoom(newValues.zoom || this.defaultZoom)
    },
    getValues() {
      let result = {}
      const lat = this.map.getCenter().lat.toFixed(5)
      const lon = this.map.getCenter().lng.toFixed(5)
      if (lat != this.defaultLatitude) result.lat = lat
      if (lon != this.defaultLongitude) result.lon = lon
      if (this.map.getZoom() != this.defaultZoom) result.zoom = this.map.getZoom()
      return result
    }
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label class="text-center">{{ config.label }}</label>
      <div id="center-position-map"></div>
    </div>
    `
}
