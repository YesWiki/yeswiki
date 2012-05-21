$(document).ready(function() {

	//bidouille antispam
	$(".antispam").attr('value', '1');

	//ajax envoi de nouveaux commentaires
	$(".bouton_microblog").live("click", function() {
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
	
	//ajax repondre à un commentaire			
	$("a.repondre_commentaire").live("click", function() {
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
	$("a.editer_commentaire").live("click", function() {
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
	$("input.bouton_annul").live("click", function() {
		$(".comment_a_editer, .reponsecommentform").remove();
		$("#comments").show().removeAttr("id");		      
	});
	
	//sauvegarde commentaire
	$("input.bouton_submit").live("click", function() {
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
	$("a.supprimer_commentaire, a.supprimer_billet").live("click", function() {
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
	var textarea = $('.textarea-microblog');
	var max = $('.textarea-microblog').attr('maxlength');

	//on empeche d'aller au dela de la limite du nombre de caracteres
	textarea.on("keyup", function(){
		var $this = $(this);
		var length = $this.val().length;
		if(length > max){
			$this.val($this.val().substr(0, max));
		}
		$this.siblings('.help-block').find('.info_nb_car').html((max - length));
	});
	
});