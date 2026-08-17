import ButtonIcs from './BazarCalendar_ButtonICS.js'

const BazarCalendar = {
  props: ['params'],
  components: { ButtonIcs },
  data() {
    return {
      selectedEntry: null,
      calendar: null,
      mounted: false,
    }
  },
  methods: {
    addEntry(entry) {
      const hasEvents = this.calendar
        .getEvents()
        .some((event) => event.groupId === entry.tag)
      if (!hasEvents) {
        this.prepareEvents(entry).forEach((event) =>
          this.calendar.addEvent(event),
        )
      }
    },
    addEntries(entries) {
      const newEvents = entries.flatMap((entry) => this.prepareEvents(entry))
      if (Array.isArray(newEvents) && newEvents.length > 0) {
        this.calendar.addEventSource({ events: newEvents })
      }
    },
    arraysEqual(a, b) {
      if (a === b) return true
      if (a == null || b == null) return false
      if (a.length !== b.length) return false

      a.sort()
      b.sort()
      for (let i = 0; i < a.length; ++i) {
        if (a[i] !== b[i]) return false
      }
      return true
    },
    getDateInServerTimeZone(date) {
      const newDate = new Date(this.getDateStringInServerTimeZone(date))
      if (newDate) {
        return newDate.toISOString()
      }
      return date
    },
    getDateStringInServerTimeZone(date) {
      const exportableDate = new Date(date)
      if (exportableDate) {
        return exportableDate
          .toLocaleString('en-GB', { timeZone: wiki.timezone })
          .replace(
            /^([0-9]{2})\/([0-9]{2})\/([0-9]{4}), ([0-9]{2}):([0-9]{2}):([0-9]{2})$/,
            '$3-$2-$1T$4:$5:$6',
          )
      }
      return date
    },
    displaySideBar(info) {
      info.jsEvent.preventDefault()
      const { entries } = this
      this.selectedEntry = entries.filter(
        (entry) => entry.tag === info.event.groupId,
      )[0]
    },
    getEventById(id) {
      return this.calendar ? this.calendar.getEventById(id) : null
    },
    isAllDayDate(date) {
      return date.length <= 10
    },
    isModalDisplay() {
      return (
        this.params.entrydisplay == null ||
        this.params.entrydisplay.length === 0 ||
        this.params.entrydisplay === 'modal' ||
        ['modal', 'newtab', 'direct', 'sidebar'].indexOf(
          this.params.entrydisplay,
        ) === -1
      )
    },
    isNewTabDisplay() {
      return (
        this.params.entrydisplay != null &&
        this.params.entrydisplay === 'newtab'
      )
    },
    isDirectLinkDisplay() {
      return (
        this.params.entrydisplay != null &&
        this.params.entrydisplay === 'direct'
      )
    },
    formatEndDate(entry) {
      const startDate = this.retrieveTimeZone(entry.bf_date_debut_evenement)
      let endDate = null
      if (entry.bf_date_fin_evenement != null) {
        endDate = this.retrieveTimeZone(entry.bf_date_fin_evenement)
        try {
          endDate = new Date(endDate)
        } catch {
          endDate = null
        }
      }
      if (
        entry.bf_date_fin_evenement == null ||
        endDate == null ||
        endDate === 'Invalid Date'
      ) {
        if (startDate.length <= 10) {
          return startDate
        }
        endDate = new Date(startDate)
        endDate.setDate(endDate.getDate() + 1)
        endDate.setHours(0)
        endDate.setMinutes(0)
        endDate.setSeconds(0)
        endDate.setMilliseconds(0)
        return endDate.toISOString()
      }
      let endDateRaw = this.retrieveTimeZone(entry.bf_date_fin_evenement)
      if (endDateRaw.length <= 10) {
        endDate.setDate(endDate.getDate() + 1)
        endDate.setHours(0)
        endDateRaw = endDate.toISOString()
      }
      return endDateRaw
    },
    manageClick(info) {
      if (this.isNewTabDisplay()) {
        info.jsEvent.preventDefault()
        window.open(info.event.url)
      } else if (this.isDirectLinkDisplay()) {
        info.jsEvent.preventDefault()
        window.location =
          info.event.url + (this.$root.isInIframe() ? '/iframe' : '')
      } else if (
        ['listWeek', 'listMonth', 'listYear'].indexOf(info.view.type) > -1
      ) {
        info.jsEvent.preventDefault()
      }
    },
    mountCalendar() {
      if (!this.mounted) {
        if (this.params.minicalendar) {
          this.$el.classList.add('minical')
        }
        const calendarEl = document.createElement('div')
        calendarEl.addEventListener('dblclick', (e) => {
          e.preventDefault()
          e.stopPropagation()
        })
        this.$el.prepend(calendarEl)
        this.calendar = new FullCalendar.Calendar(
          calendarEl,
          this.calendarOptions,
        )
        this.calendar.setOption('eventDidMount', this.updateEventData)
        if (this.params.entrydisplay === 'sidebar') {
          this.calendar.setOption('eventClick', this.displaySideBar)
        }
        this.calendar.render()
        this.mounted = true
      }
    },
    buildEventObject(entry, id, start, end) {
      const backgroundColor =
        entry.color == null || entry.color.length === 0 ? '' : entry.color
      return {
        id,
        groupId: entry.tag,
        title: entry.title || entry.bf_titre || '',
        start,
        end,
        url: entry.url + (this.isModalDisplay() ? '/iframe' : ''),
        allDay: this.isAllDayDate(start),
        className: `bazar-entry${this.isModalDisplay() ? ' modalbox' : ''}`,
        backgroundColor,
        borderColor: backgroundColor,
        extendedProps: {
          icon:
            entry.icon == null || entry.icon.length === 0
              ? ''
              : `<i class="${entry.icon}">&nbsp;</i>`,
          htmlattributes: `${
            (entry.html_data != null ? entry.html_data : '') +
            (this.isModalDisplay() ? ' data-iframe="1"' : '')
          } data-size="modal-lg"`,
        },
      }
    },
    prepareEvents(entry) {
      if (typeof entry.bf_date_debut_evenement === 'undefined') {
        return []
      }
      const start = this.retrieveTimeZone(entry.bf_date_debut_evenement)
      const end = this.formatEndDate(entry)
      const recurrenceData = entry.bf_date_fin_evenement_data
      const isRecurrent =
        recurrenceData != null &&
        typeof recurrenceData === 'object' &&
        recurrenceData.isRecurrent === '1'
      if (!isRecurrent) {
        return [this.buildEventObject(entry, entry.tag, start, end)]
      }
      const occurrences = window._bazarRecurrenceCalculator.generateOccurrences(
        {
          ...recurrenceData,
          startDate: start,
          endDate: end,
        },
      )
      return occurrences.map((occurrence, index) =>
        this.buildEventObject(
          entry,
          `${entry.tag}::${index}`,
          occurrence.start,
          occurrence.end,
        ),
      )
    },
    removeEntry(entry) {
      this.calendar
        .getEvents()
        .filter((event) => event.groupId === entry.tag)
        .forEach((event) => event.remove())
    },
    retrieveTimeZone(dateAsString) {
      let exportableDate = dateAsString
      if (typeof exportableDate === 'string' && exportableDate?.length > 10) {
        if (exportableDate.match(/\+00:00$/)) {
          const dateObj = new Date(exportableDate)
          if (dateObj) {
            const browserTimezoneOffset = dateObj.getTimezoneOffset()
            const dateNoTimeZone = exportableDate.replace(/\+00:00$/, '')
            const dateObjNoTimezone = new Date(dateNoTimeZone)
            const dateStringInServerTimeZone =
              this.getDateStringInServerTimeZone(dateNoTimeZone)
            const diffBetweenServerAndBrowserTimeZone_ms =
              new Date(dateStringInServerTimeZone).getTime() -
              dateObjNoTimezone.getTime()
            dateObj.setTime(
              dateObj.getTime() +
                browserTimezoneOffset * 60000 -
                diffBetweenServerAndBrowserTimeZone_ms,
            )
            exportableDate = dateObj.toISOString()
          }
        }
        exportableDate = this.getDateInServerTimeZone(exportableDate)
      }
      return exportableDate
    },
    updateEventData(arg) {
      const { event } = arg
      const htmlAttributes = event.extendedProps.htmlattributes
      const element = arg.el
      const holder = document.createElement('div')
      holder.innerHTML = `<div ${htmlAttributes}></div>`
      const attributesSource = holder.firstElementChild
      if (attributesSource) {
        Array.from(attributesSource.attributes).forEach((attribute) => {
          if (attribute.name.startsWith('data-')) {
            element.setAttribute(attribute.name, attribute.value)
          }
        })
      }
      if (!element.classList.contains('iconDefined')) {
        if (event.extendedProps.icon.length > 0) {
          const title = element.classList.contains('fc-list-event')
            ? element.querySelector('.fc-list-event-title a')
            : element.querySelector('.fc-event-title')
          if (title) {
            title.insertAdjacentHTML('afterbegin', event.extendedProps.icon)
          }
        }
        element.classList.add('iconDefined')
      }
      if (
        element.classList.contains('fc-list-event') &&
        this.isModalDisplay()
      ) {
        element.querySelectorAll('.fc-list-event-title a').forEach((link) => {
          link.setAttribute('data-size', 'modal-lg')
          link.setAttribute('data-iframe', '1')
          link.setAttribute('title', event.title)
        })
      } else if (
        element.classList.contains('fc-list-event') &&
        this.isNewTabDisplay()
      ) {
        element.querySelectorAll('.fc-list-event-title a').forEach((link) => {
          link.classList.add('new-window')
          link.setAttribute('title', event.title)
        })
      }
      if (!element.classList.contains('toolTipDefined')) {
        element.classList.add('toolTipDefined')
        element.setAttribute('title', event.title)
      }
    },
  },
  computed: {
    calendarOptions() {
      let extendedList = ''
      switch (this.params.showlist) {
        case 'week':
          extendedList = ',listWeek'
          break
        case 'month':
          extendedList = ',listMonth'
          break
        case 'year':
        default:
          extendedList = ',listYear'
          break
      }
      let initialView = 'dayGridMonth'
      switch (this.params.initialview) {
        case 'timeGridWeek':
          initialView = 'timeGridWeek'
          break
        case 'timeGridDay':
          initialView = 'timeGridDay'
          break
        case 'list':
          if (extendedList.length > 0) {
            initialView = extendedList.slice(1)
          }
          break
        case 'dayGridMonth':
        default:
          break
      }
      return {
        dayMaxEventRows: 3,
        editable: false,
        eventDisplay: 'block',
        eventClick: this.manageClick,
        eventMaxStack: 3,
        firstDay: 1,
        headerToolbar: {
          left: 'prev today',
          center: 'title',
          right: `dayGridMonth,timeGridWeek,timeGridDay${extendedList} next`,
        },
        initialView,
        locale: wiki.locale,
        navLinks: true,
        views: {
          dayGridMonth: {
            eventTimeFormat: {
              hour: '2-digit',
              minute: '2-digit',
              meridiem: false,
            },
          },
        },
        weekNumbers: true,
      }
    },
    entries() {
      return this.$root.entriesToDisplay.filter(
        (entry) =>
          entry.bf_date_debut_evenement &&
          !window._bazarRecurrenceCalculator.isLegacyRecurrenceChild(
            entry.bf_date_fin_evenement_data,
          ),
      )
    },
    events() {
      return this.calendar ? this.calendar.getEvents() : []
    },
  },
  mounted() {
    this.mountCalendar()
  },
  watch: {
    selectedEntry() {
      if (this.selectedEntry) {
        if (this.params.entrydisplay === 'sidebar')
          this.$root.getEntryRender(this.selectedEntry)
      }
    },
    params() {
      this.mountCalendar()
    },
    entries(newVal, oldVal) {
      const newIds = newVal.map((e) => e.tag)
      const oldIds = oldVal.map((e) => e.tag)
      if (!this.arraysEqual(newIds, oldIds)) {
        const { entries } = this
        this.$nextTick(function () {
          this.calendar.getEventSources().forEach((source) => source.remove())
          this.addEntries(entries)
        })
      }
    },
  },
  template: `
    <div class="entry-list-dynamic-template-container">
      <!-- SideNav to display entry -->
      <div v-if="selectedEntry && this.params.entrydisplay == 'sidebar'" class="entry-container">
        <div class="btn-close" @click="selectedEntry = null"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#x"/></svg></div>
        <div v-html="selectedEntry.html_render"></div>
      </div>
      <ButtonIcs v-if="this.params.showicalbutton" :bazarcalendar="this"/> 
    </div>
  `,
}

if (!window._bazarDynamicComponents) window._bazarDynamicComponents = {}
window._bazarDynamicComponents.BazarCalendar = BazarCalendar
