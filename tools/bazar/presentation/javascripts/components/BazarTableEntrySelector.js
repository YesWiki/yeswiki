Vue.component('BazarTableEntrySelector', {
  props: ['params', 'entries'],
  data() {
    return {}
  },
  computed: {
    entriesToDisplay() {
      return (this.params !== null && typeof this.params === 'object' && 'tablewith' in this.params && this.params.tablewith === 'no-geolocation')
        ? this.entries.filter((e) => typeof e === 'object' && e !== null && (
		  	        (this.params.geolocationfield && !(this.params.geolocationfield in e))
		  	        || (this.params.geolocationfield && this.params.geolocationfield in e && !e[this.params.geolocationfield].latitude)
		  	        || (this.params.geolocationfield && this.params.geolocationfield in e && !e[this.params.geolocationfield].longitude)
                    || String(e[this.params.geolocationfield].latitude).length === 0
                    || String(e[this.params.geolocationfield].longitude).length === 0
                    || Number(e[this.params.geolocationfield].latitude) === 0
                    || Number(e[this.params.geolocationfield].longitude) === 0
        ))
        : this.entries
    }
  },
  template: `
      <div>
        <slot name="bazarlist" v-bind="{entriesToDisplay:entriesToDisplay}"/>
      </div>
    `
})
