function checkAllFirstCol(elem){
  let newState = $(elem).prop('checked');
  $(elem)
    .closest('.dataTables_wrapper')
    .find('tr > td:first-child input.selectpage[type=checkbox]:visible')
    .each(function (){
      $(this).prop('checked',newState);
      $(this).trigger("change");
    });
  
  $(elem)
    .closest('.dataTables_wrapper')
    .find('tr > th:first-child label.check-all-container input[type=checkbox]')
    .prop('checked',newState);
}

function multiDelete(baseElem){
  if (confirm(_t('DELETE_ALL_SELECTED_PAGES_QUESTION'))){
    
  }
}