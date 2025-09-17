Vue.component('opening-hours-create', {
  data() {
    return {
      opening_days: [],
      field: {},
      title: '',
      locale: new URLSearchParams(document.URL).get('lang') || navigator.language,
      dayNames: [{ key: 'Mo', val: 0 }, { key: 'Tu', val: 1 }, { key: 'We', val: 2 },
        { key: 'Th', val: 3 }, { key: 'Fr', val: 4 }, { key: 'Sa', val: 5 }, { key: 'Su', val: 6 }]
    }
  },
  mounted() {
    this.opening_days = this.parseHours(this.$el.dataset.openinghours)
    this.field = JSON.parse(this.$el.dataset.field)
    this.title = this.$el.dataset.title
    this.dayNames = this.dayNames.map((val) => {
      const name = new Date(`2025-03-1${val.val}`)
        .toLocaleDateString(this.locale, { weekday: 'long' })
      return { key: val.key, val: name }
    })
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
        const hours = interval.hours.map((hour) => `${hour.start}-${hour.end}`)
        intervals.push(`${interval.days.join(',')} ${hours.join(',')}`)
      }
      return intervals.join(';')
    }
  },
  template: `
  <div class="opening-hours control-group form-group">
    <label class="control-label col-sm-3"> {{ field.label }} </label>
    <div>
        <div class="opening-hours-interval" v-for="interval in opening_days">
            <select v-model="interval.days" multiple title="${_t('BAZ_OPENING_HOURS_HELP_DAY')}">
                <option v-for="d in dayNames" :value="d.key">{{ d.val }}</option>
            </select>
            <div class="opening-hours-block-hours">
                <div class="opening-hours-hours">
                    <div v-for="hour in interval.hours">
                        <input type="time" v-model="hour.start"/>
                        <input type="time" v-model="hour.end"/>
                        <button class="btn btn-default btn-mini btn-xs"
                          title="${_t('BAZ_OPENING_HOURS_REMOVE_HOUR')}"
                          @click="deleteHour(hour, interval)" type="button">
                            <i style="margin: 0px;" class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn btn-default btn-large btn-xs"
                  title="${_t('BAZ_OPENING_HOURS_ADD_HOUR')}" @click="addTime(interval)">
                    <i style="margin: 0px;" class="fas fa-plus-circle"></i>
                </button>
            </div>
            <button class="btn btn-danger btn-large" style="margin-left: auto;"
              @click="deleteDay(interval)" title="${_t('BAZ_OPENING_HOURS_REMOVE_DAY')}"
              type="button">
                <i style="margin: 0px;" class="fa fa-trash"></i>
            </button>
        </div>

        <button type="button" class="btn btn-info btn-block" title="${_t('BAZ_OPENING_HOURS_ADD_DAY')}"
          @click="addDay()">
            <i style="margin: 0px;" class="fas fa-plus-circle"></i>
            ${_t('BAZ_OPENING_HOURS_ADD_DAY')}
        </button>
        <input type="hidden" :name=field.name  :value="openingHours">
    </div>
  </div>`
})

const elements = document.getElementsByTagName('opening-hours-create')
const arr = Array.prototype.slice.call(elements)
arr.forEach((el) => {
  new Vue({ el })
})
