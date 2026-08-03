/**
 * Narrow a list of cards from a text box.
 *
 * A table gets search from `data-yw-datatable`, which knows how to read rows and cells. A
 * list of cards has neither, so this is the same affordance for the shape that replaced
 * it: each card carries the text it can be found by, and nothing has to be re-queried.
 *
 *   <section data-yw-card-filter-scope>
 *     <input data-yw-card-filter>
 *     <article data-yw-card-filter-item="name and description, lowercased">…</article>
 *     <p data-yw-card-filter-empty hidden>nothing matches</p>
 *   </section>
 */
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
