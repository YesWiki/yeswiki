/* The opening hours editor: rows of days and times added and removed in place, written back as `Mo,Tu 09:00-12:00;Sa 10:00-12:00`. */

function serialise(editor) {
  return [
    ...editor.querySelectorAll(
      '[data-opening-hours-intervals] [data-opening-hours-interval]',
    ),
  ]
    .map((interval) => {
      const days = [...interval.querySelector('select').selectedOptions].map(
        (option) => option.value,
      )
      const hours = [...interval.querySelectorAll('[data-opening-hours-hour]')]
        .map((hour) => [
          hour.querySelector('[data-opening-hours-start]').value,
          hour.querySelector('[data-opening-hours-end]').value,
        ])
        .filter(([start, end]) => start && end)
        .map(([start, end]) => `${start}-${end}`)
      return days.length > 0 ? `${days.join(',')} ${hours.join(',')}` : ''
    })
    .filter(Boolean)
    .join(';')
}

const fromTemplate = (editor, name) =>
  editor
    .querySelector(`template[data-opening-hours-${name}-template]`)
    .content.firstElementChild.cloneNode(true)

ywInitEach('[data-opening-hours-editor]', (editor) => {
  const value = editor.querySelector('[data-opening-hours-value]')
  const update = () => {
    value.value = serialise(editor)
  }

  editor.addEventListener('input', update)
  editor.addEventListener('change', update)
  editor.addEventListener('click', (event) => {
    const button = event.target.closest('button')
    if (!button) return
    if (button.matches('[data-opening-hours-add-day]')) {
      const interval = fromTemplate(editor, 'interval')
      editor.querySelector('[data-opening-hours-intervals]').append(interval)
      interval.querySelector('select').focus()
    } else if (button.matches('[data-opening-hours-add-hour]')) {
      const hour = fromTemplate(editor, 'hour')
      button
        .closest('[data-opening-hours-interval]')
        .querySelector('.opening-hours-hours')
        .append(hour)
      hour.querySelector('input').focus()
    } else if (button.matches('[data-opening-hours-remove-hour]')) {
      button.closest('[data-opening-hours-hour]').remove()
    } else if (button.matches('[data-opening-hours-remove-day]')) {
      button.closest('[data-opening-hours-interval]').remove()
    } else {
      return
    }
    update()
  })
})
