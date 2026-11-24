/* The server-rendered calendar of a list: FullCalendar draws the events the server wrote beside it, recurring ones unfolded here. */
import { inkOn } from './colour-contrast.js'

const LIST_VIEWS = ['listWeek', 'listMonth', 'listYear']

const isAllDay = (date) => date.length <= 10

function inServerTimeZone(date) {
  const exportable = new Date(date)
  if (Number.isNaN(exportable.getTime())) return date
  return exportable
    .toLocaleString('en-GB', { timeZone: wiki.timezone })
    .replace(
      /^([0-9]{2})\/([0-9]{2})\/([0-9]{4}), ([0-9]{2}):([0-9]{2}):([0-9]{2})$/,
      '$3-$2-$1T$4:$5:$6',
    )
}

/** A stored date read in the wiki's time zone, the way the entry form wrote it. */
function readDate(stored) {
  if (typeof stored !== 'string' || stored.length <= 10) return stored
  let date = stored
  if (/\+00:00$/.test(date)) {
    const withZone = new Date(date)
    const withoutZone = date.replace(/\+00:00$/, '')
    const shift =
      new Date(inServerTimeZone(withoutZone)).getTime() -
      new Date(withoutZone).getTime()
    withZone.setTime(
      withZone.getTime() + withZone.getTimezoneOffset() * 60000 - shift,
    )
    date = withZone.toISOString()
  }
  const local = new Date(inServerTimeZone(date))
  return Number.isNaN(local.getTime()) ? date : local.toISOString()
}

/** Where an event ends for FullCalendar, whose end is exclusive: the day after a last full day. */
function endOf(event, start) {
  const stored = event.end ? readDate(event.end) : null
  const end = stored ? new Date(stored) : null
  if (!end || Number.isNaN(end.getTime())) {
    if (isAllDay(start)) return start
    const next = new Date(start)
    next.setDate(next.getDate() + 1)
    next.setHours(0, 0, 0, 0)
    return next.toISOString()
  }
  if (stored.length <= 10) {
    end.setDate(end.getDate() + 1)
    end.setHours(0)
    return end.toISOString()
  }
  return stored
}

function eventsOf(event, setup) {
  const start = readDate(event.start)
  const end = endOf(event, start)
  const modal = setup.display === 'modal'
  const build = (id, from, to) => ({
    id,
    groupId: event.tag,
    title: event.title,
    start: from,
    end: to,
    url: event.url + (modal || setup.inIframe ? '/iframe' : ''),
    allDay: isAllDay(from),
    className: `bazar-entry${modal ? ' modalbox' : ''}`,
    ...(event.color
      ? { color: event.color, contrastColor: inkOn(event.color) || undefined }
      : {}),
    extendedProps: { icon: event.icon || '', modal, tag: event.tag },
  })
  if (!event.recurrence) return [build(event.tag, start, end)]
  return window._bazarRecurrenceCalculator
    .generateOccurrences({
      ...event.recurrence,
      startDate: start,
      endDate: end,
    })
    .map((occurrence, index) =>
      build(`${event.tag}::${index}`, occurrence.start, occurrence.end),
    )
}

async function openInSide(container, tag) {
  const url = container.dataset.fragmentUrl.replace(
    '__TAG__',
    encodeURIComponent(tag),
  )
  const response = await fetch(url, { headers: { Accept: 'text/html' } })
  const side = container.querySelector('.yw-entry-calendar__side')
  const holder = side.querySelector('.yw-entry-calendar__entry')
  holder.innerHTML = response.ok ? await response.text() : ''
  side.hidden = false
  window.htmx?.process(holder)
}

function closeSide(container) {
  const side = container.querySelector('.yw-entry-calendar__side')
  side.hidden = true
  side.querySelector('.yw-entry-calendar__entry').innerHTML = ''
}

ywInitEach('.yw-entry-calendar', (container) => {
  const setup = JSON.parse(
    container.querySelector('.yw-entry-calendar__data').textContent,
  )
  const element = container.querySelector('.yw-calendar')
  element.addEventListener('dblclick', (event) => {
    event.preventDefault()
    event.stopPropagation()
  })

  const calendar = new FullCalendar.Calendar(element, {
    dayMaxEventRows: 3,
    editable: false,
    eventDisplay: 'block',
    eventMaxStack: 3,
    firstDay: 1,
    headerToolbar: {
      start: 'prev today',
      center: 'title',
      end: `dayGridMonth,timeGridWeek,timeGridDay,${setup.listView} next`,
    },
    toolbarClass: 'yw-cal-toolbar',
    toolbarTitleClass: 'yw-cal-title',
    buttonGroupClass: 'yw-cal-button-group',
    buttonClass: (button) =>
      `yw-cal-button yw-cal-button--${button.name}${button.isSelected ? ' yw-cal-button--active' : ''}`,
    inlineWeekNumberClass: 'yw-cal-week-number',
    eventClass: 'yw-cal-event',
    eventTitleClass: 'yw-cal-event-title',
    initialView: setup.initialView,
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
    events: setup.events.flatMap((event) => eventsOf(event, setup)),
    eventDidMount({ event, el }) {
      if (event.extendedProps.modal) {
        Object.assign(el.dataset, { iframe: '1', size: 'modal-lg' })
      }
      if (event.extendedProps.icon && !el.classList.contains('iconDefined')) {
        el.querySelector('.yw-cal-event-title')?.insertAdjacentHTML(
          'afterbegin',
          `${event.extendedProps.icon}&nbsp;`,
        )
      }
      el.classList.add('iconDefined')
      if (setup.display === 'newtab') el.classList.add('new-window')
      el.setAttribute('title', event.title)
    },
    eventClick(info) {
      if (setup.display === 'newtab') {
        info.jsEvent.preventDefault()
        window.open(info.event.url.replace(/\/iframe$/, ''))
      } else if (setup.display === 'direct') {
        info.jsEvent.preventDefault()
        window.location = info.event.url
      } else if (setup.display === 'sidebar') {
        info.jsEvent.preventDefault()
        openInSide(container, info.event.extendedProps.tag)
      } else if (
        !info.event.extendedProps.modal &&
        LIST_VIEWS.includes(info.view.type)
      ) {
        info.jsEvent.preventDefault()
      }
    },
  })
  calendar.render()
  container
    .querySelector('.yw-entry-calendar__close')
    .addEventListener('click', () => closeSide(container))
})
