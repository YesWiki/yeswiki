jQuery(document).ready(function() {
	//ajout de classes pour traitement css
	$("#menu_haut > div.div_include > ul > li").each(function(i) {$(this).addClass('menu'+i);});
	$("#col_menu > div.div_include > ul > li").each(function(i) {$(this).addClass('menu'+i);});
	$("a.actif").parent().addClass('liste-active').parents("ul").prev("a").addClass('actif').parent().addClass('liste-active');
	
	//pour les menus qui possèdent des sous menus, on affiche une petite flèche pour indiquer
	$("#menu_haut > div.div_include > ul > li:has(ul)").find("a:first").append("<span class=\"fleche_menu_droite fleche_menu_bas\"></span>");
	$("#col_menu > div.div_include > ul > li:has(ul)").find("a:first").append("<span class=\"fleche_menu_gauche fleche_menu_droit\"></span>");
    $("#menu_haut ul li ul li:has(ul), #col_menu ul li ul li:has(ul)").find("a:first").append("<span class=\"fleche_menu_droite fleche_menu_droit\"></span>");	
	
	//menu haut
	var config_menu_deroulant = {    
		 sensitivity: 7, // number = sensitivity threshold (must be 1 or higher)    
		 interval: 100, // number = milliseconds for onMouseOver polling interval    
		 over: function(){ $('ul:first',this).slideDown('fast').parent().addClass('hover'); },
		 timeout: 600, // number = milliseconds delay before onMouseOut    
		 out: function(){ $('ul:first',this).slideUp('fast').parent().removeClass('hover'); }
	};
	$("#menu_haut ul li:has(ul)").hoverIntent( config_menu_deroulant );
	
	//PageMenu
	var config_col_menu = {    
		 sensitivity: 7, // number = sensitivity threshold (must be 1 or higher)    
		 interval: 200, // number = milliseconds for onMouseOver polling interval    
		 over: function(){ $('ul:first',this).slideDown('slow').parent().addClass('hover').siblings('li').removeClass('hover').find('ul').slideUp('slow'); },
		 timeout: 100, // number = milliseconds delay before onMouseOut    
		 out: function(){ return false; }
	};
	$("#col_menu > div.div_include > ul > li:has(ul)").hoverIntent( config_col_menu );
	$("#col_menu ul li ul li:has(ul)").hoverIntent( config_menu_deroulant );
	
	//deroule le deuxieme niveau pour la PageMenu, si elle contient le lien actif
	$("#col_menu > div.div_include > ul > li.liste-active:has(ul)").addClass('hover').find('ul:first').slideDown('fast');
	
	//barre d'actions	
	$("body").append('<div id="overlay-action"><div class="contenu-overlay-action"></div></div>');	
	$(".barre_actions a[title]").tooltip({
	   offset: [10, 2],
	   effect: 'slide',
	   tipClass: 'tooltip_action',
	   opacity: 1,
	   delay:300,
	   predelay:50
	});
	$(".barre_actions a[rel], a.voirpage").overlay({
		mask: '#482E15',
		effect: 'apple',
		onBeforeLoad: function() {
			var wrap = this.getOverlay().find(".contenu-overlay-action");
			// on charge juste la div de classe "page" pour ne pas afficher les menus et autres contenus
			wrap.load(this.getTrigger().attr("href")+' .page');
			$("a.bouton_share[title]").tooltip({
				   offset: [10, 2],
				   effect: 'slide',
				   tipClass: 'tooltip_action',
				   opacity: 1,
				   delay:300,
				   predelay:50
				});
		}
	});
});
