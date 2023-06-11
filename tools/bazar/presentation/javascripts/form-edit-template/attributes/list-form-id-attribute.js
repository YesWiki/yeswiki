import { listAndFormUserValues } from '../fields/commons/attributes.js'
import openRemoteModal from '../../../../../../javascripts/helpers/remote-modal.js'

// formAndListIds is defined in forms_form.twig

export function initListOrFormIdAttribute() {
  // when selecting between data source lists or forms, we need to populate again the
  // listOfFormId select with the proper set of options
  $('.listeOrFormId-wrap:not(.initialized)').each(function() {
    const $attributeWrap = $(this)
    const $select = $attributeWrap.find('select[name=listeOrFormId]')
    const $sourceSelect = $attributeWrap.siblings('.subtype2-wrap').find('select')

    $attributeWrap.addClass('initialized')

    addCreateEditListButton($select, $sourceSelect)

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

function addCreateEditListButton($select, $sourceSelect) {
  const $editListButton = $(`<button type="button" class="btn btn-primary btn-icon edit-list-btn">
    <i class="fa fa-pen"></i>
  </button>`)
  $editListButton.on('click', () => {
    const url = wiki.url(`?BazaR/iframe&vue=listes&action=modif_liste&voirmenu=0&onsubmit=postmessage&idliste=${$select.val()}`)
    const modal = openRemoteModal(_t('LIST_UPDATE_TITLE'), url)
    window.onmessage = function(e) {
      if (e.data.msg === 'list_updated') {
        // update the options (list name might have changed)
        formAndListIds.lists[e.data.id] = e.data.title
        updateOptionsList($select, $sourceSelect)
        toastMessage(_t('LIST_UPDATED'), 3000, 'alert alert-success')
        modal.close()
      }
    }
  })

  const $createListButton = $(`<button type="button" class="btn btn-primary btn-icon add-list-btn">
    <i class="fa fa-plus"></i>
  </button>`)
  $createListButton.on('click', () => {
    const url = wiki.url('?BazaR/iframe&vue=listes&action=saisir_liste&voirmenu=0&onsubmit=postmessage')
    const modal = openRemoteModal(_t('LIST_CREATE_TITLE'), url)
    window.onmessage = function(e) {
      if (e.data.msg === 'list_created') {
        // update the options with the new List
        formAndListIds.lists[e.data.id] = e.data.title
        updateOptionsList($select, $sourceSelect)
        // select the newly created List
        $select.val(e.data.id)
        toastMessage(_t('LIST_CREATED'), 3000, 'alert alert-success')
        modal.close()
      }
    }
  })
  $select.closest('.input-wrap').append($editListButton).append($createListButton)
}
