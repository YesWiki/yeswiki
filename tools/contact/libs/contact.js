$(document).ready(function(){

	// validation formulaire de contact
	$(".contact-submit").on("click", function() {
		var inputsreq = $('#ajax-contact-form input[required], #ajax-contact-form textarea[required]');

		var atleastonefieldnotvalid = false;
		var atleastonemailfieldnotvalid = false;
		
		// on efface les anciennes erreurs
		$('span.help-inline').remove();

		// il y a des champs requis, on teste la validite champs par champs
		if (inputsreq.length > 0) {		
			
			inputsreq.each(function() {
				if ( !($(this).val().length === 0 || $(this).val() === '' || $(this).val() === '0')) {
					$(this).parents('.control-group').removeClass('error');
				} else {
					atleastonefieldnotvalid = true;
					$(this).parents('.control-group').addClass('error');
					$('<span>').addClass('help-inline').text('La saisie de ce champ est obligatoire.').appendTo($(this).parent());
				}
			});
		}
		
		// les emails
		$('#ajax-contact-form input[type=email]').each(function() {
			var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
			var address = $(this).val();
			if(reg.test(address) == false && !(address === '' &&  $(this).attr('required') !== 'required')) {
				atleastonemailfieldnotvalid = true;
				$(this).parents('.control-group').addClass('error');
				$('<span>').addClass('help-inline').text('L\'email saisi n\'est pas valide.').appendTo($(this).parent());		
			} else {
				$(this).parents('.control-group').removeClass('error');
			}
		});

		if (atleastonefieldnotvalid === true || atleastonemailfieldnotvalid === true) {
			// on remonte en haut du formulaire
			$('html, body').animate({scrollTop: $("#ajax-contact-form .error:first").offset().top - 80}, 800);
		}
		else {
			// on soumet le formulaire
			$("#ajax-contact-form").submit();
		}

		return false;
	});



	// envoi ajax et post controle
	$("#ajax-contact-form, #ajax-abonne-form, #ajax-desabonne-form, #ajax-mail-form").on("submit", function() {
		var $this = $(this);
		$this.addClass('form-selected');
		var str = $this.serialize();
		$.ajax({
			type: "POST",
			url: $this.attr('action'),
			data: str,
			success: function(msg) {
				$this.ajaxComplete(function(event, request, settings) {
					// Si le message a été envoyé, on affiche le message de notification et on cache le formulaire
					if(msg == 'OK') {
						result = '<div class="alert alert-success">Votre message a bien &eacute;t&eacute; envoy&eacute;. Merci!</div>';
					} else if(msg == 'abonne') {
						result = '<div class="alert alert-success">Votre abonnement a bien &eacute;t&eacute; pris en compte. Merci!</div>';
					} else if(msg == 'desabonne') {
						result = '<div class="alert alert-success">Votre d&eacute;sabonnement a bien &eacute;t&eacute; pris en compte. Merci, &agrave; bient&ocirc;t!</div>';
					} else {
						result = msg;
					}
					$this.removeClass("form-selected").before(result);
				});
			}
		});
		return false;
	});
});