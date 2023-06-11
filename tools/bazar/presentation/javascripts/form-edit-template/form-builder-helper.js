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