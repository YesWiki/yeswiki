/** The groups rail, re-bound on every htmx swap -- see the note in admin-files.js. */
ywInitEach('#yw-groups-rail', (rail) => {
  const screens = [...rail.querySelectorAll('[data-yw-groups-screen]')]

  /** Which face of the drawer is showing. The other is `hidden`, and that is the whole state. */
  function showScreen(name) {
    for (const screen of screens) {
      screen.hidden = screen.dataset.ywGroupsScreen !== name
    }
  }

  rail.querySelector('[data-yw-groups-new]')?.addEventListener('click', () => {
    showScreen('new')
    rail.querySelector('#yw-groups-new-name')?.focus()
  })

  rail
    .querySelector('[data-yw-groups-back]')
    ?.addEventListener('click', () => showScreen('list'))

  rail
    .querySelector('[data-yw-groups-close]')
    ?.addEventListener('click', () => {
      showScreen('list')
      rail.hidden = true
    })

  document.querySelectorAll('[data-yw-groups-open]').forEach((button) => {
    button.addEventListener('click', () => {
      rail.hidden = false
    })
  })

  // One form per card carries both buttons, so which one was pressed decides whether to ask.
  rail.querySelectorAll('[data-yw-groups-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!event.submitter?.hasAttribute('data-yw-groups-delete')) return
      if (!window.confirm(form.dataset.confirm)) event.preventDefault()
    })
  })
})
