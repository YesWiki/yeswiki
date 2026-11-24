/* An entry's opening hours: today's in the summary, the coming week when it unfolds, read by the opening_hours library. */

const timeOf = (date, locale) =>
  date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })

const listOf = (ranges, closed) => {
  const list = document.createElement('ul')
  list.className = 'opening-hours-display__hours'
  ;(ranges.length > 0 ? ranges : [closed]).forEach((text) => {
    const item = document.createElement('li')
    item.textContent = text
    list.append(item)
  })
  return list
}

ywInitEach('.opening-hours-display[data-opening-hours]', (display) => {
  const locale = document.documentElement.lang || navigator.language
  const today = new Date(new Date().toDateString())
  const weekEnd = new Date(today)
  weekEnd.setDate(today.getDate() + 7)

  const byDay = new Map()
  new opening_hours(display.dataset.openingHours, {}, { locale })
    .getOpenIntervals(today, weekEnd)
    .forEach(([start, end]) => {
      const day = start.getDay()
      if (!byDay.has(day)) {
        byDay.set(day, {
          name: start.toLocaleDateString(locale, { weekday: 'long' }),
          ranges: [],
        })
      }
      byDay
        .get(day)
        .ranges.push(`${timeOf(start, locale)} - ${timeOf(end, locale)}`)
    })

  const closed = display.dataset.closedLabel
  display.querySelector('.opening-hours-display__day').textContent =
    today.toLocaleDateString(locale, { weekday: 'long' })
  display
    .querySelector('.opening-hours-display__hours')
    .replaceWith(listOf(byDay.get(today.getDay())?.ranges ?? [], closed))

  const rows = display.querySelector('.opening-hours-display__week tbody')
  ;[1, 2, 3, 4, 5, 6, 0]
    .filter((day) => byDay.has(day))
    .forEach((day) => {
      const row = rows.insertRow()
      row.insertCell().textContent = byDay.get(day).name
      row.insertCell().append(listOf(byDay.get(day).ranges, closed))
    })
})
