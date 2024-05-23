const rootsElementsRaw = document.getElementsByClassName('selector_is_recurrent')
const rootsElements = []
for (let index = 0; index < rootsElementsRaw.length; index++) {
  rootsElements.push(rootsElementsRaw.item(index).parentNode)
}
const isVueJS3 = (typeof Vue.createApp == 'function')

const defaultNbMax = 600
const daysToCodeAssoc = {
  mon: 1,
  tue: 2,
  wed: 3,
  thu: 4,
  fri: 5,
  sat: 6,
  sun: 7
}
const monthsToCodeAssoc = {
  jan: 0,
  feb: 1,
  mar: 2,
  apr: 3,
  may: 4,
  jun: 5,
  jul: 6,
  aug: 7,
  sep: 8,
  oct: 9,
  nov: 10,
  dec: 11
}
const wantedPositionList = {
  fisrtOfMonth: 1,
  secondOfMonth: 2,
  thirdOfMonth: 3,
  forthOfMonth: 4,
  lastOfMonth: 99
}

const appParams = {
  components: {},
  data() {
    return {
      availableExcept: [],
      canCustomizeRepetition: false,
      datePickerForLimitInternal: null,
      days: ['mon'],
      endDateLimitTime: -1,
      except: [],
      month: '',
      newExcept: '',
      nbmax: defaultNbMax,
      nth: '',
      recurrenceBaseId: '',
      repetitionInternal: '',
      showEndDateMessage: false,
      showRange: false,
      startDateInputInternal: null,
      stepInternal: 2,
      whenInMonth: ''
    }
  },
  computed: {
    availableExceptFiltered() {
      return this.availableExcept.filter((elem) => !this.except.includes(elem))
    },
    baseElement() {
      return this.element?.getElementsByClassName('selector_is_recurrent')?.[0]
    },
    dataset() {
      return this.baseElement?.dataset?.data
    },
    datePickerForLimit() {
      let datePicker = this.datePickerForLimitInternal
      if (datePicker === null) {
        datePicker = this
          .baseElement
          ?.parentNode
          ?.querySelector('.event-container-for-datepicker input[name="bf_date_fin_evenement_data[limitdate]"]')
                    ?? null
        this.datePickerForLimitInternal = datePicker
      }
      return datePicker
    },
    element() {
      return isVueJS3 ? this.$el.parentNode : this.$el
    },
    isRecurrent() {
      return this.repetition !== ''
    },
    mainParentElement() {
      return this.baseElement?.parentNode?.parentNode
    },
    repetition() {
      switch (this.repetitionInternal) {
        case 'd':
        case 'xd':
          return 'd'
        case 'w':
        case 'xw':
          return 'w'
        case 'm':
        case 'xm':
          return 'm'
        case 'y':
        case 'xy':
          return 'y'
        default:
          return ''
      }
    },
    startDateInput() {
      if (this.startDateInputInternal === null) {
        this.startDateInputInternal = document.getElementById('bf_date_debut_evenement')
      }
      return this.startDateInputInternal
    },
    step() {
      switch (this.repetitionInternal) {
        case 'd':
        case 'w':
        case 'm':
        case 'y':
          return 1
        case 'xd':
        case 'xw':
        case 'xm':
        case 'xy':
        default:
          return this.stepInternal
      }
    }
  },
  methods: {
    /**
         * calculate available except
         * @param {int} nbStep will be set to defaultNbMax if not furnished
         * @returns {String[]} available Except
         */
    calculateAvailableExcept(nbStep = defaultNbMax) {
      let date = this.getCurrentInputDate('startDateInput')
      if (date === null) {
        return []
      }

      const except = []
      for (let i = 0; i < nbStep; i++) {
        let nextStartDate = null
        switch (this.repetition) {
          case 'd':
            nextStartDate = new Date(date.getTime() + Number(this.step) * 24 * 3600 * 1000)
            break
          case 'w':
            const currentStartDayCode = date.getDay() || 7
            const daysTocode = this.days.map((d) => daysToCodeAssoc[d] ?? 1)
            let nextWantedDay = null
            const maxDaysToCode = daysTocode.reduce((acc, val) => Math.max(acc, val), 1)
            if (!daysTocode.includes(currentStartDayCode) || currentStartDayCode === maxDaysToCode) {
              nextWantedDay = daysTocode.reduce((acc, val) => Math.min(acc, val), 7)
              nextStartDate = new Date(date.getTime())
              nextStartDate.setDate(nextStartDate.getDate() + nextWantedDay + 7 * (Number(this.step) - 1) + 7 - currentStartDayCode)
            } else {
              nextWantedDay = daysTocode.filter((d) => d > currentStartDayCode).reduce((acc, val) => Math.min(acc, val), 7)
              nextStartDate = new Date(date.getTime())
              nextStartDate.setDate(nextStartDate.getDate() + nextWantedDay - currentStartDayCode)
            }
            break
          case 'm':
            const { nextStartMonth, currentStartYear } = this.calculateNextMonth(date.getMonth(), date.getFullYear(), Number(this.step))
            nextStartDate = this.findNextStartDate(date, currentStartYear, nextStartMonth, (m, y) => this.calculateNextMonth(m, y, Number(this.step)))
            break
          case 'y':
            nextStartDate = this.findNextStartDate(date, date.getFullYear() + Number(this.step), monthsToCodeAssoc?.[this.month] ?? 0, (m, y) => ({
              currentStartYear: y + Number(this.step),
              nextStartMonth: m
            }))
            break
          default:
            break
        }
        if (nextStartDate === null
                    || nextStartDate.toString() === 'Invalid Date') {
          return except
        }
        date = nextStartDate
        if (this.endDateLimitTime < 0
                     || nextStartDate.getTime() <= this.endDateLimitTime
        ) {
          except.push(this.convertDateToString(date))
        }
      }
      return except
    },
    calculateNextMonth(nextStartMonth, currentStartYear, step) {
      const newMonth = nextStartMonth + step
      return newMonth > 11
        ? {
          nextStartMonth: newMonth - 12,
          currentStartYear: currentStartYear + 1
        }
        : {
          nextStartMonth: newMonth,
          currentStartYear
        }
    },
    /**
         * convert a Date object to a string YYYY-MM-DD
         * @param {Date} date
         * @returns {string}
         */
    convertDateToString(date) {
      // work in UTC because ISO string is splitted
      return (new Date(Date.UTC(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
        date.getHours(),
        date.getMinutes(),
        date.getSeconds()
      ))).toISOString().slice(0, 10)
    },
    getNbDaysInMonth(year, month) {
      const firstDayOfMonth = new Date(year, month)
      firstDayOfMonth.setDate(1)
      const lastDayOfMonth = new Date(year + (month === 12 ? 1 : 0), (month % 12) + 1)
      lastDayOfMonth.setDate(0)
      return Math.round((lastDayOfMonth - firstDayOfMonth) / 1000 / 3600 / 24) + 1
    },
    /**
         * @return {int} nbMax estimated from limitDate
         *   if there is no limit date return defaultNbMax
         */
    estimateNBMaxFromLimitDate() {
      if (this.endDateLimitTime <= 0) {
        return defaultNbMax
      }
      /**
             * @var {Date} startDate
             */
      const startDate = this.getCurrentInputDate('startDateInput')
      if (startDate === null) {
        return defaultNbMax
      }
      /**
             * @var {int} duration between start date end limit date
             */
      const duration = this.endDateLimitTime - startDate.getTime()
      if (duration < 0) {
        return 0
      }
      /**
             * @var {int} step step in days (very approximatively)
             */
      let step = 1
      switch (this.repetition) {
        case 'y':
          step = 365
          break
        case 'w':
          step = Math.floor(7 / this.step)
          break
        case 'm':
          step = 28
          break
        case 'd':
        default:
          step = 1
          break
      }
      return Math.ceil(duration / 1000 / 3600 / 24 / step) + 20 // margin of 20 to be really sure to catch all repetitions
    },
    findNextStartDate(date, startYear, startMonth, callback) {
      if (this.whenInMonth === 'nthOfMonth') {
        let limit = 60
        let currentStartYear = startYear
        let nextStartMonth = startMonth
        const nth = this.nth || 1
        while (limit > 0 && nth > this.getNbDaysInMonth(currentStartYear, nextStartMonth)) {
          const data = callback(nextStartMonth, currentStartYear)
          nextStartMonth = data?.nextStartMonth ?? nextStartMonth
          currentStartYear = data?.currentStartYear ?? currentStartYear
          limit -= 1
        }
        const newStartDate = new Date(date.getTime())
        newStartDate.setFullYear(currentStartYear, nextStartMonth, nth)
        return newStartDate
      }
      const wantedPosition = wantedPositionList?.[this.whenInMonth] ?? 1
      const nbDaysInMonth = this.getNbDaysInMonth(startYear, startMonth)
      const day = this.days.reduce((acc, d) => Math.min(acc, daysToCodeAssoc?.[d] ?? 7), 7)
      let counter = 0
      const testedDate = new Date(date.getTime())
      testedDate.setFullYear(startYear, startMonth)
      let newStartDate = new Date(date.getTime())
      for (let curDay = 1; curDay <= nbDaysInMonth; curDay++) {
        if (counter < wantedPosition) {
          testedDate.setDate(curDay)
          if ((testedDate.getDay() || 7) === day) {
            counter += 1
            newStartDate = new Date(testedDate.getTime())
          }
        }
      }
      return newStartDate
    },
    /**
         * get current date from input
         * @param {String} keyname
         * @returns {Date} date , null if not available
         */
    getCurrentInputDate(keyname) {
      /**
             * @var {String} dateStr current value of input
             */
      const dateStr = this?.[keyname]?.value ?? ''
      if (dateStr === '') {
        return null
      }
      /**
             * @var {Date} date current Date object from value
             */
      const date = new Date(dateStr)
      if (date.toString() === 'Invalid Date') {
        return null
      }
      return date
    },
    getCurrentStartDay() {
      const date = this.getCurrentInputDate('startDateInput')
      if (date === null) {
        return ''
      }
      const day = date.getDay()
      switch (day) {
        case 0:
          return 'sun'
        case 1:
          return 'mon'
        case 2:
          return 'tue'
        case 3:
          return 'wed'
        case 4:
          return 'thu'
        case 5:
          return 'fri'
        case 6:
          return 'sat'
        default:
          return ''
      }
    },
    registerChangeOnStartDateInput() {
      this.startDateInput.addEventListener('blur', () => {
        setTimeout(() => { this.setCurrentDayIfWeek() }, 200)
        this.updateAvailableExceptUpdatingNbMax()
      })
    },
    setCurrentDayIfWeek() {
      if (this.repetitionInternal?.match(/w$/)) {
        const day = this.getCurrentStartDay()
        if (day !== '' && !this.days.includes(day)) {
          if (day !== 'mon' && this.days.length === 1 && this.days.includes('mon')) {
            this.days = [day]
          } else {
            this.days.push(day)
          }
        }
      }
    },
    toggleDay(key) {
      if (this.repetition === 'w') {
        if (this.days.includes(key) && key !== this.getCurrentStartDay()) {
          this.days = this.days.filter((elem) => elem != key)
        } else {
          this.days.push(key)
        }
      } else {
        this.days = [key]
      }
    },
    updateAvailableExceptUpdatingNbMax() {
      /**
             * @var {int} nbmax calculated nbmax after update of limit date
             */
      const nbmax = this.estimateNBMaxFromLimitDate()
      /**
             * @var {String[]} availableExcept to update the real value of nbMax
             */
      const availableExcept = this.calculateAvailableExcept(Math.min(nbmax, defaultNbMax))
      this.availableExcept = availableExcept
      this.nbmax = Math.max(2, availableExcept.length)
      if (nbmax > defaultNbMax) {
        this.updateEndDateLimitInputIfNotEmpty(availableExcept)
      }
    },
    /**
         * update the limit date if maximum number is reached
         * @param {array} availableExcept
         */
    updateEndDateLimitInputIfNotEmpty(availableExcept) {
      /**
             * @var {Date|null} endDateLimit
             */
      const endDateLimit = this.getCurrentInputDate('datePickerForLimit')
      if (endDateLimit !== null && availableExcept.length > 0) {
        /**
                 * @var {string} endDateLimitStr
                 */
        const endDateLimitStr = this.convertDateToString(endDateLimit)
        /**
                 * @var {string} lastAvailableExcept
                 */
        const lastAvailableExcept = availableExcept[availableExcept.length - 1]
        if (lastAvailableExcept < endDateLimitStr) {
          $(this.datePickerForLimit).datepicker('update', lastAvailableExcept)
          this.showEndDateMessage = true
        }
      }
    },
    updateEndDateLimitTime(newVal = null) {
      const endDateLimit = (newVal === null)
        ? this.getCurrentInputDate('datePickerForLimit')
        : new Date(newVal)
      this.endDateLimitTime = (endDateLimit === null
                || endDateLimit.toString() === 'Invalid Date')
        ? -1
        : endDateLimit.getTime()
      this.updateAvailableExceptUpdatingNbMax()
    }
  },
  mounted() {
    const data = JSON.parse(this.dataset)
    const limitdate = data?.limitdate ?? ''
    $(this.datePickerForLimit).on('changeDate', (event) => {
      this.updateEndDateLimitTime(event.date)
    })
    $(this.datePickerForLimit).on('input', (event) => {
      if (event.date === undefined) {
        this.updateEndDateLimitTime('')
      }
    })
    if (limitdate && 'value' in this.datePickerForLimit) {
      this.datePickerForLimit.value = limitdate
      this.updateEndDateLimitTime()
    } else {
      this.updateAvailableExceptUpdatingNbMax()
    }
    if (data?.isRecurrent !== '1') {
      this.recurrenceBaseId = (
        typeof data === 'string'
                && data.match(/^\{"recurrentParentId":"([^"]+)"}$/)
      )
        ? data.replace(/^\{"recurrentParentId":"([^"]+)"}$/, '$1')
        : ''
    }
    const step = data?.step ?? 2
    this.stepInternal = step
    const repetition = data?.repetition ?? ''
    this.repetitionInternal = (data?.isRecurrent === '1' && ['d', 'y', 'w', 'm'].includes(repetition))
      ? (
        Number(step) === 1
          ? repetition
          : `x${repetition}`
      )
      : ''
    if (repetition.length > 0 && Number(step) > 1) {
      this.canCustomizeRepetition = true
    }
    this.whenInMonth = data?.whenInMonth ?? ''
    this.month = data?.month ?? ''
    this.nth = data?.nth ?? ''
    const nbmax = Number(data?.nbmax ?? defaultNbMax)
    this.nbmax = (nbmax && nbmax > 0) ? nbmax : defaultNbMax
    this.days = Array.isArray(data?.days)
      ? data.days
      : ['mon']
    this.month = data?.month ?? ''
    this.except = Array.isArray(data?.except) ? data?.except : []
    this.mainParentElement?.classList?.add('ready')
  },
  watch: {
    days() {
      this.updateAvailableExceptUpdatingNbMax()
    },
    month() {
      this.updateAvailableExceptUpdatingNbMax()
    },
    newExcept(newValue) {
      if (newValue.length > 0) {
        if (!this.except.includes(newValue)
                    && this.availableExceptFiltered.includes(newValue)) {
          this.except.push(newValue)
        }
        this.newExcept = ''
      }
    },
    nth() {
      this.updateAvailableExceptUpdatingNbMax()
    },
    repetition(repetition) {
      if (repetition !== 'w' && this.days?.length > 1) {
        this.days = [this.days[0]]
      }
      this.updateAvailableExceptUpdatingNbMax()
    },
    repetitionInternal(repetition, previousValue) {
      if (repetition === 'activateCustom') {
        this.canCustomizeRepetition = true
        this.repetitionInternal = previousValue
      } else if (repetition === 'removeCustom') {
        this.canCustomizeRepetition = false
        this.repetitionInternal = previousValue.replace('x', '')
      }
      this.setCurrentDayIfWeek()
    },
    showEndDateMessage(newValue) {
      if (newValue) {
        // set timout of 3 secondes
        setTimeout(() => {
          this.showEndDateMessage = false
        }, 3000)
      }
    },
    step() {
      this.updateAvailableExceptUpdatingNbMax()
    },
    whenInMonth() {
      this.updateAvailableExceptUpdatingNbMax()
    }
  }
}
if (isVueJS3) {
  const app = Vue.createApp(appParams)
  app.config.globalProperties.wiki = wiki
  app.config.globalProperties._t = _t
  rootsElements.forEach((elem) => {
    app.mount(elem)
  })
} else {
  Vue.prototype.wiki = wiki
  Vue.prototype._t = _t
  rootsElements.forEach((elem) => {
    new Vue({
      ...{ el: elem },
      ...appParams
    })
  })
}
