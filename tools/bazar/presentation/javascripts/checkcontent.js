document.addEventListener('change', (event) => {
  const master = event.target.closest('.checkcontent-selectall')
  if (!master) {
    return
  }
  document
    .querySelectorAll(`${master.dataset.target} input[type=checkbox]`)
    .forEach((box) => {
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
