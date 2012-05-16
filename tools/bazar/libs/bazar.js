
/** 
 * 
 * javascript and query tools for Bazar
 * 
 * */

function addFormField() {
	var nb = $("#formulaire .valeur_liste input.input_liste_label[name^='label']").length + 1;
	$("#formulaire .valeur_liste").append('<li class="liste_ligne" id="row'+nb+'">'+
			'<a href="#" title="D&eacute;placer l\'&eacute;l&eacute;me,t" class="handle"></a>'+
			'<input type="text" name="id['+nb+']" class="input_liste_id" />' +
			'<input type="text" name="label['+nb+']" class="input_liste_label" />' +
			'<a href="#" class="BAZ_lien_supprimer suppression_label_liste"></a>'+
			'</li>');
	$("#formulaire input.input_liste_id[name='id["+nb+"]']").focus();
}

function removeFormField(id) {
	var nb = $("#formulaire .valeur_liste input.input_liste_label[name^='label']").length;
	if (nb > 1) {
		var nom = 'a_effacer_' + $(id).find("input:hidden").attr('name');
		$(id).find("input:hidden").attr('name', nom).appendTo("#formulaire");
		$(id).remove();
		$("#formulaire .valeur_liste input.input_liste_label[name^='label']").each(function(i) {
			$(this).attr('name', 'label['+(i+1)+']').
			parent('.liste_ligne').attr('id', 'row'+(i+1)).
			find("input:hidden").attr('name', 'ancienlabel['+(i+1)+']');
		});
	} else {
		alert('Le dernier élément ne peut être supprimé.');
	}
}
 
 
$(document).ready(function () {
	//accordeon pour bazarliste
	$(".titre_accordeon").bind('click',function() {
		//$(this).next("div.pane").slideToggle('fast');
		if ($(this).hasClass('current')) {
			$(this).removeClass('current');
			$(this).next("div.pane").hide();
		} else { 
			$(this).addClass('current');
			$(this).next("div.pane").show();
		}
	});
	
	//antispam javascript
	$("input[name=antispam]").val('1');
	
	
	
	//formulaire des formulaires
	$(".ajout_champs_formulaire").overlay({
		expose:			'black',
		effect:			'apple',
		oneInstance:	true,
		closeOnClick:	false,
		onBeforeLoad: function() {$('#champs_formulaire .titre_overlay').html('Ajouter un nouveau champs au formulaire');}
	});
	
	$('a.bouton_annuler_formulaire').click(function() { 
		$(".ajout_champs_formulaire").overlay().close(); 
		return false;
	});
	
	
	
	//carto google
	var divcarto = document.getElementById("map" )
	if (divcarto) {	initialize(); }
	// clic sur le lien d'une fiche, l'ouvre sur la carto
	$("#markers a").live("click", function(){
		var i = $(this).attr("rel");
		// this next line closes all open infowindows before opening the selected one
		for(x=0; x < arrInfoWindows.length; x++){ arrInfoWindows[x].close(); }
		arrInfoWindows[i].open(map, arrMarkers[i]);
		$('ul.css-tabs li').remove();
		$("fieldset.tab").each(function(i) {
						$(this).parent('div.BAZ_cadre_fiche').prev('ul.css-tabs').append("<li class='liste" + i + "'><a href=\"#\">"+$(this).find('legend:first').hide().html()+"</a></li>");
		});
		$("ul.css-tabs").tabs("fieldset.tab", { onClick: function(){} } );
	});
	
	//tabulations (transforme les fieldsets de classe tab en tabulation)
	$(".BAZ_cadre_fiche, #formulaire").each(function() {
		//nb de tabs par fiche
		var nbtotal = $(this).children("fieldset.tab").size() - 1;
		
		//on ajoute le nom des tabs à partir de la legende du fieldset
		$(this).children("fieldset.tab:first").before("<ul class='css-tabs'></ul>");
		$(this).children("fieldset.tab").each(function(i) {
			$(this).addClass("tab" + i)
			if (i==0)
			{
				$(this).append('<a class="btn next-tab">Suivant &raquo;</a>');
			}
			else if (i==nbtotal)
			{
				$(this).append('<a class="btn prev-tab">&laquo; Pr&eacute;c&eacute;dent</a>');
			}
			else
			{
				$(this).append('<a class="btn prev-tab">&laquo; Pr&eacute;c&eacute;dent</a><a class="btn next-tab">Suivant &raquo;</a>');
			}
			$(this).prevAll('ul.css-tabs').append("<li class='liste" + i + "'><a href=\"#\">"+$(this).find('legend:first').hide().html()+"</a></li>");
		});
	});
	//initialise tabulations
	if ($("ul.css-tabs").size() > 1)
	{
		$("ul.css-tabs").tabs("> .tab", { onClick: function(){if (divcarto) {	initialize(); }} } );
	} 
	else if ($("ul.css-tabs").size() == 1)
	{
		$("ul.css-tabs").tabs("fieldset.tab", { onClick: function(){if (divcarto) {	initialize(); }} } );
	}
	var api = $("ul.css-tabs").data("tabs");
	// "next tab" button
	$("a.next-tab").live('click',function() {
		api.next();
		$('ul.css-tabs').scrollTo();
		return false;
	});

	// "previous tab" button
	$("a.prev-tab").live('click',function() {
		api.prev();
		$('ul.css-tabs').scrollTo();
		return false;
	});
	
	// initialise les tooltips d'aide
    $("img.tooltip_aide[title]").each(function() {
    	$(this).tooltip({ 
			effect: 'fade',
			fadeOutSpeed: 100,
			predelay: 0,
			position: "top center",
			opacity: 0.7
    	});
    });

    // initialise les iframe en overlay
    $("a.ouvrir_overlay[rel]").each(function() {
    	$(this).overlay({
			expose:			'black',
			effect:			'apple',
			oneInstance:	true,
			closeOnClick:	false,
			onBeforeLoad: function() {
				//on transforme le lien avecle handler /iframe, pour le charger dans une fenetre overlay
				var overlay_encours = this;
				var lien = overlay_encours.getTrigger().attr("href");
				//alert(lien + ' .BAZ_cadre_fiche');
				$("#overlay-link div.contentWrap").load(lien + ' #BAZ_menu');
			}		
		});
    });
    
    //liste oui / non conditionnelle
	$("select[id^='liste12'], select[id^='liste1']").change( function() {
		if ($(this).val()==1) {
			$(this).parents(".formulaire_ligne").next("div[id^='oui']").show();
			$(this).parents(".formulaire_ligne").next("div[id^='non']").hide();
		}
		if ($(this).val()==2) {
			$(this).parents(".formulaire_ligne").next("div[id^='non']").show();
			$(this).parents(".formulaire_ligne").next("div[id^='oui']").hide();
		}
	});
	//a l'ouverture du formulaire, on affiche 
	$(".BAZ_cadre_fiche div[id^='oui'], .BAZ_cadre_fiche div[id^='non']").show();
	$("#formulaire select[id^='liste12'], #formulaire select[id^='liste1']").each(function() {
		if ($(this).val()==1) {
			$(this).parents(".formulaire_ligne").next("div[id^='oui']").show();
			$(this).parents(".formulaire_ligne").next("div[id^='non']").hide();
		}
		if ($(this).val()==2) {
			$(this).parents(".formulaire_ligne").next("div[id^='non']").show();
			$(this).parents(".formulaire_ligne").next("div[id^='oui']").hide();
		}
	});
	

	//on enleve la fonction doubleclic dans le cas d'une page contenant bazar
	$("#formulaire, #map, #calendar, .accordion").bind('dblclick', function(e) {return false;});

	//=====================galerie d'images==================================================================	
	//création des overlay pour les images
    $("body").append("<div class=\"overlay\" id=\"overlay_bazar\"><div class=\"contentWrap_bazar\"></div></div>");

	$('a.triggerimage[rel="#overlay_bazar"]').overlay({
		mask:'#999', effect: 'apple',
		onBeforeLoad: function() {

			// grab wrapper element inside content
			var wrap = this.getOverlay().find('.contentWrap_bazar');

			// load the page specified in the trigger
			wrap.html('<img src='+this.getTrigger().attr("href")+' alt="image" />');
		}

	});

	//permet de gerer des affichages conditionnels, en fonction de balises div
	$("select[id^='liste']").each( function() {
		var id = $(this).attr('id');
		id = id.replace("liste", ""); 
		$("div[id^='"+id+"']").hide();
		$("div[id='"+id+'_'+$(this).val()+"']").show();
	});
	$("select[id^='liste']").change( function() {
		var id = $(this).attr('id');
		id = id.replace("liste", ""); 
		$("div[id^='"+id+"']").hide();
		$("div[id='"+id+'_'+$(this).val()+"']").show();
	});
	
	$('.BAZ_rubrique:hidden').parent().show();

	
	//============bidouille pour que les widgets en flash restent en dessous des éléments en survol===========
	$("object").append('<param value="opaque" name="wmode">');$("embed").attr('wmode','opaque');
	
	
	//============validation formulaire=======================================================================
	//============gestion des dates=======================================================================
	//traduction francaise
	$.tools.dateinput.localize("fr",  {
	   months:        'janvier,f&eacute;vrier,mars,avril,mai,juin,juillet,ao&ucirc;t,' +
						'septembre,octobre,novembre,d&eacute;cembre',
	   shortMonths:   'jan,f&eacute;v,mar,avr,mai,jun,jul,ao&ucirc;,sep,oct,nov,d&eacute;c',
	   days:          'dimanche,lundi,mardi,mercredi,jeudi,vendredi,samedi',
	   shortDays:     'dim,lun,mar,mer,jeu,ven,sam'
	});

	// dateinput initialization. the language is specified with lang- option
	$("input[type=date]").dateinput({ 
		lang: 'fr', 
		format: 'yyyy-mm-dd',
		offset: [0, 0],
		selectors: true,
		speed: 'fast',
		firstDay: 1,
		yearRange: [-70,30]  
	});

	$.tools.validator.localize("fr", {
		'*'			: 'Veuillez vérifier ces champs',
		':email'  	: 'Entrez un email valide',
		':number' 	: 'Entrez un chiffre exclusivement',
		':url' 		: 'Entrez une adresse URL valide',
		'[max]'	 	: 'La valeur ne peut pas dépasser $1',
		'[min]'		: 'La valeur doit être plus grande que $1',
		'[required]'	: 'Champ obligatoire'
	});

	
	$.tools.validator.fn(".bazar-select:required", function(input, value) {
		return value != 0 ? true : {     
			en: "Please make a selection",
			fr: "Il faut choisir une option dans la liste d&eacute;roulante."
		};
	});
	
	//validation formulaire de saisie
	var inputsreq = $("#formulaire input[required=required], #formulaire select[required=required], #formulaire textarea[required=required]");
	
	$('.bouton_sauver').click(function() {	
		
		var atleastonefieldnotvalid = false;
		var atleastonemailfieldnotvalid = false;
		var atleastoneurlfieldnotvalid = false;
		var atleastonecheckboxfieldnotvalid = false;
				
		// il y a des champs requis, on teste la validite champs par champs
		if (inputsreq.length > 0) {		
			inputsreq.each(function() {
				if ( !($(this).val().length === 0 || $(this).val() === '' || $(this).val() === '0')) {
					$(this).removeClass('invalid');
				} else {
					atleastonefieldnotvalid = true;
					$(this).addClass('invalid');
				}
			});
		}
		
		// les emails
		$('#formulaire input[type=email]').each(function() {
			var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
			var address = $(this).val();
			if(reg.test(address) == false) {
				atleastonemailfieldnotvalid = true;
				$(this).addClass('invalid');
			} else {
				$(this).removeClass('invalid');
			}
		});
		
		// les urls
		$('#formulaire input[type=url]').each(function() {
			var reg = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
			var url = $(this).val();
			if(reg.test(url) == false) {
				atleastoneurlfieldnotvalid = true;
				$(this).addClass('invalid');
			} else {
				$(this).removeClass('invalid');
			}
		});
		
		// les checkbox chk_required
		$('#formulaire fieldset.chk_required').each(function() {
			var nbchkbox = $(this).find(':checked');
			if(nbchkbox.length === 0) {
				atleastonecheckboxfieldnotvalid = true;
				$(this).addClass('invalid');
			} else {
				$(this).removeClass('invalid');
			}
		});
		
		if (atleastonefieldnotvalid === true) {
			alert('Veuillez saisir tous les champs obligatoires (avec une astérisque rouge)');
			//on remonte en haut du formulaire
			$('html, body').animate({scrollTop: $("#formulaire .invalid").offset().top - 80}, 800);
		}
		else if (atleastonemailfieldnotvalid === true) {
			alert('L\'email saisi n\'est pas valide');
			//on remonte en haut du formulaire
			$('html, body').animate({scrollTop: $("#formulaire .invalid").offset().top - 80}, 800);
		}
		else if (atleastoneurlfieldnotvalid === true) {
			alert('L\'url saisie n\'est pas valide, elle doit commencer par http:// et ne pas contenir d\'espaces ou caracteres speciaux');
			//on remonte en haut du formulaire
			$('html, body').animate({scrollTop: $("#formulaire .invalid").offset().top - 80}, 800);
		}
		else if (atleastonecheckboxfieldnotvalid === true) {
			alert('Il faut cocher au moins une case a cocher');
			//on remonte en haut du formulaire
			$('html, body').animate({scrollTop: $("#formulaire .invalid").offset().top - 80}, 800);
		}
		else {
			$("#formulaire").submit();
		}
		
		return false; 
	});
	
	//on change le look des champs obligatoires en cas de saisie dedans
	inputsreq.keypress(function(event) {
		if ( !($(this).val().length === 0 || $(this).val() === '' || $(this).val() === '0')) {
			$(this).removeClass('invalid');
		} else {
			atleastonefieldnotvalid = true;
			$(this).addClass('invalid');
		}
	});
	//on change le look des champs obligatoires en cas de changement de valeur
	inputsreq.change(function(event) {
		if ( !($(this).val().length === 0 || $(this).val() === '' || $(this).val() === '0')) {
			$(this).removeClass('invalid');
		} else {
			atleastonefieldnotvalid = true;
			$(this).addClass('invalid');
		}
	});

	
	//pour la gestion des listes, on peut rajouter dynamiquement des champs
	$('.ajout_label_liste').live('click', function() {addFormField();return false;});
	$('.suppression_label_liste').live('click', function() {removeFormField('#'+$(this).parent('.liste_ligne').attr('id'));return false;});

	$('#formulaire').removeAttr('onsubmit');

});
