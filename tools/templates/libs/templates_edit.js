// on annule les changements de look
$("#graphical_options a.button_cancel").on("click", function() {
	$('#graphical_options form')[0].reset();
	if ( ($("#changetheme").val() !== $("#hiddentheme").val() ) || ( $("#hiddensquelette").val() !== $("#changesquelette").val() ) || ( $("#hiddenstyle").val() !== $("#changestyle").val() )) {
		//on charge le theme et on remet les valeurs
		var newstyle = $("#mainstyle").attr("href");
		newstyle = newstyle.substring(0, newstyle.lastIndexOf('/')) + '/' + $("#hiddenstyle").val();
		$("#mainstyle").attr("href", newstyle);
	}

	// l'image de fond
	$("#bgCarousel .choosen").removeClass("choosen");
	var hiddenimg = $("#hiddenbgimg").val()
	if (hiddenimg !== '') {
		// pour le jpg
		if (hiddenimg.substr(hiddenimg.length-4) === '.jpg') {		
			$('#bgCarousel .bgimg[src$="'+hiddenimg+'"]').addClass("choosen");
			$("body").css({'background-image':'url(files/backgrounds/'+hiddenimg+')', 'background-repeat':'no-repeat', 'width':'100%', 'height':'100%', '-webkit-background-size':'cover', '-moz-background-size':'cover', '-o-background-size':'cover', 'background-size':'cover', 'background-attachment':'fixed', 'background-clip':'border-box', 'background-origin':'padding-box', 'background-position':'center center'});
		}
		// pour le png
		else if (hiddenimg.substr(hiddenimg.length-4) === '.png') {
			$('#bgCarousel .mozaicimg[style*="'+hiddenimg+'"]').addClass("choosen");	
			$("body").css({'background-image': 'url(files/backgrounds/'+hiddenimg+')', 'background-repeat':'repeat', 'width':'100%', 'height':'100%', '-webkit-background-size': 'auto', '-moz-background-size': 'auto', '-o-background-size': 'auto', 'background-size': 'auto', 'background-attachment': 'scroll', 'background-clip': 'border-box', 'background-origin': 'padding-box', 'background-position': 'top left'});
		}
	}
	else {
		// on enleve les images de fond
		$("body").css({'background-image':'none', 'background-repeat':'repeat', 'width':'100%', 'height':'100%', '-webkit-background-size': 'auto', '-moz-background-size': 'auto', '-o-background-size': 'auto', 'background-size': 'auto', 'background-attachment': 'scroll', 'background-clip': 'border-box', 'background-origin': 'padding-box', 'background-position': 'top left'});
	}
	
	// on remet les valeurs par défaut aux listes déroulantes
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
		bgimg = bgimg.replace("\"","").replace(")","");
	}
	else if (typeof imgsrc === 'string') {
		bgimg = imgsrc.substr(imgsrc.lastIndexOf("/")+1, imgsrc.length - imgsrc.lastIndexOf("/") );
	}
	else {
		bgimg = "";
	}

	$("#hiddenbgimg").val(bgimg);

	var o = {};
    var a = $('#form_graphical_options').serializeArray();

    $.each(a, function() {
        if (o[this.name] !== undefined) {
            if (!o[this.name].push) {
                o[this.name] = [o[this.name]];
            }
            o[this.name].push(this.value || '');
        } else {
            o[this.name] = this.value || '';
        }
    });
	var url = document.URL.split("/edit")[0]+'/savemetadatas';

	var data = { 'metadatas': $.extend({}, o, { "theme": theme, "squelette": squelette, "style": style, "bgimg": bgimg }) };
	console.log(a);
	$.post(url, data, function(data){return;});
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

// on change le theme dynamiquement
$("#changetheme").on('change', function(){ 
	var val = $(this).val();
	// pour vider la liste
	var squelette = $("#changesquelette")[0];
	squelette.options.length=0
	for (var i=0; i<tab1[val].length; i++){
		o = new Option(tab1[val][i],tab1[val][i]);
		squelette.options[squelette.options.length] = o;				
	}
	var style = $("#changestyle")[0];
	style.options.length=0
	for (var i=0; i<tab2[val].length; i++){
		o = new Option(tab2[val][i],tab2[val][i]);
		style.options[style.options.length]=o;				
	}					
});

// on deplace hashcash au bon endroit
$('#hashcash-text').appendTo('#ACEditor .form-actions');