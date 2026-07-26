export function mapFieldsConf(callback) {
  return Object.fromEntries(
    Object.entries(window.formBuilderFields).map(([name, conf]) => [name, callback(conf)])
      .filter(([name, conf]) => !!conf)
  )
}

export function copyMultipleSelectValues(currentField) {
  const currentId = $(currentField).prop('id')
  // based on formBuilder/Helpers.js 'incrementId' function
  const split = currentId.lastIndexOf('-')
  const clonedFieldNumber = parseInt(currentId.substring(split + 1)) - 1
  const baseString = currentId.substring(0, split)
  const clonedId = `${baseString}-${clonedFieldNumber}`

  // find cloned field
  const clonedField = $(`#${clonedId}`)
  if (clonedField.length > 0) {
    // copy multiple select
    const clonedFieldSelects = $(clonedField).find('select[multiple=true]')
    clonedFieldSelects.each(function() {
      const currentSelect = $(currentField).find(`select[multiple=true][name=${$(this).prop('name')}]`)
      currentSelect.val($(this).val())
    })
  }
}

export function adjustDefaultAcls(field) {
  if (!field.hasOwnProperty('read')) {
    field.read = [' * ']// everyone by default
  }
  if (!field.hasOwnProperty('write')) {
    field.write = (field.type === 'champs_mail')
      ? [' % '] // owner and @admins by default for e-mail
      : [' * '] // everyone by default
  }
  if (field.type === 'acls' && !field.hasOwnProperty('comment')) {
    field.comment = ['comments-closed'] // comments-closed by default
  }
  if (field.type === 'champs_mail' && !('seeEmailAcls' in field)) {
    field.seeEmailAcls = [' % '] // owner and @admins by default
  }
}

export function addAdvancedAttributesSection($field) {
  if (!$field.attr('type')) return

  const advancedAttributes = window.formBuilderFields[$field.attr('type')].advancedAttributes || []
  if (advancedAttributes.length > 0) {
    advancedAttributes.forEach((attr) => {
      $field.find(`.form-elements .${attr}-wrap`).addClass('advanced')
    })
    const $button = $(`<button class="btn btn-info show-advanced-attributes-btn" type="button">
      ${_t('BAZ_FORM_ADVANCED_PARAMS')}
    </button>`)
    $button.on('click', () => $field.toggleClass('show-advanced-attributes'))
    $field.find('.form-elements').append($button)
  }
}

export function adjustJqueryBuilderUI($field) {
  // Change names
  $field.find('.form-group.name-wrap label').text(_t('BAZ_FORM_EDIT_UNIQUE_ID'))
  $field.find('.form-group.label-wrap label').text(_t('BAZ_FORM_EDIT_NAME'))
  // Changes icons and icons helpers
  $field.find('a[type=remove].formbuilder-icon-cancel')
    .removeClass('formbuilder-icon-cancel').addClass('btn-icon')
    .html('<i class="fa fa-trash"></i>')
  $field.find('a[type=copy].formbuilder-icon-copy').attr('title', _t('DUPLICATE'))
  $field.find('a[type=edit].formbuilder-icon-pencil').attr('title', _t('BAZ_FORM_EDIT_HIDE'))
}

export function convertToBytes(base64content) {
  const byteCharacters = base64content
  const sliceSize = 512
  let byteNumbers
  let slice
  const byteArrays = new [].constructor()
  for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
    slice = byteCharacters.slice(offset, offset + sliceSize)
    byteNumbers = new [].constructor(slice.length)
    for (let idx = 0; idx < slice.length; idx++) {
      byteNumbers[idx] = slice.charCodeAt(idx)
    }
    byteArrays.push(new Uint8Array(byteNumbers))
  }
  return byteArrays
}
