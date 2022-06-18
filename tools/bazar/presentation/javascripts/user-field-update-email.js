function updateEmailForUser(){
    $('[name=nomwiki]').each(function(){
        let emailField = $(this).data("fieldnametoupdate");
        let userEmail = $(this).data("useremail");
        if (emailField.length > 0 && userEmail.length > 0 ){
            let emailInput = $(this).closest("form#formulaire").find(`input#${emailField}`);
            if (emailInput.length > 0 && (emailInput.val().length == 0 || emailInput.val() == false)){
                emailInput.val(userEmail);
                emailInput.trigger("change");
            }
        }
    });
}

updateEmailForUser();
$(document).on("yw-modal-open",updateEmailForUser);