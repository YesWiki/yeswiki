( function($) {
$(document).ready(function(){

	// validation formulaire de contact
	$(".contact-submit").on("click", function(e) {
		e.stopPropagation();
		var form = $(this).parents('.ajax-mail-form');
		var inputsreq = form.find('input[required], textarea[required]');

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
		form.find('input[type=email]').each(function() {
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
			$('html, body').animate({scrollTop: form.find(".error:first").offset().top - 80}, 800);
		}
		else {
			// on soumet le formulaire
			var str = form.serialize();
			$.ajax({
				type: "POST",
				url: form.attr('action'),
				data: str,
				success: function(msg) {
					form.ajaxComplete(function(event, request, settings) {
						// Si le message a ete envoye, on affiche le message de notification et on cache le formulaire
						form.before(msg);
					});
				}
			});
		}

		return false;
	});
});
} ) ( jQuery );
