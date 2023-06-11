export function addAdvancedAttributesSection() {
  $('#form-builder-container .form-field:not(.advanced-attributes-initialized)').each(function() {
    const $field = $(this)
    $field.addClass('advanced-attributes-initialized')
    const fieldName = $field.attr('type')
    const advancedAttributes = window.formBuilderFields[fieldName].advancedAttributes || []
    if (advancedAttributes.length > 0) {
      advancedAttributes.forEach((attr) => {
        $field.find(`.${attr}-wrap`).addClass('advanced')
      })
      const $button = $(`<button class="btn btn-info show-advanced-attributes-btn" type="button">
        ${_t('BAZ_FORM_ADVANCED_PARAMS')}
      </button>`)
      $button.on('click', () => $field.toggleClass('show-advanced-attributes'))
      $field.find('.form-elements').append($button)
    }
  })
}
