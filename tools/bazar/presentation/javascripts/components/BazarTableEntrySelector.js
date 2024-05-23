Vue.component('BazarTableEntrySelector', {
  props: ['params', 'entries'],
  data() {
    return {}
  },
  computed: {
    entriesToDisplay() {
      return (this.params !== null && typeof this.params === 'object' && 'tablewith' in this.params && this.params.tablewith === 'no-geolocation')
        ? this.entries.filter((e) => typeof e === 'object' && e !== null && (
          !('bf_latitude' in e)
                    || !('bf_longitude' in e)
                    || e.bf_latitude === null
                    || e.bf_longitude === null
                    || String(e.bf_longitude).length === 0
                    || String(e.bf_latitude).length === 0
                    || Number(e.bf_longitude) === 0
                    || Number(e.bf_latitude) === 0
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
