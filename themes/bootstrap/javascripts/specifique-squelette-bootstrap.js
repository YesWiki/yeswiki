/* Author: Florian Schmitt <florian@outils-reseaux.org> */ 
(function($){
	// on ajoute en jquery les classes css du bootstrap
	$("nav.nav-collapse > div > ul").each(function() { // menu du haut
		$(this).addClass('nav');
		$(this).find('>li:has(ul)').addClass('dropdown').find('a:first').addClass('dropdown-toggle').attr('data-toggle','dropdown').append('<b class="caret"></b>');
		$(this).find('> li:has(ul) > ul:first').addClass('dropdown-menu');
	}); 
	$("nav.sidebar-nav ul").each(function() {$(this).addClass('nav nav-list');}); // menu de gauche
	$('nav.sidebar-nav > div > ul li').each(function() { // on met les textes entetes d'une liste en majuscules grises
		var lien = $(this).find('> a');
		if (lien.length===0) {
			$(this).addClass('nav-header');
		}
	});
	
	// gestion des classes actives pour les menus
	$("a.actif").parent().addClass('active').parents("ul").prev("a").addClass('actif').parent().addClass('active');
	
	// ajout de l'overlay pour le partage de page et l'envois par mail 
	$('body').prepend('<div id="overlay-link" class="yeswiki-overlay" style="display:none"><div class="contentWrap" style="width:600px"></div></div>');
	$('a[rel="#overlay-link"]').overlay({
		onBeforeLoad: function() {
			// grab wrapper element inside content
			var wrap = this.getOverlay().find(".contentWrap");
	
			// load the page specified in the trigger
			var url = this.getTrigger().attr("href") + ' .page'
			wrap.load(url);
		}
	});
	
	// on enleve la fonction doubleclic dans des cas ou cela pourrait etre indesirable
	$(".accordion, .slide_show").bind('dblclick', function(e) {
		return false;
	});
	
/*
	$("#graphical_bouton").overlay({
		mask: 'transparent',
		closeOnClick : false,
		onLoad: function() {}
	});
	$('#graphical_bouton').modal({backdrop:false});
*/

	// accordeon pour bazarliste
	$(".accordion_title").bind('click',function() {
		if ($(this).hasClass('current')) {
			$(this).removeClass('current');
			$(this).next("div.pane").hide();
		} else { 
			$(this).addClass('current');
			$(this).next("div.pane").show();
		}
	});
	
})(jQuery);
