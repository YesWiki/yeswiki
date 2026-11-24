/** The "insert a field" menu: what each field can write into a template, and the writing itself. */

/** The Twig that reads one key of a template variable, whatever characters the key holds. */
export function twigAccess(variable, key) {
  return /^[a-zA-Z_]\w*$/.test(key)
    ? `${variable}.${key}`
    : `${variable}['${key.replace(/'/g, "\\'")}']`
}

const escapeHtml = (text) =>
  String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')

/** What an entry template can write for a field: it has the label, the rendered value and the stored one. */
export function twigInsertions({ name, key, stored }) {
  return [
    {
      label: _t('FORM_EDIT_ENTRY_TEMPLATE_FIELD_LABEL'),
      text: `{{ ${twigAccess('formFields', name)}.label }}`,
    },
    {
      label: _t('FORM_EDIT_ENTRY_TEMPLATE_FIELD_HTML'),
      text: `{{ ${twigAccess('html', key)}|raw }}`,
    },
    ...(stored
      ? [
          {
            label: _t('FORM_EDIT_ENTRY_TEMPLATE_FIELD_RAW'),
            text: `{{ ${twigAccess('entry', key)} }}`,
          },
        ]
      : []),
  ]
}

/** What a list template can write for a field: the label, the field as the entry shows it, and the stored value. */
export function listInsertions({ name, key, label, stored }) {
  return [
    {
      label: _t('FORM_EDIT_ENTRY_TEMPLATE_FIELD_LABEL'),
      text: escapeHtml(label),
    },
    {
      label: _t('FORM_EDIT_ENTRY_TEMPLATE_FIELD_HTML'),
      text: `{{ entryField(item.entry, '${String(name).replace(/'/g, "\\'")}') }}`,
    },
    ...(stored
      ? [
          {
            label: _t('FORM_EDIT_ENTRY_TEMPLATE_FIELD_RAW'),
            text: `{{ ${twigAccess('item.entry', key)} }}`,
          },
        ]
      : []),
  ]
}

/** Fill the dropdown with a row per field and a button per thing it can write. */
export function fillFieldMenu(menu, fields, insertionsOf, onPick) {
  menu.replaceChildren(
    ...fields.map((field) => {
      const row = document.createElement('li')
      row.className = 'yw-fb__template-field'
      const name = document.createElement('span')
      name.className = 'yw-fb__template-field-name'
      name.textContent = field.label || field.name
      row.append(name)
      insertionsOf(field).forEach((insertion) => {
        const button = document.createElement('button')
        button.type = 'button'
        button.className = 'yw-btn yw-btn--sm'
        button.textContent = insertion.label
        button.title = insertion.text
        button.addEventListener('click', () => onPick(insertion))
        row.append(button)
      })
      return row
    }),
  )
}
