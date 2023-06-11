
import { listAndFormUserValues } from './fields/commons/attributes.js'

// formAndListIds is defined in forms_form.twig

export function initLitsOrFormIdAttribute() {
  // when selecting between data source lists or forms, we need to populate again the
  // listOfFormId select with the proper set of options
  $('.listeOrFormId-wrap:not(.initialized)').each(function() {
    const $attributeWrap = $(this)
    const $select = $attributeWrap.find('select[name=listeOrFormId]')
    const $sourceSelect = $attributeWrap.siblings('.subtype2-wrap').find('select')

    $attributeWrap.addClass('initialized')

    addCreateEditListButton($select)

    $sourceSelect.on('change', () => {
      $attributeWrap.attr('data-source', $sourceSelect.val())
      updateOptionsList($select, $sourceSelect)
      toggleEditListButtonVisbility($attributeWrap, $select)
    }).trigger('change')

    $select.on('change', () => {
      toggleEditListButtonVisbility($attributeWrap, $select)
    }).trigger('change')
  })
}

function updateOptionsList($select, $sourceSelect) {
  const currentValue = $select.val()
  $select.empty()
  $select.append(new Option('', '', false))
  const optionToAddToSelect = { ...formAndListIds[`${$sourceSelect.val()}s`], ...listAndFormUserValues }

  Object.entries(optionToAddToSelect).forEach(([key, label]) => {
    const newOption = new Option(label, key, false, key == currentValue)
    $select.append(newOption)
  })
}

function toggleEditListButtonVisbility($attributeWrap, $select) {
  const $btn = $attributeWrap.find('.edit-list-btn')
  $select.val() ? $btn.show() : $btn.hide()
}

function addCreateEditListButton($select) {
  const $editListButton = $(`<button type="button" class="btn btn-primary btn-icon edit-list-btn">
    <i class="fa fa-pen"></i>
  </button>`)
  $editListButton.on('click', () => {
    console.log("Edit list", $select.val())
  })

  const $createListButton = $(`<button type="button" class="btn btn-primary btn-icon add-list-btn">
    <i class="fa fa-plus"></i>
  </button>`)
  $createListButton.on('click', () => {
    console.log("Create new list")
  })
  $select.closest('.input-wrap').append($editListButton).append($createListButton)
}
