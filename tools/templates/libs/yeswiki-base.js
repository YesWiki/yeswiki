/**
* hoverIntent r6 // 2011.02.26 // jQuery 1.5.1+
* <http://cherne.net/brian/resources/jquery.hoverIntent.html>
* 
* @param  f  onMouseOver function || An object with configuration options
* @param  g  onMouseOut function  || Nothing (use configuration options object)
* @author    Brian Cherne brian(at)cherne(dot)net
*/
(function($){$.fn.hoverIntent=function(f,g){var cfg={sensitivity:7,interval:100,timeout:0};cfg=$.extend(cfg,g?{over:f,out:g}:f);var cX,cY,pX,pY;var track=function(ev){cX=ev.pageX;cY=ev.pageY};var compare=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);if((Math.abs(pX-cX)+Math.abs(pY-cY))<cfg.sensitivity){$(ob).unbind("mousemove",track);ob.hoverIntent_s=1;return cfg.over.apply(ob,[ev])}else{pX=cX;pY=cY;ob.hoverIntent_t=setTimeout(function(){compare(ev,ob)},cfg.interval)}};var delay=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);ob.hoverIntent_s=0;return cfg.out.apply(ob,[ev])};var handleHover=function(e){var ev=jQuery.extend({},e);var ob=this;if(ob.hoverIntent_t){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t)}if(e.type=="mouseenter"){pX=ev.pageX;pY=ev.pageY;$(ob).bind("mousemove",track);if(ob.hoverIntent_s!=1){ob.hoverIntent_t=setTimeout(function(){compare(ev,ob)},cfg.interval)}}else{$(ob).unbind("mousemove",track);if(ob.hoverIntent_s==1){ob.hoverIntent_t=setTimeout(function(){delay(ev,ob)},cfg.timeout)}}};return this.bind('mouseenter',handleHover).bind('mouseleave',handleHover)}})(jQuery);

/* Author: Florian Schmitt */ 
(function($){
	//gestion des classes actives pour les menus
	$("a.actif").parent().addClass('liste-active').parents("ul").prev("a").addClass('actif').parent().addClass('liste-active');
	
	/* Ajout de l'overlay pour le partage de page et l'envois par mail */
	$('#container').before('<div id="overlay-link" class="yeswiki-overlay" style="display:none"><div class="contentWrap" style="width:600px"></div></div>');
	$('a[rel="#overlay-link"]').overlay({
		onBeforeLoad: function() {
			// grab wrapper element inside content
			var wrap = this.getOverlay().find(".contentWrap");
	
			// load the page specified in the trigger
			var url = this.getTrigger().attr("href") + ' .page'
			wrap.load(url);
		}
	});
	
	// Menus déroulants
	var config_col_menu = {    
		 sensitivity: 3, // number = sensitivity threshold (must be 1 or higher)    
		 interval: 100, // number = milliseconds for onMouseOver polling interval    
		 over: function(){
			// on ferme les menus deroulants deja ouverts
			var listes = $(this).siblings('li');
			listes.removeClass('hover').find('ul').slideUp('fast');
			listes.find(".arrow").html("&#9658;");
			
			//on deroule et on tourne la fleche
			$(this).addClass('hover').find('ul:first').slideDown('fast');
			$(this).find(".arrow:first").html("&#9660;");
		 },
		 timeout: 100, // number = milliseconds delay before onMouseOut    
		 out: function(){ return false; }
	};
	$(".liste-deroulante li:has(ul)").hoverIntent( config_col_menu );
	
	//pour les menus qui possèdent des sous menus, on affiche une petite flèche pour indiquer
	var arrowright = $("<span>").addClass('arrow arrow-level1').html("&#9658;");
	$(".liste-deroulante li:has(ul)").find("a:first").prepend(arrowright);

	//deroule le deuxieme niveau pour la PageMenu, si elle contient le lien actif
	var listesderoulables = $(".liste-deroulante > ul > li.liste-active:has(ul)");
	listesderoulables.addClass('hover').find('ul:first').slideDown('fast');
	listesderoulables.find(".arrow:first").html("&#9660;");
	
	//on enleve la fonction doubleclic dans des cas ou cela pourrait etre indesirable
	$(".no-dblclick, form, a").bind('dblclick', function(e) {
		return false;
	});
	
})(jQuery);
