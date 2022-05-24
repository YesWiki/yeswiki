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
  isRunning: false,
  refreshOnModalClosing : {},
  modalClosing: function (modalContainer){
    let id = $(modalContainer).prop('id');
    if (multiDeleteService.refreshOnModalClosing.hasOwnProperty(id) 
      && multiDeleteService.refreshOnModalClosing[id] === true){
      window.location.reload();
    }
  },
  initProgressBar: function (modal){
    multiDeleteService.updateProgressBar(modal,['test'],-1);
  },
  updateProgressBar: function (modal,items,currentIndex){
    let value = (items.length == 0) ? 100 : Math.min(100,Math.round((currentIndex+1)/items.length*100));
    $(modal).find('.modal-footer .progress-bar').each(function(){
        $(this).attr('style',`width: ${value}%;`);
        $(this).attr('aria-valuenow',value);
      });
  },
  modalIsClosed: function (modal){
    return ($(modal).filter(':visible').length == 0);
  },
  addErrorMessage: function (modal,message){
    $(modal).find('.modal-body .multi-delete-results').first().append(
      $('<div>').addClass('alert alert-danger')
        .text(message)
    );
  },
  removeLine: function (target,itemId){
    let table = $(`#${target} .dataTable[id]`);
    if (table.length == 0){
      return false;
    }
    table.DataTable().row($(`#${target} [data-itemid="${itemId}"]`).parents('tr')).remove().draw();
    return true;
  },
  deleteNextItem: function (modal,items,type,currentIndex,target){
    multiDeleteService.updateProgressBar(modal,items,currentIndex);
    if ((currentIndex +1)< items.length ){
      multiDeleteService.deleteOneItem(modal,items,type,currentIndex +1,target);
    } else {
      multiDeleteService.isRunning = false;
      $(modal).find('.modal-body .multi-delete-results').first().append(
        $('<div>').text(_t('MULTIDELETE_END'))
      );
    }
  },
  deleteOneItem: function (modal,items,type,currentIndex,target){
    if (['pages'].indexOf(type) == -1){
      multiDeleteService.addErrorMessage(modal,"Unknown type ! Should be 'pages' !");
      return;
    }
    let item = items[currentIndex] ?? {};
    let itemId = (item.id != undefined) ? item.id : '';
    let csrfToken = (item.token != undefined) ? item.token : '';
    if (itemId.length == 0 || csrfToken.length == 0){
      multiDeleteService.deleteNextItem(modal,items,type,currentIndex,target);
      return ;
    }
    $.ajax({
      type: 'GET',
      url: wiki.url(`?api/${type}/${itemId}/delete`,{csrfToken:csrfToken}),
      timeout: 30000, // 30 seconds
      error: function (xhr,status,error){
        multiDeleteService.addErrorMessage(modal,
          _t('MULTIDELETE_ERROR')
          .replace('{itemId}',itemId)
          .replace('{error}',error));
          // if error force reload
          multiDeleteService.refreshOnModalClosing[$(modal).parent().prop('id')] = true;
      },
      success: function(){
        if (!multiDeleteService.removeLine(target,itemId)){
          multiDeleteService.refreshOnModalClosing[$(modal).parent().prop('id')] = true;
        }
      },
      complete: function (){
        setTimeout(function(){multiDeleteService.deleteNextItem(modal,items,type,currentIndex,target);},0);
      },
    });
  },
  deleteItems: function (elem){
    let target = $(elem).data('target');
    let type = $(elem).data('type');
    // get selected item
    if (target.length > 0){
      let inputs = $(`#${target}`).find('tr > td:first-child input.selectline[type=checkbox]:visible:checked');
      let modal = $(elem).closest('.modal-dialog');
      
      let items = [];
      for (let index = 0; index < inputs.length; index++) {
        let itemId = $(inputs[index]).data('itemid');
        let csrfToken = $(inputs[index]).data('csrftoken');
        if (itemId.length > 0 && csrfToken.length > 0){
          items.push({id:itemId,token:csrfToken});
        }
      }
      if (items.length > 0){
        setTimeout(function(){multiDeleteService.deleteOneItem(modal,items,type,0,target);},0);
      }
    }
  },
  updateNbSelected: function (modalId){
    let button = $(`#${modalId} .modal-body > button.start-btn-delete-all`);
    let text = $(`#${modalId} .modal-body > .alert.alert-info > span.nb-elem-selected`);
    let target = $(button).data('target');
    if (target.length > 0){
      let inputs = $(`#${target}`).find('tr > td:first-child input.selectline[type=checkbox]:visible:checked');
      $(text).html(inputs.length);
    } else {
      $(text).html('error');
    }
  }
};

$('button.start-btn-delete-all').on('click',function(){
  if (!multiDeleteService.isRunning){
    multiDeleteService.isRunning = true;
    let elem = event.target;
    if (elem){
      $(elem).attr('disabled','disabled');
      multiDeleteService.deleteItems(elem);
    }
  }
});

$(".modal.multidelete").on('shown.bs.modal',function(){
  multiDeleteService.initProgressBar($(this));
  $(this).find('.modal-body .multi-delete-results').html('');
  $(this).find('button.start-btn-delete-all').removeAttr('disabled');
});

$(".modal.multidelete").on('hidden.bs.modal',function(){
  multiDeleteService.modalClosing($(this));
});