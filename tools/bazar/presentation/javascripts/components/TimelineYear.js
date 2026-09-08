Vue.component('TimelineYear', {
  props: ['entry', 'entries', 'indexentry'],
  computed: {
    newyear() {
      if (this.indexentry === 0) {
        return true
      }
      const CurrentYear = new Date(
        this.entry.bf_date_debut_evenement,
      ).getFullYear()
      const PreviousYear = new Date(
        this.entries[this.indexentry - 1].bf_date_debut_evenement,
      ).getFullYear()

      return CurrentYear !== PreviousYear
    },
  },
  template: `
  <div v-show="newyear" class="timeline-year">
      <h2>{{ new Date(this.entry.bf_date_debut_evenement,
    ).getFullYear() }}</h2>
  </div>
  `,
})
