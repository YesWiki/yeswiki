jQuery(function(){
	//ajout de classes pour traitement css
	$("#menu_haut > div.div_include > ul > li").each(function(i) {$(this).addClass('menu'+i);});
	$("#col_menu > div.div_include > ul > li").each(function(i) {$(this).addClass('menu'+i);});
	
	//menu deroulant du haut
	$("#menu_haut ul li").hover(function(){   
        $(this).addClass("hover");
        $('ul:first',this).css('display', 'block');
    }, function(){
        $(this).removeClass("hover");
        $('ul:first',this).css('display', 'none');
    });
    $("#menu_haut ul li ul li:has(ul)").find("a:first").append(" &raquo; ");
    
	//menu de gauche avec accordeon
	$("#col_menu > div.div_include > ul > li > ul").hide();
	$("#col_menu > div.div_include > ul > li").children("ul").each(function(i) {
		$(this).prev("a").addClass("depliable").bind("click", function() {
			if ( $(this).hasClass("depliable") ) { $(this).removeClass("depliable").addClass("deplie"); }
			else {$(this).removeClass("deplie").addClass("depliable"); }
			$(this).next("ul").slideToggle('fast');
			return false;
		})
	});
});
