ywInitEach('[data-yw-other-languages]', (list) => {
  const select = document.querySelector('[data-yw-main-language]')
  if (!select) return

  function sync() {
    list.querySelectorAll('[data-language]').forEach((option) => {
      const isMain = option.dataset.language === select.value
      option.hidden = isMain
      if (isMain) {
        const box = option.querySelector('input[type="checkbox"]')
        if (box) box.checked = false
      }
    })
  }

  select.addEventListener('change', sync)
  sync()
})
