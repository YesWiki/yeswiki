/**
* hoverIntent r5 // 2007.03.27 // jQuery 1.1.2+
* <http://cherne.net/brian/resources/jquery.hoverIntent.html>
* 
* @param  f  onMouseOver function || An object with configuration options
* @param  g  onMouseOut function  || Nothing (use configuration options object)
* @author    Brian Cherne <brian@cherne.net>
*/
(function($){$.fn.hoverIntent=function(f,g){var cfg={sensitivity:7,interval:100,timeout:0};cfg=$.extend(cfg,g?{over:f,out:g}:f);var cX,cY,pX,pY;var track=function(ev){cX=ev.pageX;cY=ev.pageY;};var compare=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);if((Math.abs(pX-cX)+Math.abs(pY-cY))<cfg.sensitivity){$(ob).unbind("mousemove",track);ob.hoverIntent_s=1;return cfg.over.apply(ob,[ev]);}else{pX=cX;pY=cY;ob.hoverIntent_t=setTimeout(function(){compare(ev,ob);},cfg.interval);}};var delay=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);ob.hoverIntent_s=0;return cfg.out.apply(ob,[ev]);};var handleHover=function(e){var p=(e.type=="mouseover"?e.fromElement:e.toElement)||e.relatedTarget;while(p&&p!=this){try{p=p.parentNode;}catch(e){p=this;}}if(p==this){return false;}var ev=jQuery.extend({},e);var ob=this;if(ob.hoverIntent_t){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);}if(e.type=="mouseover"){pX=ev.pageX;pY=ev.pageY;$(ob).bind("mousemove",track);if(ob.hoverIntent_s!=1){ob.hoverIntent_t=setTimeout(function(){compare(ev,ob);},cfg.interval);}}else{$(ob).unbind("mousemove",track);if(ob.hoverIntent_s==1){ob.hoverIntent_t=setTimeout(function(){delay(ev,ob);},cfg.timeout);}}};return this.mouseover(handleHover).mouseout(handleHover);};})(jQuery);


/* Author: Florian Schmitt */ 
(function($){
	/* menu déroulant du haut pour la navigation, inspiré du tutoriel : http://net.tutsplus.com/tutorials/html-css-techniques/how-to-create-a-drop-down-nav-menu-with-html5-css3-and-jquery/ */				
	//cache nav
	var nav = $("#topnav");
	
	/* on ajoute des flèches pour signaler les sous menus et on gère le menu déroulant */
	nav.find("li").each(function() {
		if ($(this).find("ul").length > 0) {
			if ($(this).parents("ul").length <= 1) {
				var arrow = $("<span>").addClass('arrow arrow-level1').html("&#9660;");
				var firstlink = $(this).find('> a:first');
				if (firstlink.length > 0) { arrow.appendTo(firstlink); }
				else { arrow.appendTo($(this)); };
			}
			else {
				$("<span>").addClass('arrow arrow-level'+$(this).parents("ul").length).html("&#9658;").appendTo($(this));
			}
			
			var config = {    
			 sensitivity: 3,    
			 interval: 100,    
			 over: function() { //show submenu
					$(this).find("ul:first").slideDown(100);
				},    
			 timeout: 100,    
			 out: function() { //hide submenu
					$(this).find("ul").slideUp(200);
				}
			};
			$(this).hoverIntent( config );

		}
	});
	nav.find("ul").each(function() { 
		$(this).find("li:last").addClass('last');
	});
	
	/* Ajout de l'overlay pour le partage de page et l'envois par mail */
	$('#container').before('<div id="overlay-link" class="yeswiki-overlay" style="display:none"><div class="contentWrap" style="width:600px"></div></div>');
	$('a[rel="#overlay-link"]').overlay({
		mask: '#999',
		onBeforeLoad: function() {
			// grab wrapper element inside content
			var wrap = this.getOverlay().find(".contentWrap");
	
			// load the page specified in the trigger
			var url = this.getTrigger().attr("href") + ' .page'
			wrap.load(url);
		}
	});
})(jQuery);
