$(document).ready(function() {
  // validation formulaire de contact
  $('body').on('click', '.mail-submit', function(e) {
    e.stopPropagation();
    var form = $(this).parents('.ajax-mail-form');
    var inputsreq = form.find('input[required], textarea[required]');

    var atleastonefieldnotvalid = false;
    var atleastonemailfieldnotvalid = false;

    // on efface les anciennes erreurs
    form.find('.help-block').remove();

    // il y a des champs requis, on teste la validite champs par champs
    if (inputsreq.length > 0) {
      inputsreq.each(function() {
        if (!($(this).val().length === 0 || $(this).val() === '' || $(this).val() === '0')) {
          $(this).parents('.form-group').removeClass('has-error');
        } else {
          atleastonefieldnotvalid = true;
          $(this).parents('.form-group').addClass('has-error');
          $('<span>').addClass('help-block').text('La saisie de ce champ est obligatoire.').appendTo($(this).parents('.form-group'));
        }
      });
    }

    // les emails
    form.find('input[type=email]').each(function() {
      var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,10})$/;
      var address = $(this).val();
      if (reg.test(address) == false && !(address === '' && $(this).attr('required') !== 'required')) {
        atleastonemailfieldnotvalid = true;
        $(this).parents('.form-group').addClass('has-error');
        $('<span>').addClass('help-block').text('L\'email saisi n\'est pas valide.').appendTo($(this).parents('.form-group'));
      } else {
        $(this).parents('.form-group').removeClass('has-error');
      }
    });

    if (atleastonefieldnotvalid === true || atleastonemailfieldnotvalid === true) {
      // on remonte en haut du formulaire
      $('html, body').animate({
        scrollTop: form.find('.has-error:first').offset().top - 80,
      }, 800);
    } else {
      // on soumet le formulaire
      var str = form.serialize();
      $.ajax({
        type: 'POST',
        url: form.attr('action'),
        data: str,
        success: function(msg) {
          // si le message a ete envoye, on affiche le message de notification
          form.find('.alert').remove();
          form.prepend(msg);
          msg = '';

          //  on vide le formulaire si succes
          if (form.find('.alert-success').length > 0) {
            form[0].reset();
          }
        },
      });
    }

    return false;
  });
});
