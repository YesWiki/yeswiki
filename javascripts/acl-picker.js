/** A guided ACL picker (templates/_acl-picker.twig) over the textarea the form submits, one rule per line. */
ywInitEach('[data-yw-acl]', (picker) => {
  const source = picker.querySelector('.yw-acl__source')
  const guided = picker.querySelector('.yw-acl__guided')
  const advanced = picker.querySelector('.yw-acl__advanced')
  const membersBlock = picker.querySelector('[data-yw-acl-members]')
  const extra = picker.querySelector('[data-yw-acl-extra]')
  const toggle = picker.querySelector('[data-yw-acl-toggle]')
  const audiences = Array.from(
    picker.querySelectorAll('[data-yw-acl-audience]'),
  )
  const members = Array.from(picker.querySelectorAll('[data-yw-acl-member]'))
  if (!source || !guided || !advanced || !extra || !toggle) return

  const ONLY = 'only'
  const broad = audiences.map((r) => r.value).filter((v) => v !== ONLY)
  const known = members.map((box) => box.value)

  const rules = (text) =>
    String(text || '')
      .split(/[\n,]/)
      .map((rule) => rule.trim())
      .filter((rule) => rule !== '')

  const needsAdvanced = (list) =>
    list.some((rule) => rule.startsWith('!') || rule.startsWith('#'))

  const showMembers = () => {
    const chosen = audiences.find((r) => r.checked)
    membersBlock.hidden = !chosen || chosen.value !== ONLY
  }

  const read = () => {
    const current = rules(source.value)
    const wide = broad.find((value) => current.includes(value))
    audiences.forEach((r) => {
      r.checked = wide
        ? r.value === wide
        : current.length > 0 && r.value === ONLY
    })
    members.forEach((box) => {
      box.checked = current.includes(box.value)
    })
    extra.value = current
      .filter((rule) => !known.includes(rule) && !broad.includes(rule))
      .join(', ')
    showMembers()
  }

  const write = () => {
    const chosen = audiences.find((r) => r.checked)
    if (chosen && chosen.value !== ONLY) {
      source.value = chosen.value
      return
    }
    const picked = members.filter((box) => box.checked).map((box) => box.value)
    const names = rules(extra.value).filter((rule) => !picked.includes(rule))
    source.value = picked.concat(names).join('\n')
  }

  const setAdvanced = (on) => {
    if (!on && needsAdvanced(rules(source.value))) return
    if (!on) read()
    guided.hidden = on
    advanced.hidden = !on
    toggle.textContent = on
      ? toggle.dataset.labelSimple
      : toggle.dataset.labelAdvanced
  }

  audiences.forEach((r) =>
    r.addEventListener('change', () => {
      showMembers()
      write()
    }),
  )
  members.forEach((box) => box.addEventListener('change', write))
  extra.addEventListener('input', write)
  toggle.addEventListener('click', () => setAdvanced(!guided.hidden))

  read()
  setAdvanced(needsAdvanced(rules(source.value)))
})
