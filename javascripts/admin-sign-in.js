// The sign-in providers screen: the two row tables (group mapping, extra attributes) grow and shrink.
// The matching boxes (by name, by email) show, for the ticked ones only, the warning
// that the provider can take an existing account.
// A delete, or switching off the accounts the administrator signs in with, asks first: the forms carry `data-yw-confirm`,
// which the wiki's own dialog answers (javascripts/confirm.js).

ywInitEach('[data-yw-sign-in-rows]', (section) => {
  const body = section.querySelector('[data-yw-sign-in-rows-body]')
  const template = section.querySelector(
    'template[data-yw-sign-in-row-template]',
  )
  if (!body || !template) return

  // Indexes only have to be distinct inside the table: the server reads rows, not positions.
  let next = body.querySelectorAll('tr').length

  section
    .querySelector('[data-yw-sign-in-add]')
    ?.addEventListener('click', () => {
      const html = template.innerHTML.replaceAll('__i__', String(next++))
      body.insertAdjacentHTML('beforeend', html)
      body.lastElementChild?.querySelector('input, select')?.focus()
    })

  section.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-yw-sign-in-remove]')
    if (remove) remove.closest('tr')?.remove()
  })
})

// Each matching box has its own warning right under it, shown while the box is ticked.
ywInitEach('[data-yw-sign-in-takeover]', (alert) => {
  const box = (alert.closest('form') ?? document).querySelector(
    `[data-yw-sign-in-match="${alert.dataset.ywSignInTakeover}"]`,
  )
  if (!box) return
  const refresh = () => {
    alert.hidden = !box.checked
  }
  box.addEventListener('change', refresh)
  // A browser may restore the box as it was before a reload, unlike the server-rendered alert.
  refresh()
})
