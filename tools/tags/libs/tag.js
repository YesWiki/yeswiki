$(document).ready(function() {
	//nuage de mots clés : tooltip
	$('.tooltip').each(function () {
		var distance = 200;
		var time = 250;
		var hideDelay = 100;
		var hideDelayTimer = null;
		var beingShown = false;
		var shown = false;
		var trigger = $(this);
		var info = $('#tooltip'+$(this).attr('id'));

		$([trigger.get(0), info.get(0)]).mouseover(function () {
			if (hideDelayTimer) clearTimeout(hideDelayTimer);
			if (beingShown || shown) {
				// don't trigger the animation again
				return;
			} else {
				// reset position of info box
				beingShown = true;

				info.css({
					display: 'block'
				}).animate({
					opacity: 0.9
				}, time, 'swing', function() {
					beingShown = false;
					shown = true;
				});
			}
			return false;
		}).mouseout(function () {
			if (hideDelayTimer) clearTimeout(hideDelayTimer);
			hideDelayTimer = setTimeout(function () {
				hideDelayTimer = null;
				info.animate({
					opacity: 0
				}, time, 'swing', function () {
					shown = false;
					info.css('display', 'none');
				});
			}, hideDelay);
			return false;
		});
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
	$("a.lien_titre_accordeon").live("click", function() {
		$(this).siblings(".accordeon_cache").toggle();
		return false;
	});
	
	//Afficher/cacher les commentaires
	$("strong.lien_commenter").css("cursor", "pointer").live("click", function(){
		$(this).siblings(".commentaires_billet_microblog").toggle().find(".commentaire_microblog").focus();
	});

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

		$.scrollTo(".reponsecommentform", 800);
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

	//le formulaire du microblog est activé pour écriture directement
	$("textarea.microblog_billet").focus();
		
	//on efface tous les écrits restants dans le formulaire du billet microblog
	$('.btn_annuler').live("click", function(){
		$(this).parents("form").clearForm();
		var max = parseInt($('.info_nb_car_max').html());
		$('.microblog_billet').focus().parent().find('.info_nb_car').html(max);
	});
	
	//on sauve le billet microblog en ajoutant l'antispam
	$('.btn_enregistrer').live("click", function(){
		$(this).parents("form").append("<input type=\"hidden\" name=\"antispam\" value=\"1\" />");	    
	});
	
	//on empeche d'aller au dela de la limite du nombre de caracteres
	$('.microblog_billet').live("keypress", function(){
		var max = parseInt($(this).parent().find('.info_nb_car_max').html());
		if($(this).val().length > max){
			$(this).val($(this).val().substr(0, max));
		}
		$(this).parent().find('.info_nb_car').html((max - $(this).val().length));
	});
	
});

/**
 * jQuery.ScrollTo - Easy element scrolling using jQuery.
 * Copyright (c) 2007-2009 Ariel Flesler - aflesler(at)gmail(dot)com | http://flesler.blogspot.com
 * Dual licensed under MIT and GPL.
 * Date: 5/25/2009
 * @author Ariel Flesler
 * @version 1.4.2
 *
 * http://flesler.blogspot.com/2007/10/jqueryscrollto.html
 */
;(function(d){var k=d.scrollTo=function(a,i,e){d(window).scrollTo(a,i,e)};k.defaults={axis:'xy',duration:parseFloat(d.fn.jquery)>=1.3?0:1};k.window=function(a){return d(window)._scrollable()};d.fn._scrollable=function(){return this.map(function(){var a=this,i=!a.nodeName||d.inArray(a.nodeName.toLowerCase(),['iframe','#document','html','body'])!=-1;if(!i)return a;var e=(a.contentWindow||a).document||a.ownerDocument||a;return d.browser.safari||e.compatMode=='BackCompat'?e.body:e.documentElement})};d.fn.scrollTo=function(n,j,b){if(typeof j=='object'){b=j;j=0}if(typeof b=='function')b={onAfter:b};if(n=='max')n=9e9;b=d.extend({},k.defaults,b);j=j||b.speed||b.duration;b.queue=b.queue&&b.axis.length>1;if(b.queue)j/=2;b.offset=p(b.offset);b.over=p(b.over);return this._scrollable().each(function(){var q=this,r=d(q),f=n,s,g={},u=r.is('html,body');switch(typeof f){case'number':case'string':if(/^([+-]=)?\d+(\.\d+)?(px|%)?$/.test(f)){f=p(f);break}f=d(f,this);case'object':if(f.is||f.style)s=(f=d(f)).offset()}d.each(b.axis.split(''),function(a,i){var e=i=='x'?'Left':'Top',h=e.toLowerCase(),c='scroll'+e,l=q[c],m=k.max(q,i);if(s){g[c]=s[h]+(u?0:l-r.offset()[h]);if(b.margin){g[c]-=parseInt(f.css('margin'+e))||0;g[c]-=parseInt(f.css('border'+e+'Width'))||0}g[c]+=b.offset[h]||0;if(b.over[h])g[c]+=f[i=='x'?'width':'height']()*b.over[h]}else{var o=f[h];g[c]=o.slice&&o.slice(-1)=='%'?parseFloat(o)/100*m:o}if(/^\d+$/.test(g[c]))g[c]=g[c]<=0?0:Math.min(g[c],m);if(!a&&b.queue){if(l!=g[c])t(b.onAfterFirst);delete g[c]}});t(b.onAfter);function t(a){r.animate(g,j,b.easing,a&&function(){a.call(this,n,b)})}}).end()};k.max=function(a,i){var e=i=='x'?'Width':'Height',h='scroll'+e;if(!d(a).is('html,body'))return a[h]-d(a)[e.toLowerCase()]();var c='client'+e,l=a.ownerDocument.documentElement,m=a.ownerDocument.body;return Math.max(l[h],m[h])-Math.min(l[c],m[c])};function p(a){return typeof a=='object'?a:{top:a,left:a}}})(jQuery);
