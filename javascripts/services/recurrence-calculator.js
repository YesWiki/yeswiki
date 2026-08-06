// Shared recurrence date-math for bazar "repeated dates" events.
// Loaded as a plain classic script (not an ES module) so it can be consumed both by
// recurrent-event.js (classic script) and BazarCalendar.js (ES module, via window.*).
const DEFAULT_MAXIMUM_REPETITION = 600

const daysToCodeAssoc = {
  mon: 1,
  tue: 2,
  wed: 3,
  thu: 4,
  fri: 5,
  sat: 6,
  sun: 7,
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
  dec: 11,
}
const wantedPositionList = {
  fisrtOfMonth: 1,
  secondOfMonth: 2,
  thirdOfMonth: 3,
  forthOfMonth: 4,
  lastOfMonth: 99,
}

function pad(n) {
  return String(n).padStart(2, '0')
}

// date-only strings ('YYYY-MM-DD') must be parsed as local midnight, not UTC midnight
// (the native `new Date('YYYY-MM-DD')` parses as UTC, which shifts the calendar day in
// non-UTC browsers) — this mirrors the day itself, not the instant.
function parseDateString(dateStr) {
  if (typeof dateStr !== 'string' || dateStr.length === 0) {
    return null
  }
  if (dateStr.length <= 10) {
    const match = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (!match) return null
    const [, y, m, d] = match
    return new Date(Number(y), Number(m) - 1, Number(d))
  }
  const date = new Date(dateStr)
  return date.toString() === 'Invalid Date' ? null : date
}

function formatDateString(date, hasTime) {
  if (!hasTime) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
  }
  return date.toISOString()
}

function getNbDaysInMonth(year, month) {
  const firstDayOfMonth = new Date(year, month, 1)
  const lastDayOfMonth = new Date(year, month + 1, 0)
  return Math.round((lastDayOfMonth - firstDayOfMonth) / 1000 / 3600 / 24) + 1
}

function calculateNextMonth(nextStartMonth, currentStartYear, step) {
  const newMonth = nextStartMonth + step
  return newMonth > 11
    ? { nextStartMonth: newMonth - 12, currentStartYear: currentStartYear + 1 }
    : { nextStartMonth: newMonth, currentStartYear }
}

function findNextStartDate(
  date,
  startYear,
  startMonth,
  whenInMonth,
  nth,
  daysCodes,
  callback,
) {
  if (whenInMonth === 'nthOfMonth') {
    let limit = 60
    let currentStartYear = startYear
    let nextStartMonth = startMonth
    const nthValue = Number(nth) || 1
    while (
      limit > 0 &&
      nthValue > getNbDaysInMonth(currentStartYear, nextStartMonth)
    ) {
      const data = callback(nextStartMonth, currentStartYear)
      nextStartMonth = data?.nextStartMonth ?? nextStartMonth
      currentStartYear = data?.currentStartYear ?? currentStartYear
      limit -= 1
    }
    const newStartDate = new Date(date.getTime())
    newStartDate.setFullYear(currentStartYear, nextStartMonth, nthValue)
    return newStartDate
  }
  const wantedPosition = wantedPositionList?.[whenInMonth] ?? 1
  const nbDaysInMonth = getNbDaysInMonth(startYear, startMonth)
  const day = daysCodes.reduce((acc, d) => Math.min(acc, d), 7)
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
}

function calculateNextWeeklyDate(date, daysCodes, step) {
  const currentStartDayCode = date.getDay() || 7
  const maxDaysCode = daysCodes.reduce((acc, val) => Math.max(acc, val), 1)
  const newDate = new Date(date.getTime())
  if (
    !daysCodes.includes(currentStartDayCode) ||
    currentStartDayCode === maxDaysCode
  ) {
    const nextWantedDay = daysCodes.reduce((acc, val) => Math.min(acc, val), 7)
    newDate.setDate(
      newDate.getDate() +
        nextWantedDay +
        7 * (step - 1) +
        7 -
        currentStartDayCode,
    )
  } else {
    const nextWantedDay = daysCodes
      .filter((d) => d > currentStartDayCode)
      .reduce((acc, val) => Math.min(acc, val), 7)
    newDate.setDate(newDate.getDate() + nextWantedDay - currentStartDayCode)
  }
  return newDate
}

