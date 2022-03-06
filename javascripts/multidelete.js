function checkAllFirstCol(elem){
  let newState = $(elem).prop('checked');
  $(elem)
    .closest('.dataTables_wrapper')
    .find('tr > td:first-child input.selectline[type=checkbox]:visible')
    .each(function (){
      $(this).prop('checked',newState);
      $(this).trigger("change");
    });
  
  $(elem)
    .closest('.dataTables_wrapper')
    .find('tr > th:first-child label.check-all-container input[type=checkbox]')
    .prop('checked',newState);
}

const multiDeleteService = {
  updateProgressBar: function (modal,items,currentIndex){
    let value = (items.length == 0) ? 100 : (currentIndex+1)/items.length;
    $(modal).find('.modal-footer .progress-bar').each(function(){
        $(this).attr('style',`width: ${value}%;`);
        $(this).attr('aria-valuenow',value);
      });
  },
  deleteNextItem: function (modal,items,type,currentIndex){
    multiDeleteService.updateProgressBar(modal,items,currentIndex);
    if (currentIndex < items.length){
      multiDeleteService.deleteOneItem(modal,items,type,currentIndex +1);
    } else {
      $(modal).find('.modal-body').append(
        $('<div>').text(_t('MULTIDELETE_END'))
      );
    }
  },
  deleteOneItem: function (modal,items,type,currentIndex){
    let tag = items[currentIndex] ?? '';
    let sanitizedType = 'pages';
    if (tag.length == 0){
      multiDeleteService.deleteNextItem(modal,items,sanitizedType,currentIndex);
    }
    $.ajax({
      type: 'DELETE',
      url: wiki.url(`?api/${sanitizedType}/${tag}`),
      timeout: 30000, // 30 seconds
      error: function (xhr,status,error){
        $(modal).find('.modal-body').append(
          $('<div>').addClass('alert alert-danger')
            .text(_t('MULTIDELETE_ERROR').replace('{tag}',tag))
        );
      },
      complete: function (){
        multiDeleteService.deleteNextItem(modal,items,sanitizedType,currentIndex);
      },
    });
  },
  deleteItems: function (elem){
    let target = $(elem).data('target');
    let type = $(elem).data('type');
    // get selected item
    if (target.length > 0){
      let inputs = $(`#${target}`).find('tr > td:first-child input.selectline[type=checkbox]:visible');
      let modal = $(elem).closest('.modal-dialog');
      
      let items = [];
      for (let index = 0; index < inputs.length; index++) {
        let itemId = $(inputs[index]).data('itemid');
        if (itemId.length > 0){
          items.push(itemId);
        }
      }
      if (items.length > 0){
        multiDeleteService.deleteOneItem(modal,items,type,0);
      }
    }
  }
};

$('button.start-btn-delete-all').on('click',function(){
  let elem = event.target;
  if (elem){
    multiDeleteService.deleteItems(elem);
  }
});