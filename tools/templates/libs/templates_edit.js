// On change le theme dynamiquement
$("#changetheme").on('change', function(){ 
	var val = $(this).val();
	// pour vider la liste
	document.getElementById("form_graphical_options").squelette.options.length=0
	for (var i=0; i<tab1[val].length; i++){
		
		  o=new Option(tab1[val][i],tab1[val][i]);
		 document.getElementById("form_graphical_options").squelette.options[document.getElementById("form_graphical_options").squelette.options.length]=o;
		
					
	}
	document.getElementById("form_graphical_options").style.options.length=0
	for (var i=0; i<tab2[val].length; i++){
		  o=new Option(tab2[val][i],tab2[val][i]);
		 document.getElementById("form_graphical_options").style.options[document.getElementById("form_graphical_options").style.options.length]=o;
					
	}					
});

// On change le css dynamiquement
/*$("#form_graphical_options select[name=style]").on('change', function(){ 
	var newstyle = $("#mainstyle").attr("href");
	newstyle = newstyle.substring(0, newstyle.lastIndexOf('/')) + '/' + $(this).attr('value');
	$("#mainstyle").attr("href", newstyle);
});*/

// on annule les changements de look
$("#graphical_options a.button_cancel").on("click", function() {
	if ( ($("#changetheme").val() !== $("#hiddentheme").val() ) || ( $("#hiddensquelette").val() !== $("#changesquelette").val() ) || ( $("#hiddenstyle").val() !== $("#changestyle").val() )) {
		//on charge le theme et on remet les valeurs
		var newstyle = $("#mainstyle").attr("href");
		newstyle = newstyle.substring(0, newstyle.lastIndexOf('/')) + '/' + $("#hiddenstyle").val();
		$("#mainstyle").attr("href", newstyle);
	}
	var hiddenimg = $("#hiddenbgimg").val()
	if (hiddenimg !== '') {
		//TODO : remettre l image initiale
		console.log(hiddenimg);
	}
	else {
		//on enleve les images de fond
		$("body").css({'background-image':'none', 'background-repeat':'repeat', 'width':'100%', 'height':'100%', '-webkit-background-size': 'auto', '-moz-background-size': 'auto', '-o-background-size': 'auto', 'background-size': 'auto', 'background-attachment': 'scroll', 'background-clip': 'border-box', 'background-origin': 'padding-box', 'background-position': 'top left'});
		$(".choosen").removeClass("choosen");
	}
	
	//on remet les valeurs par défaut aux listes déroulantes
	$("#changetheme").val($("#hiddentheme").val());
	$("#changesquelette").val($("#hiddensquelette").val());
	$("#changestyle").val($("#hiddenstyle").val());

	return;
});

// on sauve les metas et on transmet les valeurs changées du theme au formulaire
$("#graphical_options a.button_save").on("click", function() {
	var theme = $("#changetheme").val();
	$("#hiddentheme").val(theme);
	var squelette = $("#changesquelette").val();
	$("#hiddensquelette").val(squelette);
	var style = $("#changestyle").val();
	$("#hiddenstyle").val(style);
	var bgimg = $(".choosen").css("background-image");
	var imgsrc = $(".choosen").attr("src");
	
	if(!(typeof bgimg === 'undefined') && bgimg != 'none'){
		bgimg = bgimg.substr(bgimg.lastIndexOf("/")+1, bgimg.length - bgimg.lastIndexOf("/") );
		bgimg = bgimg.replace("\")","");
	} 
	if (typeof imgsrc === 'string') {
		bgimg = imgsrc.substr(imgsrc.lastIndexOf("/")+1, imgsrc.length - imgsrc.lastIndexOf("/") );
	}
	$("#hiddenbgimg").val(bgimg);
	var url = document.URL;
	$.post(url.replace("/edit", "/savemetadatas"), { 'metadatas': { "theme": theme, "squelette": squelette, "style": style, "bgimg": bgimg } });
	return;
});

// changement de fond d ecran
$("#bgCarousel img.bgimg").on("click", function() {
	// Au cas ou le template ne le prend pas en compte, on met html à 100%
	$("html").css({'width':'100%', 'height':'100%'});

	// desactivation de la meme image de fond
	if ($(this).hasClass('choosen')) {
		$("body").css({'background-image':'none', 'background-repeat':'repeat', 'width':'100%', 'height':'100%', '-webkit-background-size': 'auto', '-moz-background-size': 'auto', '-o-background-size': 'auto', 'background-size': 'auto', 'background-attachment': 'scroll', 'background-clip': 'border-box', 'background-origin': 'padding-box', 'background-position': 'top left'});
		$(this).removeClass("choosen");
	}
	else {
		var imgsrc = $(this).attr("src");
		imgsrc = imgsrc.replace('thumbs/','');
		$("#bgCarousel .choosen").removeClass("choosen");
		$(this).addClass("choosen");
		$("body").css({'background-image':'url('+imgsrc+')', 'background-repeat':'no-repeat', 'width':'100%', 'height':'100%', '-webkit-background-size':'cover', '-moz-background-size':'cover', '-o-background-size':'cover', 'background-size':'cover', 'background-attachment':'fixed', 'background-clip':'border-box', 'background-origin':'padding-box', 'background-position':'center center'});
	}
}); 

// changement de fond d ecran en mosaique
$("#bgCarousel div.mozaicimg").on("click", function() {
	// desactivation de la meme image de fond
	if ($(this).hasClass('choosen')) {
		$("body").css({'background-image':'none', 'background-repeat':'repeat', 'width':'100%', 'height':'100%', '-webkit-background-size': 'auto', '-moz-background-size': 'auto', '-o-background-size': 'auto', 'background-size': 'auto', 'background-attachment': 'scroll', 'background-clip': 'border-box', 'background-origin': 'padding-box', 'background-position': 'top left'});
		$(this).removeClass("choosen");
	}
	else {
		$("body").css({'background-image':$(this).css('background-image'), 'background-repeat':'repeat', 'width':'100%', 'height':'100%', '-webkit-background-size': 'auto', '-moz-background-size': 'auto', '-o-background-size': 'auto', 'background-size': 'auto', 'background-attachment': 'scroll', 'background-clip': 'border-box', 'background-origin': 'padding-box', 'background-position': 'top left'});
		$("#bgCarousel .choosen").removeClass("choosen");
		$(this).addClass("choosen");			
	}
}); 