;(function () {
  const listSelector =
    '.bazar-radio-rows, .bazar-checkbox-cols, .list-bazar-entries'

  document.addEventListener('input', (e) => {
    const filter = e.target.closest('.filter-entries')
    if (!filter) return
    let list = filter.parentElement.querySelector(listSelector)
    if (!list && filter.parentElement.parentElement) {
      list = filter.parentElement.parentElement.querySelector(listSelector)
    }
    if (!list) return
    const needle = filter.value.trim().toLowerCase()
    list.querySelectorAll('.radio, .checkbox').forEach((rowParam) => {
      const row = rowParam
      const matches = !needle || row.textContent.toLowerCase().includes(needle)
      row.style.display = matches ? '' : 'none'
    })
  })
})()
