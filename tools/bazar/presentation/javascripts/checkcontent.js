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
  const selected = form.querySelectorAll(
    'input[name="checkcontent-repair[]"]:checked',
  )
  if (selected.length > 0 && !window.confirm(form.dataset.confirm)) {
    event.preventDefault()
    return
  }
  form.querySelectorAll('tbody tr').forEach((row) => {
    const box = row.querySelector('input[name="checkcontent-repair[]"]')
    if (box && box.checked) {
      return
    }
    row.querySelectorAll('input, select, textarea').forEach((field) => {
      field.disabled = true
    })
  })
})
