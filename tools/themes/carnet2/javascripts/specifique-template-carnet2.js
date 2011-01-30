jQuery(function(){
	//menu de gauche avec accordeon
	$("#menu_gauche > div.div_include > ul > li > ul, #menu_droite > div.div_include > ul > li > ul").hide();
	$("#menu_gauche > div.div_include > ul > li, #menu_droite > div.div_include > ul > li").children("ul").each(function(i) {
		$(this).prev("a").addClass("depliable").bind("click", function() {
			if ( $(this).hasClass("depliable") ) { $(this).removeClass("depliable").addClass("deplie"); }
			else {$(this).removeClass("deplie").addClass("depliable"); }
			$(this).next("ul").slideToggle('fast');
			return false;
		})
	});
	
	var hauteur_gauche = $('#menu_gauche').height();
	var hauteur_milieu = $('#contentmilieu').height();
	var hauteur_content = $('#content').height();
	var hauteur_droite = $('#colonne_droite').height();
	var hauteur_max = hauteur_gauche;
	if (hauteur_max < hauteur_milieu) {hauteur_max = hauteur_milieu;}
	if (hauteur_max < hauteur_content) {hauteur_max = hauteur_content;}
	if (hauteur_max < hauteur_droite) {hauteur_max = hauteur_droite;}
	$('#content, #contentmilieu, #menu_gauche, #colonne_droite').height(hauteur_max);
	
});
