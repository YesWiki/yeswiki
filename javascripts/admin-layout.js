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

/** The rows of one list, in document order. */
function rowsOf(list) {
  return [...list.querySelectorAll('[data-yw-layout-row]')]
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
}

function isChild(row) {
  return row.querySelector('[data-yw-layout-child]')?.value === '1'
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
      const step = Number(move.dataset.ywLayoutMove)
      const up = step < 0
      const sibling = up ? row.previousElementSibling : row.nextElementSibling
      if (sibling) {
        // insertBefore of the row *after* the sibling is the same move in both directions
        list.insertBefore(up ? row : sibling, up ? sibling : row)
        refresh(list)
      }
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
