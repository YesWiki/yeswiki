Vue.component('opening-hours', {
  data() {
    return {
      intervals: [],
      today: [],
      locale: new URLSearchParams(document.URL).get('lang') || navigator.language,
      todayName: new Date().toLocaleDateString(this.locale, { weekday: 'long' })
    }
  },
  mounted() {
    const now = new Date()
    const currentDay = new Date(now.toDateString())
    const endWeek = new Date(currentDay)
    endWeek.setDate(currentDay.getDate() + 7)
    const oh = new opening_hours(this.$el.dataset.openinghours, {}, { locale: this.locale })
    this.intervals = this.groupBy(oh.getOpenIntervals(currentDay, endWeek))
    this.todayName = new Date().toLocaleDateString(this.locale, { weekday: 'long' })
    this.today = this.intervals[now.getDay()] || []
  },
  methods: {

    groupBy(tableauObjets) {
      return tableauObjets.reduce((acc, obj) => {
        const cle = obj[0].getDay()
        if (!acc[cle]) {
          acc[cle] = []
        }
        acc[cle].push({
          day: obj[0].toLocaleDateString(this.locale, { weekday: 'long' }),
          start: obj[0].toLocaleTimeString(this.locale, {
            hour: '2-digit',
            minute: '2-digit'
          }),
          end: obj[1].toLocaleTimeString(this.locale, {
            hour: '2-digit',
            minute: '2-digit'
          })
        })
        return acc
      }, [])
    }

  },
  template: `
  <div>
    <details>
        <summary style="display: flex;align-items: center;">
        <span>{{ todayName }} </span>
        <ul style="list-style: none; margin-bottom: 0px;">
       <li v-if="!today.length"> ${_t('BAZ_OPENING_HOURS_CLOSED')}  </li>
        <li v-for="interval in today"> {{ interval.start }} - {{ interval.end }} </li>
        </ul>
        <i class="fas fa-angle-down" style="margin-left: 1em;font-size: 2em;"></i>
        </summary>
        <div style="background: white;padding: 1em;box-shadow: rgba(0, 0, 0, 0.35) 0px 5px 15px;
        z-index: 1;position: absolute;border-radius: 10px;">
          <table>
              <tr v-for="day in intervals" v-if="day !== undefined">
                  <td> {{ day[0].day }} </td>
                  <td>
                      <ul>
                          <li v-for="interval in day"> {{ interval.start }} - {{ interval.end }} </li>
                      </ul>
                  </td>
              </tr>
          </table>
        </div>
    </details>
  </div>`
})

const elements = document.getElementsByTagName('opening-hours')
const arr = Array.prototype.slice.call(elements)
arr.forEach((el) => {
  new Vue({ el })
})
