// Gestion des menu 2nd niveau
function sfHover() {
	var sfEls = $("#menuhaut > div > ul > li");
	for (var i=0; i<sfEls.length; ++i) {
		sfEls[i].onmouseover=function() {
			clearTimeout(this.timer);
			//var myLink = $E('a',this);
			//myLink.setStyle('background-image','url("../images/fondMenu1_Over.gif")');
			//.item2 a:hover {background-image:url(../images/fondMenu1_Over.gif);}
			if(this.className.indexOf(" sfhover") == -1)
				this.className+=" sfhover";
				
		}
		sfEls[i].onmouseout=function() {
			sfHoverOut(this);	
		}
	}
}

function sfHoverOut(element) {
	clearTimeout(element.timer);
	//console.log(element,'out');
	$//E('a',element).setStyle('border','');
	element.className=element.className.replace(new RegExp(" sfhover\\b"), "");
}

jQuery(document).ready(function() {
	(function($){$.fn.hoverIntent=function(f,g){var cfg={sensitivity:7,interval:100,timeout:0};cfg=$.extend(cfg,g?{over:f,out:g}:f);var cX,cY,pX,pY;var track=function(ev){cX=ev.pageX;cY=ev.pageY;};var compare=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);if((Math.abs(pX-cX)+Math.abs(pY-cY))<cfg.sensitivity){$(ob).unbind("mousemove",track);ob.hoverIntent_s=1;return cfg.over.apply(ob,[ev]);}else{pX=cX;pY=cY;ob.hoverIntent_t=setTimeout(function(){compare(ev,ob);},cfg.interval);}};var delay=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);ob.hoverIntent_s=0;return cfg.out.apply(ob,[ev]);};var handleHover=function(e){var p=(e.type=="mouseover"?e.fromElement:e.toElement)||e.relatedTarget;while(p&&p!=this){try{p=p.parentNode;}catch(e){p=this;}}if(p==this){return false;}var ev=jQuery.extend({},e);var ob=this;if(ob.hoverIntent_t){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);}if(e.type=="mouseover"){pX=ev.pageX;pY=ev.pageY;$(ob).bind("mousemove",track);if(ob.hoverIntent_s!=1){ob.hoverIntent_t=setTimeout(function(){compare(ev,ob);},cfg.interval);}}else{$(ob).unbind("mousemove",track);if(ob.hoverIntent_s==1){ob.hoverIntent_t=setTimeout(function(){delay(ev,ob);},cfg.timeout);}}};return this.mouseover(handleHover).mouseout(handleHover);};})(jQuery);
	$("#menuhaut > div > ul > li").each( function(i) {$(this).addClass("rubrique"+(i+1));} );
	sfHover();
	
	//slideshow actus
	$(".slidetabs").tabs(".images > div", { effect: 'fade', fadeOutSpeed: "slow", rotate: true }).slideshow();
	

	// main vertical scroll
	$("#main").scrollable({

		// basic settings
		vertical: true,

		// up/down keys will always control this scrollable
		keyboard: 'static'

	// main navigator (thumbnail images)
	}).navigator("#main_navi");

});
