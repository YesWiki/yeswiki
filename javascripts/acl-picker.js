/** An ACL picker (templates/_acl-picker.twig): checkboxes and a free line, both writing the hidden textarea the form submits. */
ywInitEach('[data-yw-acl]', (picker) => {
  const source = picker.querySelector('.yw-acl__source')
  const extra = picker.querySelector('[data-yw-acl-extra]')
  const boxes = Array.from(picker.querySelectorAll('[data-yw-acl-option]'))
  if (!source || !extra) return

  const rules = (text) =>
    String(text || '')
      .split(/[\n,]/)
      .map((rule) => rule.trim())
      .filter((rule) => rule !== '')

  const known = boxes.map((box) => box.value)

  const read = () => {
    const current = rules(source.value)
    boxes.forEach((box) => {
      box.checked = current.includes(box.value)
    })
    extra.value = current.filter((rule) => !known.includes(rule)).join(', ')
  }

  const write = () => {
    const chosen = boxes.filter((box) => box.checked).map((box) => box.value)
    const others = rules(extra.value).filter((rule) => !chosen.includes(rule))
    source.value = chosen.concat(others).join('\n')
  }

  boxes.forEach((box) => box.addEventListener('change', write))
  extra.addEventListener('input', write)
  extra.addEventListener('change', () => {
    write()
    read()
  })
  read()
})
