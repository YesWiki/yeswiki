/* The recurrence of an event: the server draws every control, this shows the ones the chosen repetition needs and keeps the derived values in step. */

const NB_MAX = 600
const WEEKDAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat']

const isoDay = (date) =>
  new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
    .toISOString()
    .slice(0, 10)

const dateIn = (input) => {
  const date = input?.value ? new Date(input.value) : null
  return date && !Number.isNaN(date.getTime()) ? date : null
}

ywInitEach('[data-recurrence]', (root) => {
  const field = root.dataset.field
  const $ = (selector) => root.querySelector(selector)
  const repetition = $('[data-recurrence-repetition]')
  const rule = $('[data-recurrence-rule]')
  const limit = $('[data-recurrence-limit]')
  const limitHint = $('[data-recurrence-limit-hint]')
  const inMonth = $('[data-recurrence-in-month]')
  const weekday = $('[data-recurrence-weekday]')
  const exceptChoice = $('[data-recurrence-except-choice]')
  const exceptList = $('[data-recurrence-except]')
  const start = () => document.getElementById('bf_date_debut_evenement')
  let chosen = repetition.value

  const kind = () => (/^x?[dwmy]$/.test(chosen) ? chosen.slice(-1) : '')
  const step = () => {
    if (!chosen.startsWith('x')) return 1
    const input = root.querySelector(
      `[data-recurrence-when="${chosen}"]:not([disabled]) .recurrence__step`,
    )
    return Math.max(2, Number(input?.value) || 2)
  }
  const startDay = () => {
    const date = dateIn(start())
    return date ? WEEKDAYS[date.getDay()] : ''
  }
  const days = () => {
    if (kind() === 'w') {
      return [...root.querySelectorAll('[data-recurrence-day]:checked')].map(
        (box) => box.value,
      )
    }
    if (
      ['m', 'y'].includes(kind()) &&
      inMonth.value &&
      inMonth.value !== 'nthOfMonth'
    ) {
      return [weekday.value]
    }
    return []
  }
  const excepted = () =>
    [...exceptList.querySelectorAll('[data-recurrence-except-date]')].map(
      (item) => item.dataset.recurrenceExceptDate,
    )

  const show = () => {
    const current = kind()
    const when = inMonth.value
    const visible = {
      [`x${current}`]: chosen.startsWith('x'),
      y: current === 'y',
      'm y': ['m', 'y'].includes(current),
      w: current === 'w',
      weekday:
        ['m', 'y'].includes(current) && when !== '' && when !== 'nthOfMonth',
      nth: ['m', 'y'].includes(current) && when === 'nthOfMonth',
    }
    rule.disabled = current === ''
    rule.hidden = current === ''
    root.querySelectorAll('[data-recurrence-when]').forEach((set) => {
      const on = Boolean(visible[set.dataset.recurrenceWhen])
      set.disabled = !on
      set.hidden = !on
    })
  }

  const allowedOccurrences = () => {
    const from = dateIn(start())
    const until = dateIn(limit)
    if (!from || !until) return NB_MAX
    const span = until.getTime() - from.getTime()
    if (span < 0) return 0
    const daysPerStep =
      { y: 365, w: Math.floor(7 / step()) || 1, m: 28 }[kind()] ?? 1
    return Math.ceil(span / 86400000 / daysPerStep) + 20
  }

  const occurrences = (count) => {
    const from = dateIn(start())
    if (!from || kind() === '') return []
    const anchor = isoDay(from)
    const until = dateIn(limit)
    return window._bazarRecurrenceCalculator
      .generateOccurrences({
        startDate: anchor,
        endDate: anchor,
        repetition: kind(),
        step: step(),
        days: days(),
        month: $('[data-recurrence-month]').value,
        whenInMonth: inMonth.value,
        nth: $('[data-recurrence-nth]').value,
        nbmax: count,
        limitdate: until ? isoDay(until) : null,
        except: [],
      })
      .slice(1)
      .map((occurrence) => occurrence.start)
  }

  const update = () => {
    show()
    $('[data-recurrence-kind]').value = kind()
    $('[data-recurrence-step]').value = step()
    const holder = $('[data-recurrence-days]')
    holder.replaceChildren(
      ...days().map((day, index) => {
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = `${field}_data[days][${index}]`
        input.value = day
        return input
      }),
    )

    const wanted = allowedOccurrences()
    const available = occurrences(Math.min(wanted, NB_MAX))
    $('[data-recurrence-nbmax]').value = Math.max(2, available.length)
    if (
      wanted > NB_MAX &&
      available.length > 0 &&
      limit.value > available.at(-1)
    ) {
      limit.value = available.at(-1)
      limitHint.hidden = false
      window.setTimeout(() => {
        limitHint.hidden = true
      }, 3000)
    }

    const taken = excepted()
    exceptChoice.replaceChildren(
      exceptChoice.options[0],
      ...available
        .filter((date) => !taken.includes(date))
        .map((date) => new Option(date, date)),
    )
  }

  const addExcept = (date) => {
    const item = $(
      'template[data-recurrence-except-template]',
    ).content.firstElementChild.cloneNode(true)
    item.dataset.recurrenceExceptDate = date
    item.querySelector('[data-recurrence-except-label]').textContent = date
    item.querySelector('input').value = date
    exceptList.append(item)
  }

  repetition.addEventListener('change', () => {
    if (['activateCustom', 'removeCustom'].includes(repetition.value)) {
      const custom = repetition.value === 'activateCustom'
      root.querySelectorAll('[data-recurrence-custom]').forEach((option) => {
        option.hidden = !custom
        option.disabled = !custom
      })
      root.querySelectorAll('[data-recurrence-plain]').forEach((option) => {
        option.hidden = custom
        option.disabled = custom
      })
      if (!custom) chosen = chosen.replace('x', '')
      repetition.value = chosen
    } else {
      chosen = repetition.value
    }
    if (kind() === 'w' && days().length === 0 && startDay()) {
      root.querySelector(
        `[data-recurrence-day][value="${startDay()}"]`,
      ).checked = true
    }
    update()
  })

  root.addEventListener('change', (event) => {
    const day = event.target.closest('[data-recurrence-day]')
    if (day && !day.checked && day.value === startDay()) day.checked = true
    if (event.target === exceptChoice) {
      if (exceptChoice.value) addExcept(exceptChoice.value)
    }
    if (event.target !== repetition) update()
  })
  root.addEventListener('input', (event) => {
    if (event.target.matches('.recurrence__step')) update()
  })
  root.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-recurrence-except-remove]')
    if (!remove) return
    remove.closest('[data-recurrence-except-date]').remove()
    update()
  })
  start()?.addEventListener('change', update)

  update()
})
