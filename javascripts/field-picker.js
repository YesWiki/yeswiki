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

/** What a list template can write for a field: Vue reads stored values, and only long text may be read as HTML. */
export function vueInsertions({ key, label, html }) {
  const path = twigAccess('entry', key)
  const quoted = path.replace(/"/g, '&quot;')
  return [
    {
      label: _t('FORM_EDIT_ENTRY_TEMPLATE_FIELD_LABEL'),
      text: escapeHtml(label),
    },
    ...(html
      ? [
          {
            label: _t('FORM_EDIT_ENTRY_TEMPLATE_FIELD_HTML'),
            text: `<span v-html="${quoted}"></span>`,
            needs: key,
          },
        ]
      : []),
    {
      label: _t('FORM_EDIT_ENTRY_TEMPLATE_FIELD_RAW'),
      text: `<span v-text="${quoted}"></span>`,
      needs: key,
    },
  ]
}

/** The source with $name in its necessary_fields, or null when that list is not a literal this can rewrite. */
export function withNecessaryField(source, name) {
  const literal =
    /(\{%-?\s*set\s+necessary_fields\s*=\s*\[)([^\]]*)(\]\s*-?%\})/.exec(source)
  if (literal) {
    const listed = literal[2].match(/[\w-]+/g) || []
    if (listed.includes(name)) return source
    const items = literal[2].trim()
    const list = items ? `${items}, '${name}'` : `'${name}'`
    return source.replace(literal[0], `${literal[1]}${list}${literal[3]}`)
  }
  if (source.includes('necessary_fields')) return null
  const parent = /\{%-?\s*extends\s[^%]*%\}[^\n]*\n/.exec(source)
  if (!parent) return null
  const at = parent.index + parent[0].length
  return `${source.slice(0, at)}\n{% set necessary_fields = ['${name}'] %}\n${source.slice(at)}`
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
