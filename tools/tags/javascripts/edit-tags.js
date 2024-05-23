document.addEventListener('DOMContentLoaded', () => {
  const inputTags = $('#ACEditor .yeswiki-input-pagetag')
  const existingTagsInternal = (typeof existingTags === 'undefined' || !Array.isArray(existingTags))
    ? []
    : existingTags
  inputTags.tagsinput({
    typeahead: {
      afterSelect(val) { inputTags.tagsinput('input').val('') },
      source: existingTagsInternal,
      autoSelect: false
    },
    trimValue: true,
    confirmKeys: [13, 186, 188]
  })

  // bidouille antispam
  $('.antispam').attr('value', '1')
})
