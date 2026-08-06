// javascripts/admin-layout.js -- the Layout screen (ticket 30).
//
// Two lists of rows, edited in place: the navbar and the quick menu. Everything here is
// list editing -- add, remove, move, and (navbar only) indent a row under the one above --
// plus renumbering, which is the part that matters.
//
// **The row's position IS its field name.** A row posts `navbar[3][label]`, so the three
// inputs of one row arrive as one array server-side. Any add, remove or move therefore has
// to renumber every row after it, or two rows end up sharing an index and one of them
// silently replaces the other. That is why renumber() runs after every mutation rather than
// only on submit: a form that is renumbered lazily is one refresh away from being wrong.
//
// Nothing here is required to save: the rows the server rendered are already correct, and
// a row is deleted by emptying its label. The buttons are what makes it pleasant.
//
// The form carries `data-yw-unsaved-guard`, so leaving with changes asks first. That matters
// more here than on most screens: the live preview makes the page *look* as though the
// change has taken effect, and only Save writes it.
import './unsaved-changes.js'
// Imported, not merely used: `window.ywAutocomplete` is a core asset on every page, but this
// module is a *module* and the core scripts are deferred classics -- both execute in document
// order after parsing, and the template emits this one first. So the helper genuinely was not
// defined yet when this ran, the link fields were skipped, and nothing said so. An import is
// evaluated before the importing module's body, which settles it. The classic copy loads
// afterwards and reassigns the same global from the cache-busted URL, so this cannot pin a
// stale version.
import './yw-autocomplete.js'

/** The rows of one list, in document order. */
function rowsOf(list) {
  return [...list.querySelectorAll('[data-yw-layout-row]')]
}

/**
 * The wiki's page names, fetched once and shared by every link field on the screen.
 *
 * `?api/pages`, with the `?` -- `wiki.url('/api/…')` redirects to the home page and the
 * suggestions silently never arrive.
 */
let pageNames = null
function pages() {
  pageNames ??= fetch(wiki.url('?api/pages'))
    .then((response) =>
      response.ok ? response.json() : Promise.reject(response),
    )
    .then((data) =>
      Object.values(data)
        .map((page) => page.tag)
        .filter(Boolean),
    )
    // a screen with no suggestions still edits the menu: the field is free text, and a link
    // may just as well be an external address
    .catch(() => [])

  return pageNames
}

/**
 * Offer those names on every link field in `root`, the way the editor's own link rail does.
 *
 * Marked as it goes: rows are added after this first runs, and wiring the same field twice
 * would stack two dropdowns over each other.
 */
function suggestPagesIn(root) {
  root
    .querySelectorAll('.yw-layout__link:not([data-yw-layout-suggested])')
    .forEach((field) => {
      field.dataset.ywLayoutSuggested = '1'
      pages().then((names) => {
        window.ywAutocomplete(field, {
          items: 6,
          // 0 would drop the whole wiki over the field the moment it takes focus
          minLength: 1,
          source: (query) =>
            names.filter((name) =>
              name.toLowerCase().includes(query.toLowerCase()),
            ),
        })
      })
    })
}

/**
 * Rewrite every field name in the list so the indices are 0..n-1 in document order.
 *
 * Only the index is touched: `navbar[7][label]` becomes `navbar[2][label]`, and the field
 * keeps whatever name the template gave it.
 */
function renumber(list) {
  rowsOf(list).forEach((row, index) => {
    row.querySelectorAll('[name]').forEach((field) => {
      field.setAttribute('name', field.name.replace(/\[\d+\]/, `[${index}]`))
    })
  })
}

/** A row cannot be a child of nothing: the first row of the list is always top level. */
function normalizeIndents(list) {
  rowsOf(list).forEach((row, index) => {
    if (index === 0) setChild(row, false)
  })
}

