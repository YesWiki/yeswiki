const rootsElementsRaw = document.getElementsByClassName('selector_is_recurrent')
const rootsElements = []
for (let index = 0; index < rootsElementsRaw.length; index++) {
  rootsElements.push(rootsElementsRaw.item(index).parentNode)
}
const isVueJS3 = (typeof Vue.createApp == 'function')

const defaultNbMax = 600

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
      const date = this.getCurrentInputDate('startDateInput')
      if (date === null) {
        return []
      }
      const anchor = this.convertDateToString(date)
      const occurrences = window._bazarRecurrenceCalculator.generateOccurrences({
        startDate: anchor,
        endDate: anchor,
        repetition: this.repetition,
        step: this.step,
        days: this.days,
        month: this.month,
        whenInMonth: this.whenInMonth,
        nth: this.nth,
        nbmax: nbStep,
        limitdate: this.endDateLimitTime >= 0 ? this.convertDateToString(new Date(this.endDateLimitTime)) : null,
        except: []
      })
      // drop the anchor occurrence (#0): this picker only offers *future* candidate dates
      return occurrences.slice(1).map((occurrence) => occurrence.start)
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
          // native <input type="date"> now (was bootstrap-datepicker 'update')
          this.datePickerForLimit.value = lastAvailableExcept
          this.updateEndDateLimitTime(lastAvailableExcept)
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
    this.datePickerForLimit.addEventListener('change', () => {
      this.updateEndDateLimitTime(this.datePickerForLimit.value || '')
    })
    this.datePickerForLimit.addEventListener('input', () => {
      if (!this.datePickerForLimit.value) {
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
      this.recurrenceBaseId = window._bazarRecurrenceCalculator.isLegacyRecurrenceChild(data)
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
