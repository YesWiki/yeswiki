
import { listAndFormUserValues } from './fields/commons/attributes.js'

// formAndListIds is defined in forms_form.twig

export function initLitsOrFormIdAttribute() {
  // when selecting between data source lists or forms, we need to populate again the
  // listOfFormId select with the proper set of options
  $('.radio-group-field, .checkbox-group-field, .select-field')
    .find('select[name=subtype2]:not(.list-form-attr-initialized)')
    .on('change', function() {
      $(this).addClass('list-form-attr-initialized')
      const $select = $(this).closest('.form-field').find('select[name=listeOrFormId]')
      const currentValue = $select.val()
      $select.empty()
      $select.append(new Option('', '', false))
      const optionToAddToSelect = { ...formAndListIds[`${$(this).val()}s`], ...listAndFormUserValues }
      Object.entries(optionToAddToSelect).forEach(([key, label]) => {
        const newOption = new Option(label, key, false, key == currentValue)
        $select.append(newOption)
      })
    })
    .trigger('change')
}
