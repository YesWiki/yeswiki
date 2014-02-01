/* Author: Florian Schmitt <florian@outils-reseaux.org> */ 
(function($){
	var menuhaut = $("nav.nav-collapse");
	var pagehautinc =  menuhaut.find("> div.include");
	menuhaut.attr('ondblclick', pagehautinc.attr('ondblclick'));
	pagehautinc.removeAttr('ondblclick');
	
	$("nav.sidebar-nav ul").each(function() {$(this).addClass('nav nav-list');}); // menu de gauche
	$('nav.sidebar-nav > div > ul li').each(function() { // on met les textes entetes d'une liste en majuscules grises
		var lien = $(this).find('> a');
		if (lien.length===0) {
			$(this).addClass('nav-header');
		}
	});
})(jQuery);
