document.addEventListener('change', (event) => {
  const master = event.target.closest('.checkcontent-selectall')
  if (!master) {
    return
  }
  const table = master.closest('table')
  if (!table) {
    return
  }
  table.querySelectorAll('tbody input[type=checkbox]').forEach((box) => {
    box.checked = master.checked
  })
})

document.addEventListener('submit', (event) => {
  const form = event.target.closest('.checkcontent-repair-form')
  if (!form) {
    return
  }
  const selected = form.querySelectorAll('input[name="checkcontent-repair[]"]:checked')
  if (selected.length > 0 && !window.confirm(form.dataset.confirm)) {
    event.preventDefault()
  }
})
