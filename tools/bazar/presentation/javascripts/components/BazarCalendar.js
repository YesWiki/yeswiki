import ButtonIcs from './BazarCalendar_ButtonICS.js'

Vue.component('BazarCalendar', {
  props: ['params'],
  components: { ButtonIcs },
  data() {
    return {
      selectedEntry: null,
      calendar: null,
      mounted: false
    }
  },
  methods: {
    addEntry(entry) {
      const newEvent = this.prepareEvent(entry)
      if (Object.keys(newEvent).length > 0) {
        this.calendar.addEvent(newEvent)
      }
    },
    addEntries(entries) {
      const newEvents = entries.map((entry) => this.prepareEvent(entry))
      if (Array.isArray(newEvents) && newEvents.length > 0) {
        this.calendar.addEventSource({ events: newEvents })
      }
    },
    arraysEqual(a, b) {
      if (a === b) return true
      if (a == null || b == null) return false
      if (a.length !== b.length) return false

      a.sort(); b.sort()
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
          .replace(/^([0-9]{2})\/([0-9]{2})\/([0-9]{4}), ([0-9]{2}):([0-9]{2}):([0-9]{2})$/, '$3-$2-$1T$4:$5:$6')
      }
      return date
    },
    displaySideBar(info) {
      info.jsEvent.preventDefault()
      const { entries } = this
      this.selectedEntry = entries.filter((entry) => (entry.id_fiche == info.event.id))[0]
    },
    getEventById(id) {
      return this.calendar ? this.calendar.getEventById(id) : null
    },
    isAllDayDate(date) {
      return (date.length <= 10)
    },
    isModalDisplay() {
      return (this.params.entrydisplay == undefined || this.params.entrydisplay.length == 0 || this.params.entrydisplay == 'modal' || ['modal', 'newtab', 'direct', 'sidebar'].indexOf(this.params.entrydisplay) == -1)
    },
    isNewTabDisplay() {
      return (this.params.entrydisplay != undefined && this.params.entrydisplay == 'newtab')
    },
    isDirectLinkDisplay() {
      return (this.params.entrydisplay != undefined && this.params.entrydisplay == 'direct')
    },
    formatEndDate(entry) {
      // Fixs bug, when no time is specified, is the event is on multiple day, calendJs show it like
      // it end one day earlier
      const startDate = this.retrieveTimeZone(entry.bf_date_debut_evenement)
      let endDate = null
      if (entry.bf_date_fin_evenement != undefined) {
        endDate = this.retrieveTimeZone(entry.bf_date_fin_evenement)
        try {
          endDate = new Date(endDate)
        } catch (error) {
          endDate = null
        }
      }
      if (entry.bf_date_fin_evenement == undefined || endDate == null || endDate == 'Invalid Date') {
        if (startDate.length <= 10) {
          return startDate
        }
        endDate = new Date(startDate)
        endDate.setDate(endDate.getDate() + 1) // +1 day
        endDate.setHours(0)
        endDate.setMinutes(0)
        endDate.setSeconds(0)
        endDate.setMilliseconds(0)
        return endDate.toISOString()
      }
      let endDateRaw = this.retrieveTimeZone(entry.bf_date_fin_evenement)
      if (endDateRaw.length <= 10) {
        endDate.setDate(endDate.getDate() + 1) // +1 day
        endDate.setHours(0)
        endDateRaw = endDate.toISOString()
      }
      return endDateRaw
    },
    manageClick(info) {
      if (this.isNewTabDisplay()) {
        info.jsEvent.preventDefault() // don't let the browser navigate
        window.open(info.event.url)
      } else if (this.isDirectLinkDisplay()) {
        info.jsEvent.preventDefault()
        window.location = info.event.url + (this.$root.isInIframe() ? '/iframe' : '')
      } else if (['listWeek', 'listMonth', 'listYear'].indexOf(info.view.type) > -1) {
        info.jsEvent.preventDefault() // don't let the browser navigate
      }
    },
    mountCalendar() {
      if (!this.mounted) {
        if (this.params.minical) {
          $(this.$el).addClass('minical')
        }
        const calendarEl = $('<div>').on(
          'dblclick',
          (e) => false
        )
        $(this.$el).prepend(calendarEl)
        this.calendar = new FullCalendar.Calendar(calendarEl.get(0), this.calendarOptions)
        this.calendar.setOption('eventDidMount', this.updateEventData)
        if (this.params.entrydisplay == 'sidebar') {
          this.calendar.setOption('eventClick', this.displaySideBar)
        }
        this.calendar.render()
        this.mounted = true
      }
    },
    prepareEvent(entry) {
      const entryId = entry.id_fiche
      const existingEvent = this.getEventById(entryId)
      if (!existingEvent && typeof entry.bf_date_debut_evenement != 'undefined') {
        const backgroundColor = (entry.color == undefined || entry.color.length == 0) ? '' : entry.color
        const newEvent = {
          id: entryId,
          title: entry.bf_titre,
          start: this.retrieveTimeZone(entry.bf_date_debut_evenement),
          end: this.formatEndDate(entry),
          url: entry.url + (this.isModalDisplay() ? '/iframe' : ''),
          allDay: this.isAllDayDate(entry.bf_date_debut_evenement),
          className: `bazar-entry${this.isModalDisplay() ? ' modalbox' : ''}`,
          backgroundColor,
          borderColor: backgroundColor,
          extendedProps: {
            icon: (entry.icon == undefined || entry.icon.length == 0) ? '' : `<i class="${entry.icon}">&nbsp;</i>`,
            htmlattributes: `${((entry.html_data != undefined) ? entry.html_data : '')
              + (this.isModalDisplay() ? ' data-iframe="1"' : '')
            } data-size="modal-lg"`
          }
        }
        return newEvent
      }
      return {}
    },
    removeEntry(entry) {
      const entryId = entry.id_fiche
      const existingEvent = this.getEventById(entryId)
      if (existingEvent) {
        existingEvent.remove()
      }
    },
    retrieveTimeZone(dateAsString) {
      let exportableDate = dateAsString
      if (typeof exportableDate === 'string'
        && exportableDate?.length > 10) {
        if (exportableDate.match(/\+00:00$/)) {
          // could be an error
          const dateObj = new Date(exportableDate)
          if (dateObj) {
            const browserTimezoneOffset = dateObj.getTimezoneOffset()
            const dateNoTimeZone = exportableDate.replace(/\+00:00$/, '')
            const dateObjNoTimezone = new Date(dateNoTimeZone)
            const dateStringInServerTimeZone = this.getDateStringInServerTimeZone(dateNoTimeZone)
            const diffBetweenServerAndBrowserTimeZone_ms = (new Date(dateStringInServerTimeZone)).getTime()
               - dateObjNoTimezone.getTime()
            dateObj.setTime(dateObj.getTime() + browserTimezoneOffset * 60000 - diffBetweenServerAndBrowserTimeZone_ms)
            exportableDate = dateObj.toISOString()
          }
        }
        // fake date in browser timezone to be sure to be sync with server's timezone
        exportableDate = this.getDateInServerTimeZone(exportableDate)
      }
      return exportableDate
    },
    updateEventData(arg) {
      const { event } = arg
      const htmlAttributes = event.extendedProps.htmlattributes
      const element = $(arg.el)
      $.each($(`<div ${htmlAttributes}>`).data(), (index, value) => {
        $(element).attr(`data-${index}`, value)
      })
      if (!$(element).hasClass('iconDefined')) {
        if (event.extendedProps.icon.length > 0) {
          if (!$(element).hasClass('fc-list-event')) {
            $(element).find('.fc-event-title').prepend($(event.extendedProps.icon))
          } else {
            $(element).find('.fc-list-event-title a').prepend($(event.extendedProps.icon))
          }
        }
        $(element).addClass('iconDefined')
      }
      if ($(element).hasClass('fc-list-event') && this.isModalDisplay()) {
        $(element).find('.fc-list-event-title a').each(function() {
          $(this).attr('data-size', 'modal-lg')
          $(this).attr('data-iframe', '1')
          $(this).attr('title', event.title)
        })
      } else if ($(element).hasClass('fc-list-event') && this.isNewTabDisplay()) {
        $(element).find('.fc-list-event-title a').each(function() {
          $(this).addClass('new-window')
          $(this).attr('title', event.title)
        })
      }
      if (!$(element).hasClass('toolTipDefined')) {
        $(element).tooltip({ title: event.title, html: true })
        $(element).addClass('toolTipDefined')
        $(element).attr('title', event.title)
      }
    }
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
            initialView = extendedList.slice(1) // remove first char
            break
          }
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
          right: `dayGridMonth,timeGridWeek,timeGridDay${extendedList} next`
        },
        initialView,
        locale: wiki.locale,
        navLinks: true,
        views: {
          dayGridMonth: { // name of view
            eventTimeFormat: {
              hour: '2-digit',
              minute: '2-digit',
              meridiem: false
            }
            // other view-specific options here
          }
        },
        weekNumbers: true
      }
    },
    entries() {
      return this.$root.entriesToDisplay.filter((entry) => entry.bf_date_debut_evenement)
    },
    events() {
      return this.calendar ? this.calendar.getEvents() : []
    }
  },
  watch: {
    selectedEntry(newVal, oldVal) {
      if (this.selectedEntry) {
        if (this.params.entrydisplay == 'sidebar') this.$root.getEntryRender(this.selectedEntry)
      }
    },
    params() {
      this.mountCalendar()
    },
    entries(newVal, oldVal) {
      const newIds = newVal.map((e) => e.id_fiche)
      const oldIds = oldVal.map((e) => e.id_fiche)
      if (!this.arraysEqual(newIds, oldIds)) {
        const { entries } = this
        this.$nextTick(function() {
          this.calendar.getEventSources().forEach((source) => source.remove())
          this.addEntries(entries)
        })
      }
    }
  },
  template: `
    <div class="bazar-list-dynamic-template-container">
      <!-- SideNav to display entry -->
      <div v-if="selectedEntry && this.params.entrydisplay == 'sidebar'" class="entry-container">
        <div class="btn-close" @click="selectedEntry = null"><i class="fa fa-times"></i></div>
        <div v-html="selectedEntry.html_render"></div>
      </div>
      <ButtonIcs v-if="this.params.showicalbutton" :bazarcalendar="this"/> 
    </div>
  `
})
