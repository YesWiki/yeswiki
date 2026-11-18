// The sign-in providers screen: the two row tables (group mapping, extra attributes) grow and shrink.

ywInitEach('[data-yw-sign-in-rows]', (section) => {
  const body = section.querySelector('[data-yw-sign-in-rows-body]')
  const template = section.querySelector(
    'template[data-yw-sign-in-row-template]',
  )
  if (!body || !template) return

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

ywInitEach('[data-yw-sign-in-takeover]', (alert) => {
  const box = (alert.closest('form') ?? document).querySelector(
    `[data-yw-sign-in-match="${alert.dataset.ywSignInTakeover}"]`,
  )
  if (!box) return
  const refresh = () => {
    alert.hidden = !box.checked
  }
  box.addEventListener('change', refresh)
  refresh()
})