function setChild(row, child) {
  const flag = row.querySelector('[data-yw-layout-child]')
  if (!flag) return
  flag.value = child ? '1' : '0'
  row.classList.toggle('yw-layout__row--child', child)

  // the button points the way this row can now go: right to become a submenu, left to come
  // back out. Both titles were rendered onto it by the template.
  const button = row.querySelector('[data-yw-layout-indent]')
  if (!button) return
  const use = button.querySelector('use')
  if (use) {
    const href = use.getAttribute('href') || ''
    use.setAttribute(
      'href',
      href.replace(/#.*$/, child ? '#arrow-left' : '#arrow-right'),
    )
  }
  const title = child
    ? button.dataset.ywLayoutTitleOutdent
    : button.dataset.ywLayoutTitleIndent
  if (title) button.title = title
}

function isChild(row) {
  return row.querySelector('[data-yw-layout-child]')?.value === '1'
}

/**
 * The rows a move has to carry as one: a top-level row takes the submenu entries under it.
 *
 * Moving the parent alone left its children behind, adopted by whichever entry happened to
 * land above them -- the list still *read* as valid, so nothing complained, and the menu
 * came out rearranged in a way nobody asked for.
 */
function groupOf(row) {
  const group = [row]
  if (isChild(row)) return group
  let next = row.nextElementSibling
  while (next && isChild(next)) {
    group.push(next)
    next = next.nextElementSibling
  }
  return group
}

/** Move `row` one place, taking its submenu with it and stepping over whole groups. */
function moveRow(list, row, step) {
  const up = step < 0

  // A submenu entry moves among its own siblings only: above its parent is not a position
  // it can hold, and past the next parent would silently re-parent it.
  if (isChild(row)) {
    const sibling = up ? row.previousElementSibling : row.nextElementSibling
    if (!sibling || !isChild(sibling)) return
    list.insertBefore(up ? row : sibling, up ? sibling : row)

    return
  }

  const group = groupOf(row)
  if (up) {
    // back up over the previous group to reach the entry it hangs off
    let target = group[0].previousElementSibling
    if (!target) return
    while (isChild(target) && target.previousElementSibling) {
      target = target.previousElementSibling
    }
    group.forEach((member) => list.insertBefore(member, target))

    return
  }

  const following = group[group.length - 1].nextElementSibling
  if (!following) return
  const nextGroup = groupOf(following)
  // null appends, which is the right answer when that group ends the list
  const anchor = nextGroup[nextGroup.length - 1].nextElementSibling
  group.forEach((member) => list.insertBefore(member, anchor))
}

function refresh(list) {
  normalizeIndents(list)
  renumber(list)
}

document.querySelectorAll('[data-yw-layout-rows]').forEach((list) => {
  const name = list.dataset.ywLayoutRows
  const template = document.querySelector(`[data-yw-layout-template="${name}"]`)

  document
    .querySelector(`[data-yw-layout-add="${name}"]`)
    ?.addEventListener('click', () => {
      if (!template) return
      list.appendChild(template.content.cloneNode(true))
      refresh(list)
      suggestPagesIn(list)
      // the label of the row just added: adding an entry is followed by naming it
      rowsOf(list).at(-1)?.querySelector('input[type="text"]')?.focus()
    })

  list.addEventListener('click', (event) => {
    const row = event.target.closest('[data-yw-layout-row]')
    if (!row) return

    if (event.target.closest('[data-yw-layout-remove]')) {
      row.remove()
      refresh(list)
      return
    }

    const move = event.target.closest('[data-yw-layout-move]')
    if (move) {
      moveRow(list, row, Number(move.dataset.ywLayoutMove))
      refresh(list)
      return
    }

    // one button, both ways: a top-level row goes under the one above, a child comes back
    // out. There is only one level, so the second press is the only thing "outdent" can mean
    if (event.target.closest('[data-yw-layout-indent]')) {
      setChild(row, !isChild(row))
      refresh(list)
    }
  })

  refresh(list)
  suggestPagesIn(list)
})

// The logo: a hidden field, a preview of it, and two buttons.
//
// The field is what the form posts and the only thing that holds the value; the picker
// writes into it (javascripts/inputs/file-picker-field.js, which this screen reuses
// wholesale, and which dispatches `change` after it does). So the preview and the Remove
// button follow **the field**, not the picker -- which is also what makes Remove a one-liner.
const logo = document.querySelector('[data-yw-layout-logo]')
const preview = document.querySelector('[data-yw-layout-logo-preview]')
const removeLogo = document.querySelector('[data-yw-layout-logo-remove]')

if (logo) {
  const show = () => {
    const chosen = logo.value.trim() !== ''
    if (preview) {
      preview.src = logo.value
      preview.hidden = !chosen
    }
    // nothing to remove when there is nothing there: the button would be a no-op that looks
    // like a control
    if (removeLogo) removeLogo.hidden = !chosen
  }

  logo.addEventListener('input', show)
  logo.addEventListener('change', show)

  removeLogo?.addEventListener('click', () => {
    logo.value = ''
    // through the same event the picker fires, so there is one path that updates this card
    logo.dispatchEvent(new Event('change', { bubbles: true }))
  })
}

// The navbar height slider.
//
// Everything else on this screen previews over htmx, because everything else is *markup* and
// the server is what knows how to render it. Height is not markup: it is one custom property,
// and the round trip is exactly the lag that makes a slider feel broken. So this one is
// written straight onto the document, which is also where the saved value ends up -- the
// squelette puts it on <html>, inline, so it beats every stylesheet (ticket 30).
const height = document.querySelector('[data-yw-layout-height]')
const heightValue = document.querySelector('[data-yw-layout-height-value]')

if (height) {
  const apply = () => {
    const px = `${height.value}px`
    document.documentElement.style.setProperty('--yw-navbar-height', px)
    if (heightValue) heightValue.textContent = px
  }
  apply()
  // `input` fires while dragging, which is the whole point of a slider
  height.addEventListener('input', apply)
}
