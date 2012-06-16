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
	$("a.active-link").parent().addClass('active-list').parents("ul").prev("a").addClass('active-parent-link').parent().addClass('active-list');
	
	/* Ajout de l'overlay pour le partage de page et l'envois par mail */
	$('body').prepend('<div id="overlay-link" class="yeswiki-overlay" style="display:none"><div class="contentWrap" style="width:600px"></div></div>');
	$('a[rel="#overlay-link"]').overlay({
		onBeforeLoad: function() {
			// grab wrapper element inside content
			var wrap = this.getOverlay().find(".contentWrap");
			var url = this.getTrigger().attr("href");
			var finurl = url.substr(-3);
			
			// si c'est une image on l'affiche
			if (finurl === "png" || finurl === "jpg"| finurl === "jpeg" || finurl === "gif" ||
				finurl === "PNG" || finurl === "JPG"| finurl === "JPEG" || finurl === "GIF"	) {
				wrap.html('<img src="'+url+'" alt="image" />');
			}
			// on charge l'intérieur d'une page wiki sinon
			else {
				wrap.load(url + ' .page');
			}
			
		}
	});

	// Menus déroulants horizontaux
	var confighorizontal = {    
		sensitivity: 3,    
		interval: 100,    
		over: function() { //show submenu
			$(this).addClass('hover').find("ul:first").show();
		},    
		timeout: 100,    
		out: function() { //hide submenu
			$(this).removeClass('hover').find("ul").hide();
		}
	};
	var nav = $(".horizontal-dropdown-menu > ul");

	/* on ajoute des flèches pour signaler les sous menus et on gère le menu déroulant */
	nav.each(function() {
		var $nav = $(this);
		var nbmainlist = 1;
		$nav.find("li").each(function(i) {
			var $list = $(this);
			if ($list.parents("ul").length <= 1) { $list.addClass('list-'+nbmainlist); nbmainlist++;}

			// s'il y a des sous menus
			if ($list.find("ul").length > 0) {
				// selon la hierarchie des menu, on change le sens et la forme de la fleche
				if ($list.parents("ul").length <= 1) {
					var arrow = $("<span>").addClass('arrow arrow-level1').html("&#9660;");
				}
				else {
					var arrow = $("<span>").addClass('arrow arrow-level'+$list.parents("ul").length).html("&#9658;");
				}
				
				var firstsublist = $list.find('ul:first');
				if (firstsublist.length > 0) { 
					firstsublist.prev().append(arrow); 
				}
				else { 
					$list.before(arrow); 
				};
				
				$list.hoverIntent(confighorizontal);
			}
		});
		$nav.find("li:last").addClass('last');
	});
	
	
	// Menus déroulants verticaux
	var configvertical = {    
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

		//pour les menus qui possèdent des sous menus, on affiche une petite flèche pour indiquer
	var arrowright = $("<span>").addClass('arrow arrow-level1').html("&#9658;");
	$(".vertical-dropdown-menu li:has(ul)").hoverIntent( configvertical ).find("a:first").prepend(arrowright);
	

	//deroule le deuxieme niveau pour la PageMenu, si elle contient le lien actif
	var listesderoulables = $(".vertical-dropdown-menu > ul > li.active-list:has(ul)");
	listesderoulables.addClass('hover').find('ul:first').slideDown('fast');
	listesderoulables.find(".arrow:first").html("&#9660;");
	

	//on enleve la fonction doubleclic dans des cas ou cela pourrait etre indesirable
	$(".no-dblclick, form, a").bind('dblclick', function(e) {
		return false;
	});
	
})(jQuery);
