$(document).ready(function() {
	// nuage de mots cles : tooltip
	$(".tooltip_link").tooltip({tipClass:'tooltip', position: "top center", relative: true});

	// ajax envoi de nouveaux commentaires
	$(document).on("click", ".save-comment", function(){ 
		var textcommentaire = $(this).prevAll(".comment-response").val();	
		var urlpost = $(this).parent("form").attr("action").replace('/addcomment','/ajaxaddcomment'+'&jsonp_callback=?'); 		
		$(this).parents(".form-respond-comment").attr("id",'comments');	
		
		$.ajax({
			type: "POST",
			url: urlpost,
			data: { body: textcommentaire, initialpage: $('#initialpage').attr('value'), antispam : "1" },
			dataType: "jsonp",		
			success: function(data){
				$("#comments").before(data.html).removeAttr("id");
				$(".form-respond-comment").remove();
			}
		 });

		return false;
	});
	
	// ajax repondre a un commentaire
	$(document).on("click", ".answer-comment", function(){ 			
		// on cache les formulaires deja ouverts et on reaffiche le contenu
		$(".form-modify-comment, .form-respond-comment").remove();
		$("#comments").show().removeAttr("id");	
		   
		$(this).parents(".yeswiki-comment").next(".comment-replies").append("<form class=\"form-respond-comment well well-small\" action=\""+$(this).attr("href")+"/addcomment\" method=\"post\">" +
			"<input name=\"wiki\" value=\""+$(this).parents(".yeswiki-comment").prev("a").attr("name")+"/addcomment\" type=\"hidden\">" +
			"<textarea name=\"body\" required=\"required\" class=\"comment-response\" rows=\"3\" cols=\"20\" placeholder=\"Ecrire votre commentaire ici...\"></textarea>" +
			"<input class=\"btn btn-small btn-primary save-comment\" value=\"Enregistrer\" type=\"button\">" +
			"<input class=\"btn btn-small cancel-comment\" type=\"button\" value=\"Annuler\" /></form>");
		$(this).parents(".yeswiki-comment").next(".comment-replies").find(".comment-response").focus();											
		
		return false;		      
	});
	 
	// ajax edition commentaire			
	$(document).on("click", ".edit-comment", function(){ 	
		// on cache les formulaires deja ouverts et on reaffiche le contenu
		$(".form-modify-comment, .form-respond-comment").remove();
		$("#comments").show().removeAttr("id");	
		
		// on attribut un id au div selectionne, afin de le retrouver
		$(this).parents(".yeswiki-comment").attr("id",'comments');		
		
		var urlpost = $(this).attr("href").replace('edit','ajaxedit')+'&jsonp_callback=?';					   
	   	$.getJSON(urlpost, {"commentaire" : "1"}, function(data) {
	   		if (data.nochange=='1') {
		     	$("#comments").show();
		    } else {
				// on affiche le contenu ajax
			    $("#comments").after(data.html).hide();
			    $('.comment-response').focus();
		    }
		});										

		return false;		      
	});
	
	// annulation edition commentaire
	$(document).on("click", ".cancel-comment, .btn-cancel-modify", function(){ 	
		$(".form-modify-comment, .form-respond-comment").remove();
		$("#comments").show().removeAttr("id");		      
	});
	
	// modification de commentaire
	$(document).on("click", ".btn-modify", function(){ 	
		var urlpost= $(this).parents('form').attr("action") + '&jsonp_callback=?' ;
		$(this).parents(".yeswiki-comment").attr("id",'comments');
		$.getJSON(urlpost, { 
			"submit" : "savecomment",
			"initialpage": $('#initialpage').attr('value'),
			"wiki" : $(".form-modify-comment input[name='wiki']").val(),
			"previous" : $(".form-modify-comment input[name='previous']").val(),
			"body" : $(".form-modify-comment textarea[name='body']").val(),
			"antispam" : "1"
		}, function(data) {				 
		    if (data.nochange=='1') {
		     	$("#comments").show();
		    } else {      	
		      	// on enleve le formulaire et on affiche le contenu ajax				      			
	      		$("#comments").before(data.html);
		      	$("#comments").remove();
	      	}
		    $(".form-modify-comment").remove();
	   	});
	});			
				
	// ajax suppression commentaire			
	$(document).on("click", ".delete-comment", function(){ 	
		var urlget = $(this).attr('href').replace('deletepage','ajaxdeletepage')+'&jsonp_callback=?';
		$(this).parents('.yeswiki-comment').attr("id",'commentasupp');
		
		if (confirm('Voulez vous vraiment supprimer cette entree et ses commentaires associes?'))
		{
			$.getJSON(urlget, function(data) {				 
			    if (data.reponse=='succes') {
			    	$("#commentasupp").next(".comment-replies").remove();
			    	$("#commentasupp").remove();
			    } else {      	
			      	alert(data.reponse);
		      	}
		   	});
			return false;
		}
		else 
		{
			return false;
		}		      
	});
	
});