function calculateNextDate(
  date,
  repetition,
  step,
  daysCodes,
  monthCode,
  whenInMonth,
  nth,
) {
  switch (repetition) {
    case 'y': {
      const nextStartYear = date.getFullYear() + step
      const nextStartMonth = monthCode
      return findNextStartDate(
        date,
        nextStartYear,
        nextStartMonth,
        whenInMonth,
        nth,
        daysCodes,
        (m, y) => ({
          nextStartMonth: m,
          currentStartYear: y + step,
        }),
      )
    }
    case 'm': {
      const { nextStartMonth, currentStartYear } = calculateNextMonth(
        date.getMonth(),
        date.getFullYear(),
        step,
      )
      return findNextStartDate(
        date,
        currentStartYear,
        nextStartMonth,
        whenInMonth,
        nth,
        daysCodes,
        (m, y) => calculateNextMonth(m, y, step),
      )
    }
    case 'w':
      return calculateNextWeeklyDate(date, daysCodes, step)
    case 'd':
    default: {
      const newDate = new Date(date.getTime())
      newDate.setDate(newDate.getDate() + step)
      return newDate
    }
  }
}

/**
 * @returns {Array<{start: string, end: string}>} ordered occurrences, element 0 is the anchor itself
 */
function generateOccurrences({
  startDate,
  endDate,
  repetition,
  step,
  days,
  month,
  whenInMonth,
  nth,
  nbmax,
  limitdate,
  except,
}) {
  const start = parseDateString(startDate)
  if (start === null) {
    return []
  }
  const end = parseDateString(endDate) ?? start

  const hasStartTime = typeof startDate === 'string' && startDate.length > 10
  const hasEndTime = typeof endDate === 'string' && endDate.length > 10

  const daysCodes = (Array.isArray(days) ? days : [])
    .map((d) => daysToCodeAssoc[d])
    .filter((c) => !!c)
    .sort((a, b) => a - b)
  const usedDays = daysCodes.length > 0 ? daysCodes : [start.getDay() || 7]
  const monthCode = Object.prototype.hasOwnProperty.call(
    monthsToCodeAssoc,
    month,
  )
    ? monthsToCodeAssoc[month]
    : start.getMonth()

  const stepInt = Math.max(1, Number(step) || 1)
  const nbmaxInt = Math.min(
    Math.max(1, Number(nbmax) || 1),
    DEFAULT_MAXIMUM_REPETITION,
  )
  const exceptSet = new Set(Array.isArray(except) ? except : [])
  const limitDateObj = limitdate ? parseDateString(limitdate) : null
  const limitTime = limitDateObj ? limitDateObj.getTime() : null

  const occurrences = []
  let curStart = start
  let curEnd = end

  // the anchor occurrence (the entry's own dates) is never subject to `except`,
  // only occurrences computed further in the loop can be skipped
  occurrences.push({
    start: formatDateString(curStart, hasStartTime),
    end: formatDateString(curEnd, hasEndTime),
  })

  for (let i = 1; i <= nbmaxInt; i++) {
    const nextStart = calculateNextDate(
      curStart,
      repetition,
      stepInt,
      usedDays,
      monthCode,
      whenInMonth,
      nth,
    )
    if (!nextStart || nextStart.toString() === 'Invalid Date') {
      break
    }
    const delta = nextStart.getTime() - curStart.getTime()
    const nextEnd = new Date(curEnd.getTime() + delta)
    curStart = nextStart
    curEnd = nextEnd
    // limitdate is an exclusive upper bound: an occurrence starting or ending on/after it is dropped, and generation stops
    if (
      limitTime !== null &&
      (curStart.getTime() >= limitTime || curEnd.getTime() >= limitTime)
    ) {
      break
    }
    const startDatePart = `${curStart.getFullYear()}-${pad(curStart.getMonth() + 1)}-${pad(curStart.getDate())}`
    if (!exceptSet.has(startDatePart)) {
      occurrences.push({
        start: formatDateString(curStart, hasStartTime),
        end: formatDateString(curEnd, hasEndTime),
      })
    }
  }

  return occurrences
}

function isLegacyRecurrenceChild(value) {
  return (
    typeof value === 'string' && /^\{"recurrentParentId":"[^"]+"}$/.test(value)
  )
}

if (!window._bazarRecurrenceCalculator) {
  window._bazarRecurrenceCalculator = {
    generateOccurrences,
    isLegacyRecurrenceChild,
  }
}
