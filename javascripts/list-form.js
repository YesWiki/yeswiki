/* The value list editor: the server draws the tree, this adds, removes, folds and moves its values, and writes them as JSON when the form is sent. */
import { openIconPicker, iconHtml } from './icon-picker.js'
import { canPickFromScreen, pickInto } from './eyedropper.js'

const slugify = (text) =>
  text
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-zA-Z_0-9{}]/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_+|_+$/g, '')
    .toLowerCase()

const own = (node, selector) =>
  [...node.querySelectorAll(selector)].filter(
    (element) => element.closest('[data-list-node]') === node,
  )
const ownOne = (node, selector) => own(node, selector)[0]
const childrenOf = (container) =>
  [...container.children].filter((child) => child.matches('[data-list-node]'))

function nodeData(node) {
  const color = ownOne(node, '[data-list-color]')
  const icon = JSON.parse(ownOne(node, '[data-list-icon]').dataset.icon || '{}')
  return {
    ...JSON.parse(node.dataset.extra || '{}'),
    id: ownOne(node, '[data-list-id]').value.trim(),
    label: ownOne(node, '[data-list-label]').value,
    color: color.dataset.set === '1' ? color.value : '',
    icon: { source: icon.source || 'sprite', value: icon.value || '' },
    children: childrenOf(ownOne(node, '[data-list-nodes]')).map(nodeData),
  }
}

function makeSortable(container) {
  window.Sortable?.create(container, {
    group: 'list-nodes',
    animation: 150,
    fallbackOnBody: true,
    swapThreshold: 0.65,
    filter: 'input, select, button',
    preventOnFilter: false,
    onEnd: () =>
      container.dispatchEvent(new Event('change', { bubbles: true })),
  })
}

function unfold(node, open) {
  const children = ownOne(node, '[data-list-children]')
  const toggle = ownOne(node, '[data-list-toggle]')
  children.hidden = !open
  toggle.setAttribute('aria-expanded', String(open))
  toggle.querySelector('use')?.setAttribute(
    'href',
    toggle
      .querySelector('use')
      .getAttribute('href')
      .replace(/#chevron-(up|down)$/, open ? '#chevron-up' : '#chevron-down'),
  )
}

function addValue(form, adder) {
  const input = adder.querySelector('[data-list-new]')
  const node = form
    .querySelector('template[data-list-node-template]')
    .content.firstElementChild.cloneNode(true)
  ownOne(node, '[data-list-label]').value = input.value
  ownOne(node, '[data-list-id]').value = slugify(input.value)
  ownOne(node, '[data-list-id]').classList.toggle('error', input.value === '')
  adder.parentElement.querySelector('[data-list-nodes]').append(node)
  input.value = ''
  const parent = adder.closest('[data-list-node]')
  if (parent) unfold(parent, true)
  form.dispatchEvent(new Event('change', { bubbles: true }))
}

ywInitEach('[data-list-nodes]', makeSortable)

ywInitEach('[data-list-form]', (form) => {
  const picking = canPickFromScreen()
  const showPickers = (root) =>
    root.querySelectorAll('[data-list-color-pick]').forEach((button) => {
      button.hidden = !picking
    })
  showPickers(form)
  new MutationObserver(() => showPickers(form)).observe(form, {
    childList: true,
    subtree: true,
  })

  form.addEventListener('click', (event) => {
    const button = event.target.closest('button')
    if (!button || !form.contains(button)) return
    const node = button.closest('[data-list-node]')
    if (button.matches('[data-list-add]')) {
      addValue(form, button.closest('.list-new-node'))
    } else if (button.matches('[data-list-toggle]')) {
      unfold(node, ownOne(node, '[data-list-children]').hidden)
    } else if (button.matches('[data-list-delete]')) {
      node.remove()
      form.dispatchEvent(new Event('change', { bubbles: true }))
    } else if (button.matches('[data-list-color-clear]')) {
      const color = ownOne(node, '[data-list-color]')
      color.dataset.set = '0'
      color.classList.add('input-color--empty')
      button.hidden = true
      form.dispatchEvent(new Event('change', { bubbles: true }))
    } else if (button.matches('[data-list-color-pick]')) {
      pickInto(ownOne(node, '[data-list-color]'))
    } else if (button.matches('[data-list-icon]')) {
      openIconPicker({
        anchor: button,
        value: JSON.parse(button.dataset.icon || '{}'),
        onPick: (icon) => {
          button.dataset.icon = JSON.stringify(icon)
          button.querySelector('.yw-icon-field__preview').innerHTML =
            iconHtml(icon)
          form.dispatchEvent(new Event('change', { bubbles: true }))
        },
      })
    }
  })

  form.addEventListener('input', (event) => {
    const field = event.target
    if (field.matches('[data-list-color]')) {
      field.dataset.set = '1'
      field.classList.remove('input-color--empty')
      ownOne(
        field.closest('[data-list-node]'),
        '[data-list-color-clear]',
      ).hidden = false
    } else if (field.matches('[data-list-id]')) {
      field.classList.toggle('error', field.value.trim() === '')
    }
  })

  form.addEventListener('keydown', (event) => {
    if (
      event.key !== 'Enter' ||
      !event.target.matches('[data-list-node] input, [data-list-new]')
    )
      return
    event.preventDefault()
    if (event.target.matches('[data-list-new]')) {
      addValue(form, event.target.closest('.list-new-node'))
    }
  })

  form.addEventListener('submit', (event) => {
    const nodes = childrenOf(form.querySelector('[data-list-root]')).map(
      nodeData,
    )
    const ids = []
    const collect = (list) =>
      list.forEach((node) => {
        ids.push(node.id)
        collect(node.children)
      })
    collect(nodes)
    const duplicates = [
      ...new Set(ids.filter((id, index) => id && ids.indexOf(id) !== index)),
    ]
    if (ids.some((id) => !id)) {
      event.preventDefault()
      toastMessage(form.dataset.missingIds, 4000, 'alert alert-danger')
    } else if (duplicates.length > 0) {
      event.preventDefault()
      toastMessage(
        `${form.dataset.duplicateIds}${duplicates.join(', ')}`,
        8000,
        'alert alert-danger',
      )
    }
    form.querySelector('[data-list-json]').value = JSON.stringify(nodes)
  })
})
