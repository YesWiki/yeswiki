new Vue({
  el: '.opening-hours-create',
  data:
  { opening_days: [] },
  mounted() {
    this.opening_days = this.parseHours(this.$el.dataset.openinghours)
  },
  methods: {
    parseHours(openingHours) {
      let result = []
      if (openingHours !== '') {
        const intervals = openingHours.split(';')
        for (const interval of intervals) {
          const [days, hours] = interval.split(' ')
          const splittedHours = hours.split(',').map(
            (hour) => {
              const [start, end] = hour.split('-'); return { start, end }
            }
          )
          result.push({ days: days.split(','), hours: splittedHours })
        }
      } else {
        result = [{
          days: [],
          hours: [{ start: null, end: null }]
        }]
      }
      return result
    },
    addTime(interval) {
      interval.hours.push(
        { start: null, end: null }
      )
    },
    addDay() {
      this.opening_days.push({
        days: [],
        hours: [{ start: null, end: null }]
      })
    },
    deleteHour(hour, day) {
      const index = this.opening_days.indexOf(day)
      this.opening_days[index].hours = this.opening_days[index].hours.filter((node) => hour !== node)
    },
    deleteDay(day) {
      this.opening_days = this.opening_days.filter((node) => node !== day)
    }

  },
  computed: {
    openingHours() {
      const intervals = []
      for (const interval of this.opening_days) {
        const hours = interval.hours.map((hour) => `${hour.start}-${hour.end}`
)
        intervals.push(`${interval.days.join(',')} ${hours.join(',')}`)
      }
      return intervals.join(';')
    }
  }
})
