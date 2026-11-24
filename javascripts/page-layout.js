/** The page layout panel of the edit screen: a page name only when a page is chosen, the theme only when the page keeps its own. */

/** Shows the page name field of a header or footer only when "another page" is chosen. */
const followRoleMode = (group) => {
  const mode = group.querySelector('[data-yw-layout-role-mode]')
  const page = group.querySelector('[data-yw-layout-role-page]')
  if (!mode || !page) return
  const update = () => {
    page.hidden = mode.value !== 'page'
    if (!page.hidden && page.value === '') page.focus()
  }
  mode.addEventListener('change', update)
}

/** Shows the theme settings only while the page keeps a theme of its own. */
const followOwnTheme = (panel) => {
  const toggle = panel.querySelector('[data-yw-layout-own-theme]')
  const theme = panel.querySelector('[data-yw-layout-theme]')
  if (!toggle || !theme) return
  toggle.addEventListener('change', () => {
    theme.hidden = !toggle.checked
  })
}

/** Writes the background picked in the carousel into the field the page is saved with. */
const followBackground = (panel) => {
  const input = panel.querySelector('[data-yw-layout-bgimg]')
  const carousel = panel.querySelector('#bgCarousel')
  if (!input || !carousel) return
  carousel.addEventListener('click', (event) => {
    const item = event.target.closest('[data-yw-background-file]')
    if (!item) return
    setTimeout(() => {
      input.value = item.classList.contains('choosen')
        ? item.dataset.ywBackgroundFile
        : ''
    }, 0)
  })
}

ywInitEach('[data-yw-page-layout]', (panel) => {
  panel.querySelectorAll('[data-yw-layout-role]').forEach(followRoleMode)
  followOwnTheme(panel)
  followBackground(panel)
})
