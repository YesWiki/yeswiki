/** The Journal's filters and pager, re-bound on every htmx swap -- see the note in admin-files.js. */
ywInitEach('#yw-logs', (screen) => {
  const filters = screen.querySelector('#yw-logs-filters')
  const pageInput = filters?.querySelector('input[name="page"]')
  if (!filters || !pageInput) return

  /**
   * Changing a filter puts you back on the first page: page 7 of the old result set means
   * nothing. Bound in the capture phase, because htmx listens for the same events on the way
   * back up -- in the bubble phase this would reset the page *after* the request carrying it had
   * already been built.
   */
  const backToTheFirstPage = (event) => {
    if (event.target !== pageInput) pageInput.value = '1'
  }
  filters.addEventListener('change', backToTheFirstPage, true)
  filters.addEventListener('input', backToTheFirstPage, true)

  window.ywLogsGoTo = (page) => {
    pageInput.value = String(page)
    document.body.dispatchEvent(new CustomEvent('ywLogsReload'))
  }
})
