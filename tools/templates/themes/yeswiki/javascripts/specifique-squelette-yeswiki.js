/* Author: Florian Schmitt */ 
(function($){

	// cache navigation du haut
	var nav = $("#topnav");
	
	/* on ajoute des flèches pour signaler les sous menus et on gère le menu déroulant */
	nav.find("li").each(function() {
		// s'il y a des sous menus
		if ($(this).find("ul").length > 0) {
			// selon la hierarchie des menu, on change le sens et la forme de la fleche
			if ($(this).parents("ul").length <= 1) {
				var arrow = $("<span>").addClass('arrow arrow-level1').html("&#9660;");
			}
			else {
				var arrow = $("<span>").addClass('arrow arrow-level'+$(this).parents("ul").length).html("&#9658;");
			}
			
			var firstsublist = $(this).find('ul:first');
			if (firstsublist.length > 0) { 
				firstsublist.prev().append(arrow); 
			}
			else { 
				$(this).before(arrow); 
			};
			
			var config = {    
			 sensitivity: 3,    
			 interval: 100,    
			 over: function() { //show submenu
					$(this).addClass('sfHover').find("ul:first").show();
				},    
			 timeout: 100,    
			 out: function() { //hide submenu
					$(this).removeClass('sfHover').find("ul").hide();
				}
			};
			$(this).hoverIntent( config );

		}
	});
	nav.find("ul").each(function() { 
		$(this).find("li:last").addClass('last');
	});	
})(jQuery);
