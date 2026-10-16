import { readConf, writeConf } from './commons/attributes.js'
import { openIconPicker, iconHtml, iconFromString } from '../../icon-picker.js'

const DEFAULT_REACTIONS = [
  ['top-gratitude', _t('BAZ_REACTIONS_DEFAULT_GRATITUDE')],
  ['j-aime', _t('BAZ_REACTIONS_DEFAULT_I_LOVE')],
  ['j-ai-appris', _t('BAZ_REACTIONS_DEFAULT_I_UNDERSTOOD')],
  ['pas-compris', _t('BAZ_REACTIONS_DEFAULT_NOT_UNDERSTOOD')],
  ['pas-d-accord', _t('BAZ_REACTIONS_DEFAULT_NOT_AGREE')],
  ['idee-noire', _t('BAZ_REACTIONS_DEFAULT_BLACK_IDEA')],
]

const ICON_SOURCES = ['sprite', 'emoji', 'reaction', 'file']

const splitCsv = (value) =>
  String(value ?? '') === ''
    ? []
    : String(value)
        .split(',')
        .map((part) => part.trim())

const slugify = (text) =>
  text
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

const esc = (text) => {
  const div = document.createElement('div')
  div.textContent = String(text ?? '')
  return div.innerHTML
}

/** Replace the three comma separated attributes with one row per reaction: icon, name and, on demand, id. */
function reactionsEditorSetup(api) {
  const anchorRow = api.getRow('labels')
  if (!anchorRow) return
  ;['labels', 'images', 'ids'].forEach((name) => api.hide(name))

  const labels = splitCsv(api.getValue('labels'))
  const images = splitCsv(api.getValue('images'))
  const ids = splitCsv(api.getValue('ids'))
  let rows = labels.map((label, index) => ({
    label,
    image: images[index] ?? '',
    id: ids[index] ?? '',
  }))
  let showIds = false

  const editor = document.createElement('div')
  editor.className = 'yw-fb__attribute yw-fb__reactions'
  anchorRow.before(editor)

  const save = () => {
    const kept = rows.filter((row) => row.label.trim() !== '')
    api.setValue('labels', kept.map((row) => row.label.trim()).join(','))
    api.setValue(
      'images',
      kept.some((row) => row.image)
        ? kept.map((row) => row.image).join(',')
        : '',
    )
    api.setValue(
      'ids',
      kept.some((row) => row.id.trim())
        ? kept.map((row) => row.id.trim() || slugify(row.label)).join(',')
        : '',
    )
  }

  const render = () => {
    editor.innerHTML = `
      <label class="yw-form-label">${esc(_t('BAZ_REACTIONS_FIELD_CHOICES_LABEL'))}</label>
      <ol class="yw-fb__reactions-list">
        ${rows
          .map(
            (row, index) => `
          <li class="yw-fb__reaction" data-index="${index}">
            <span class="yw-fb__reaction-drag" title="⇕">⠿</span>
            <button type="button" class="yw-input yw-icon-field__button" data-reaction-icon
                    title="${esc(_t('ICON_PICKER_TITLE'))}" aria-label="${esc(_t('ICON_PICKER_TITLE'))}">
              <span class="yw-icon-field__preview">${iconHtml(iconFromString(row.image))}</span>
            </button>
            <span class="yw-fb__reaction-texts">
              <input type="text" class="yw-input" data-reaction-label value="${esc(row.label)}"
                     placeholder="${esc(_t('BAZ_REACTIONS_FIELD_NAME_PLACEHOLDER'))}">
              ${showIds ? `<input type="text" class="yw-input yw-fb__reaction-id" data-reaction-id value="${esc(row.id)}" placeholder="${esc(slugify(row.label))}">` : ''}
            </span>
            <button type="button" class="yw-btn yw-btn--sm yw-btn--quiet" data-reaction-remove
                    title="${esc(_t('BAZ_REACTIONS_FIELD_REMOVE'))}" aria-label="${esc(_t('BAZ_REACTIONS_FIELD_REMOVE'))}">
              <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#x"/></svg>
            </button>
          </li>`,
          )
          .join('')}
      </ol>
      ${rows.length === 0 ? `<small class="yw-fb__description">${esc(_t('BAZ_REACTIONS_FIELD_EMPTY_HINT'))}</small>` : ''}
      <div class="yw-fb__reactions-actions">
        <button type="button" class="yw-btn yw-btn--sm" data-reaction-add>
          <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#plus"/></svg>
          ${esc(_t('BAZ_REACTIONS_FIELD_ADD'))}
        </button>
        ${rows.length === 0 ? `<button type="button" class="yw-btn yw-btn--sm yw-btn--quiet" data-reaction-defaults>${esc(_t('BAZ_REACTIONS_FIELD_USE_DEFAULTS'))}</button>` : ''}
      </div>
      ${
        rows.length > 0
          ? `<label class="yw-fb__check"><input type="checkbox" data-reaction-show-ids${showIds ? ' checked' : ''}> ${esc(_t('BAZ_REACTIONS_FIELD_IDS_LABEL'))}</label>
             ${showIds ? `<small class="yw-fb__description">${esc(_t('BAZ_REACTIONS_FIELD_IDS_HINT'))}</small>` : ''}`
          : ''
      }`
    if (window.Sortable) {
      window.Sortable.create(editor.querySelector('.yw-fb__reactions-list'), {
        handle: '.yw-fb__reaction-drag',
        animation: 150,
        onUpdate(event) {
          const [moved] = rows.splice(event.oldIndex, 1)
          rows.splice(event.newIndex, 0, moved)
          save()
          render()
        },
      })
    }
  }

  const rowOf = (target) =>
    rows[Number(target.closest('[data-index]')?.dataset.index)]

  editor.addEventListener('input', (event) => {
    const row = rowOf(event.target)
    if (!row) return
    if (event.target.matches('[data-reaction-label]')) {
      event.target.value = event.target.value.replace(/,/g, '')
      row.label = event.target.value
      const id = event.target.parentNode.querySelector('[data-reaction-id]')
      if (id) id.placeholder = slugify(row.label)
    }
    if (event.target.matches('[data-reaction-id]')) {
      event.target.value = slugify(event.target.value)
      row.id = event.target.value
    }
    save()
  })

  editor.addEventListener('change', (event) => {
    if (!event.target.matches('[data-reaction-show-ids]')) return
    showIds = event.target.checked
    render()
  })

  editor.addEventListener('click', (event) => {
    if (event.target.closest('[data-reaction-add]')) {
      rows.push({ label: '', image: '', id: '' })
      render()
      editor
        .querySelectorAll('[data-reaction-label]')
        .item(rows.length - 1)
        ?.focus()
      return
    }
    if (event.target.closest('[data-reaction-defaults]')) {
      rows = DEFAULT_REACTIONS.map(([id, label]) => ({ label, image: id, id }))
      save()
      render()
      return
    }
    const iconButton = event.target.closest('[data-reaction-icon]')
    if (iconButton) {
      const row = rowOf(iconButton)
      openIconPicker({
        anchor: iconButton,
        value: iconFromString(row.image),
        sources: ICON_SOURCES,
        onPick: ({ value }) => {
          row.image = value
          iconButton.querySelector('.yw-icon-field__preview').innerHTML =
            iconHtml(iconFromString(value))
          save()
        },
      })
      return
    }
    if (event.target.closest('[data-reaction-remove]')) {
      rows.splice(rows.indexOf(rowOf(event.target)), 1)
      save()
      render()
    }
  })

  render()
}

export default {
  field: {
    label: _t('BAZ_REACTIONS_FIELD'),
    name: 'reactions',
    attrs: { type: 'reactions' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#thumb-up"/></svg>',
  },
  attributes: {
    label_reaction: {
      label: _t('BAZ_REACTIONS_FIELD_ACTIVATE_LABEL'),
      value: '',
      placeholder: _t('BAZ_ACTIVATE_REACTIONS'),
      description: _t('BAZ_REACTIONS_FIELD_ACTIVATE_HINT'),
    },
    default: {
      label: _t('BAZ_REACTIONS_FIELD_DEFAULT_ACTIVATION_LABEL'),
      options: { oui: _t('YES'), non: _t('NO') },
    },
    labels: { value: '' },
    images: { value: '' },
    ids: { value: '' },
    read_access: readConf,
    write_access: writeConf,
  },
  disabledAttributes: ['label', 'required'],
  editorSetup: reactionsEditorSetup,
}
