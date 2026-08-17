/** Narrow a list of cards from a text box. */
ywInitEach('[data-yw-card-filter]', (input) => {
  const scope = input.closest('[data-yw-card-filter-scope]') ?? document
  const cards = () => scope.querySelectorAll('[data-yw-card-filter-item]')
  const empty = scope.querySelector('[data-yw-card-filter-empty]')

  const apply = () => {
    const needle = input.value.trim().toLowerCase()
    let shown = 0
    cards().forEach((cardParam) => {
      const card = cardParam
      const haystack = card.dataset.ywCardFilterItem || ''
      const matches = needle === '' || haystack.includes(needle)
      card.hidden = !matches
      if (matches) shown += 1
    })
    if (empty) empty.hidden = shown > 0
  }

  input.addEventListener('input', apply)
  apply()
})
