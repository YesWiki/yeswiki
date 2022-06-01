const usersTableService = {
    isRunning: false,
    createUser: function (elem){
        $(elem).tooltip('hide');
        let form = $(elem).closest('form');
        if (form.length > 0){
            let inputName = $(form).find('[name=name]');
            let name = inputName.length > 0 ? $(inputName).val() : "";
            let inputEmail = $(form).find('[name=email]');
            let email = inputEmail.length > 0 ? $(inputEmail).val() : "";
            $.ajax({
                method: 'post',
                url: wiki.url('api/users'),
                data: {
                    name: name,
                    email: email
                },
                success: function(data){
                    let userName = data.user.name;
                    let userEmail = data.user.email;
                    let signuptime = data.user.signuptime;
                    // append In Datable
                    let table = $(form).siblings('.dataTables_wrapper').first();
                    let tableId = $(table).prop('id').slice(0,-"_wrapper".length);
                    inputName.val("");
                    inputEmail.val("");
                    $(`#${tableId}`).DataTable().row.add( [
                        "",
                        userName,
                        "",
                        userEmail,
                        signuptime,
                        "",
                        ""
                    ] ).draw();
                    toastMessage(_t('USERSTABLE_USER_CREATED',{name:userName}),1100,'alert alert-success');
                },
                error: function(e){
                    toastMessage(_t('USERSTABLE_USER_NOT_CREATED',{name:name,error:e.responseJSON && e.responseJSON.error ? e.responseJSON.error : ''}),3000,'alert alert-danger');
                },
                complete: function (){
                    $(elem).removeAttr('disabled');
                    usersTableService.isRunning = false;
                }
            });
        }
    },
    deleteUser: function(event){
      event.preventDefault();
      if (!usersTableService.isRunning){
          usersTableService.isRunning = true;
        let elem = event.target;
        if (elem){
          $(elem).attr('disabled','disabled');
          $(elem).tooltip('hide');
          let name = $(elem).data('name');
          let csrfToken = $(elem).data('csrfToken');
          let targetNode = $(elem).data('targetNode');
          let modal = $(elem).data('modal');
          
          $.ajax({
            method: 'get',
            url: wiki.url(`api/users/${name}/delete`,{csrfToken:csrfToken}),
            timeout: 30000, // 30 seconds
            error: function (e) {
              multiDeleteService.addErrorMessage($(modal),
                _t('USERSTABLE_USER_NOT_DELETED',{username:name})+ ' : '
                +(e.responseJSON && e.responseJSON.error ? e.responseJSON.error : ''));
            },
            success: function(){
              multiDeleteService.removeLine($(targetNode).closest('.dataTables_wrapper').prop('id'),name);
              $(modal).find('.modal-body .multi-delete-results').first().append(
                $('<div>').text(_t('USERSTABLE_USER_DELETED',{username:name}))
              );
            },
            complete: function (){
              multiDeleteService.updateProgressBar($(modal),['test'],0);
              usersTableService.isRunning = false;
            }
          });
        }
      }
    },
}

$('form.form-inline button.create-user').on('click',function(event){
  event.preventDefault();
  if (!usersTableService.isRunning){
      usersTableService.isRunning = true;
    let elem = event.target;
    if (elem){
      $(elem).attr('disabled','disabled');
      usersTableService.createUser(elem);
    }
  }
});

$('#userTableDeleteModal.modal').on('shown.bs.modal',function(event){
  multiDeleteService.initProgressBar($(this));
  $(this).find('.modal-body .multi-delete-results').html('');
  let deleteButton = $(this).find('button.start-btn-delete-user')
  $(deleteButton).removeAttr('disabled');
  let button = $(event.relatedTarget) // Button that triggered the modal
  let name = $(button).data('name');
  let csrfToken = $(button).closest('tr').find(`td > label > input[data-itemId=${name}][data-csrfToken]`).first().data('csrftoken');
  $(this).find('#userNameToDelete').text(name);
  $(deleteButton).data('name',name);
  $(deleteButton).data('csrfToken',csrfToken);
  $(deleteButton).data('targetNode',button);
  $(deleteButton).data('modal',this);
  $(deleteButton).on('click',usersTableService.deleteUser);
});
