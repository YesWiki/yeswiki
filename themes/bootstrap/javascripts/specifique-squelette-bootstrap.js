/* Author: Florian Schmitt <florian@outils-reseaux.org> */ 
(function($){
	var menuhaut = $("nav.nav-collapse");
	var pagehautinc =  menuhaut.find("> div.include");
	menuhaut.attr('ondblclick', pagehautinc.attr('ondblclick'));
	pagehautinc.removeAttr('ondblclick');
	// on ajoute en jquery les classes css du bootstrap
	menuhaut.find("> div > ul").each(function() { // menu du haut
		var $this = $(this);
		$this.addClass('nav').append($('<div>').addClass('clear').html());
		$this.find('>li:has(ul)').each(function() {
			$(this).addClass('dropdown').find('a:first').addClass('dropdown-toggle').attr({'href':'#', 'data-toggle':'dropdown'}).append('<b class="caret"></b>').next('ul:first').addClass('dropdown-menu')});
	}); 

	menuhaut.find("ul ul li:has(ul)").each(function() { // menu du haut
		var $this = $(this);
		$this.addClass('dropdown-submenu')
		$this.find('a:first').next('ul:first').addClass('dropdown-menu');
	}); 
	
	$("nav.sidebar-nav ul").each(function() {$(this).addClass('nav nav-list');}); // menu de gauche
	$('nav.sidebar-nav > div > ul li').each(function() { // on met les textes entetes d'une liste en majuscules grises
		var lien = $(this).find('> a');
		if (lien.length===0) {
			$(this).addClass('nav-header');
		}
	});
})(jQuery);
