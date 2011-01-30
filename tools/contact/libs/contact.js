$(document).ready(function(){
	$("#ajax-contact-form, #ajax-abonne-form, #ajax-desabonne-form, #ajax-mail-form").live("submit", function() {
		$(this).addClass('form-selected').prev(".note").addClass('note-selected');
		var str = $(this).serialize();
		$.ajax({
			type: "POST",
			url: $(this).attr('action'),
			data: str,
			success: function(msg) {
				$(".note-selected").ajaxComplete(function(event, request, settings) {
					// Si le message a été envoyé, on affiche le message de notification et on cache le formulaire
					if(msg == 'OK') {
						result = '<div class="info_box">Votre message a bien &eacute;t&eacute; envoy&eacute;. Merci!</div>';
						$(".form-selected").hide().removeClass("form-selected");
					} else if(msg == 'abonne') {
						result = '<div class="info_box">Votre abonnement a bien &eacute;t&eacute; pris en compte. Merci!</div>';
						$(".form-selected").hide().removeClass("form-selected");
					} else if(msg == 'desabonne') {
						result = '<div class="info_box">Votre d&eacute;sabonnement a bien &eacute;t&eacute; pris en compte. Merci, &agrave; bient&ocirc;t!</div>';
						$(".form-selected").hide().removeClass("form-selected");
					} else {
						result = msg;
						$(".form-selected").show().removeClass("form-selected");
					}
					$(this).html(result);
				}).removeClass("note-selected");
			}
		});
		return false;
	});
});