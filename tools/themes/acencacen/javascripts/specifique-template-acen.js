jQuery(function(){
	//ajout de classes pour traitement css
	$("#menu_haut > div.div_include > ul > li").each(function(i) {$(this).addClass('menu'+i);});
	$("#col_menu > div.div_include > ul > li").each(function(i) {$(this).addClass('menu'+i);});
	
	//menu deroulant du haut
	$("#menu_haut ul li").hover(function(){   
        $(this).addClass("hover").children('a').animate({ opacity: 0.9 }, 'fast' );
        $('ul:first',this).css('display', 'block').animate({ opacity: 0.9 }, 'fast' );
;
    }, function(){
        $(this).removeClass("hover");
        $('ul:first',this).css('display', 'none');
	});

    $("#menu_haut ul li ul li:has(ul), #col_menu ul li ul li:has(ul)").find("a:first").append("<span class=\"fleche_menu\">&gt;</span>");
    
	//menu de gauche avec accordeon
	$("#col_menu > div.div_include > ul > li").each(function(i) {
		$(this).bind("mouseenter", function() {
			$(this).children("a").removeClass("depliable").addClass("deplie");
			$(this).children("ul").show('fast');
			return false;
		}).bind("mouseleave", function() {
			$(this).children("a").removeClass("deplie").addClass("depliable"); 
			$(this).children("ul").hide('fast');
			return false;
		}).prev("a").addClass("depliable")
	});
	
	//déplacement des bloc à droite
	var precisions = $("#precisions").html();
	if (eval(typeof(window[precisions]) != "undefined") && precisions.length>0) {
		precisions = "<div id=\"quelques_precisions\"></div>" + precisions;
	}
	var ensavoirplus = $("#ensavoirplus").html();
	if (eval(typeof(window[ensavoirplus]) != "undefined") && ensavoirplus.length>0) {
		ensavoirplus = "<div id=\"en_savoir_plus\"></div>" + ensavoirplus;
	}
	$("#precisions").remove();
	$("#ensavoirplus").remove();
	$('#infos_complementaires').append(precisions+ensavoirplus);
});
