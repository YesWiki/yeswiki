$(document).ready(function() {
	$('.tag-label').hover(function(){
		$(this).addClass('label-primary');
		$(this).removeClass('label-info');
	}, function(){
		if(!$(this).hasClass('label-active')){
			$(this).addClass('label-info');
			$(this).removeClass('label-primary');
		}
	});

	//nuage de mots clés : tooltip
	$('.tag-link').popover({html:true,placement:'top',trigger:'click'})
	$('.nuage').on( "click", ".btn-close-popover",  function() {
		$(this).parents('.popover').prev('.tag-link').popover('hide'); 
		return false;
	});

	//nettoyage des formulaires
	$.fn.clearForm = function() {
	    return this.each(function() {
	      var type = this.type, tag = this.tagName.toLowerCase();
	      if (tag == 'form')
	        return $(':input',this).clearForm();
	      if (type == 'text' || type == 'password' || tag == 'textarea')
	        this.value = '';
	      else if (type == 'checkbox' || type == 'radio')
	        this.checked = false;
	      else if (tag == 'select')
	        this.selectedIndex = -1;
	    });
	};
	
	//bidouille antispam
	$(".antispam").attr('value', '1');
		
	//accordeon
	$("a.lien_titre_accordeon").on("click", function() {
		$(this).siblings(".accordeon_cache").toggle();
		return false;
	});
	
	//Afficher/cacher les commentaires
	$("strong.lien_commenter").css("cursor", "pointer").on("click", function(){
		$(this).siblings(".commentaires_billet_microblog").toggle().find(".commentaire_microblog").focus();
	});

	//ajax envoi de nouveaux commentaires
	$(".bouton_microblog").on("click", function() {
		var textcommentaire = $(this).prevAll(".commentaire_microblog").val();	
		var urlpost= $(this).parent("form").attr("action").replace('/addcomment','/ajaxaddcomment'+'&jsonp_callback=?'); 		
		$(this).parents(".microblogcommentform, .reponsecommentform").attr("id",'comments');	
		
		$.ajax({
			type: "POST",
			url: urlpost,
			data: { body: textcommentaire, antispam : "1" },
			dataType: "jsonp",		
			success: function(data){
				$("#comments").before(data.html).removeAttr("id");
				$(".microblogcommentform form").clearForm();
				$(".reponsecommentform").remove();
			}
		 });

		return false;
	});
	
	//ajax repondre é un commentaire			
	$("a.repondre_commentaire").on("click", function() {
		//on cache les formulaires déja ouverts et on reaffiche le contenu
		$(".comment_a_editer, .reponsecommentform").remove();
		$("#comments").show().removeAttr("id");	
		   
		$(this).parents(".comment").next(".commentreponses").append("<div class=\"reponsecommentform\">" +
			"<form action=\""+$(this).attr("href")+"/addcomment\" method=\"post\">" +
			"<input name=\"wiki\" value=\""+$(this).parents(".comment").prev("a").attr("name")+"/addcomment\" type=\"hidden\">" +
			"<textarea name=\"body\" class=\"commentaire_microblog\" rows=\"3\" cols=\"20\"></textarea><br>" +
			"<input class=\"bouton_microblog\" value=\"R&eacute;pondre\" accesskey=\"s\" type=\"button\">" +
			"<input class=\"bouton_annul\" type=\"button\" value=\"Annulation\" /></form>" +
			"</div>");

		$(".reponsecommentform").focus();
		$(this).parents(".comment").next(".commentreponses").find("textarea.commentaire_microblog").focus();											
		
		return false;		      
	});
	 
	//ajax edition commentaire			
	$("a.editer_commentaire").on("click", function() {
		//on cache les formulaires déja ouverts et on reaffiche le contenu
		$(".comment_a_editer, .reponsecommentform").remove();
		$("#comments").show().removeAttr("id");	
		
		//on attribut un id au div selectionne, afin de le retrouver
		$(this).parents(".comment").attr("id",'comments');		
		
		var urlpost= $(this).attr("href").replace('edit','ajaxedit')+'&jsonp_callback=?';					   
	   	$.getJSON(urlpost, {"commentaire" : "1"}, function(data) {
	   		if (data.nochange=='1') {
		     	$("#comments").show();
		    } else {
				//on affiche le contenu ajax
			    var ajoutajax = $("<div>").addClass("comment_a_editer").html(data.html).show();
			    $("#comments").after(ajoutajax);
			    $("#body").focus();
				$("#comments").hide();
		    }
		});										

		return false;		      
	});
	
	//annulation edition commentaire
	$("input.bouton_annul").on("click", function() {
		$(".comment_a_editer, .reponsecommentform").remove();
		$("#comments").show().removeAttr("id");		      
	});
	
	//sauvegarde commentaire
	$("input.bouton_submit").on("click", function() {
		var urlpost= $("#ACEditor").attr("action") + '&jsonp_callback=?' ;
		$(this).parents(".comment").attr("id",'comments');		
		$.getJSON(urlpost, { 
			"submit" : "Sauver",
			"commentaire" : "1",
			"wiki" : $("#ACEditor input[name='wiki']").val(),
			"previous" : $("#ACEditor input[name='previous']").val(),
			"body" : $("#ACEditor textarea[name='body']").val(),
		}, function(data) {				 
		    if (data.nochange=='1') {
		     	$("#comments").show();
		    } else {      	
		      	//on enleve le formulaire et on affiche le contenu ajax				      			
	      		$("#comments").before(data.html);
		      	$("#comments").remove();
	      	}
		    $(".comment_a_editer").remove();
	   	});
	});			
				
	//ajax suppression commentaire			
	$("a.supprimer_commentaire, a.supprimer_billet").on("click", function() {
		var urlget = $(this).attr('href').replace('deletepage','ajaxdeletepage')+'&jsonp_callback=?';
		$(this).parent().parent().attr("id",'commentasupp');
		
		if (confirm('Voulez vous vraiment supprimer cette entrée et ses commentaires associés?'))
		{
			$.getJSON(urlget, function(data) {				 
			    if (data.reponse=='succes') {
			    	$("#commentasupp").next(".commentreponses").remove();
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
	
	//on efface tous les écrits restants dans le formulaire du billet microblog
	$('.btn_annuler').on("click", function(){
		$(this).parents("form").clearForm();
		var max = parseInt($('.info_nb_car_max').html());
		$('.microblog_billet').focus().parent().find('.info_nb_car').html(max);
	});
	
	//on sauve le billet microblog en ajoutant l'antispam
	$('.btn_enregistrer').on("click", function(){
		$(this).parents("form").append("<input type=\"hidden\" name=\"antispam\" value=\"1\" />");	    
	});
	
	//on empeche d'aller au dela de la limite du nombre de caracteres
	$('.microblog_billet').on("keypress", function(){
		var max = parseInt($(this).parent().find('.info_nb_car_max').html());
		if($(this).val().length > max){
			$(this).val($(this).val().substr(0, max));
		}
		$(this).parent().find('.info_nb_car').html((max - $(this).val().length));
	});
	
});