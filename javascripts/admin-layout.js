/**
 * The Layout screen's own controls: the logo, the bar height, and where the chrome sits.
 *
 * The two menus it edits are edited by `menu-editor.js`, which every menu screen shares (ticket 64).
 */
import './unsaved-changes.js'

ywInitEach('[data-yw-layout-logo]', (logo) => {
  const preview = document.querySelector('[data-yw-layout-logo-preview]')
  const removeLogo = document.querySelector('[data-yw-layout-logo-remove]')

  const show = () => {
    const chosen = logo.value.trim() !== ''
    if (preview) {
      preview.src = logo.value
      preview.hidden = !chosen
    }
    if (removeLogo) removeLogo.hidden = !chosen
  }

  logo.addEventListener('input', show)
  logo.addEventListener('change', show)

  removeLogo?.addEventListener('click', () => {
    logo.value = ''
    logo.dispatchEvent(new Event('change', { bubbles: true }))
  })
})

ywInitEach('[data-yw-layout-height]', (height) => {
  const heightValue = document.querySelector('[data-yw-layout-height-value]')

  const apply = () => {
    const px = `${height.value}px`
    document.documentElement.style.setProperty('--yw-navbar-height', px)
    if (heightValue) heightValue.textContent = px
  }
  apply()
  height.addEventListener('input', apply)
})

ywInitEach('[data-yw-layout-navbar-position]', (choice) => {
  const apply = () => {
    if (choice.checked) document.documentElement.dataset.ywNavbar = choice.value
  }
  apply()
  choice.addEventListener('change', apply)
})

ywInitEach('input[name="layout_header_position"]', (choice) => {
  const apply = () => {
    if (!choice.checked) return
    document.documentElement.dataset.ywHeader = choice.value
    const header = document.getElementById('yw-header')
    const nav = document.getElementById('yw-topnav')
    if (!header || !nav || header.parentNode !== nav.parentNode) return
    nav.parentNode.insertBefore(
      header,
      choice.value === 'before' ? nav : nav.nextSibling,
    )
    window.dispatchEvent(new Event('resize'))
  }
  apply()
  choice.addEventListener('change', apply)
})
