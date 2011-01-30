/**
 * jQuery.ScrollTo - Easy element scrolling using jQuery.
 * Copyright (c) 2007-2009 Ariel Flesler - aflesler(at)gmail(dot)com | http://flesler.blogspot.com
 * Dual licensed under MIT and GPL.
 * Date: 5/25/2009
 * @author Ariel Flesler
 * @version 1.4.2
 *
 * http://flesler.blogspot.com/2007/10/jqueryscrollto.html
 */
;(function(d){var k=d.scrollTo=function(a,i,e){d(window).scrollTo(a,i,e)};k.defaults={axis:'xy',duration:parseFloat(d.fn.jquery)>=1.3?0:1};k.window=function(a){return d(window)._scrollable()};d.fn._scrollable=function(){return this.map(function(){var a=this,i=!a.nodeName||d.inArray(a.nodeName.toLowerCase(),['iframe','#document','html','body'])!=-1;if(!i)return a;var e=(a.contentWindow||a).document||a.ownerDocument||a;return d.browser.safari||e.compatMode=='BackCompat'?e.body:e.documentElement})};d.fn.scrollTo=function(n,j,b){if(typeof j=='object'){b=j;j=0}if(typeof b=='function')b={onAfter:b};if(n=='max')n=9e9;b=d.extend({},k.defaults,b);j=j||b.speed||b.duration;b.queue=b.queue&&b.axis.length>1;if(b.queue)j/=2;b.offset=p(b.offset);b.over=p(b.over);return this._scrollable().each(function(){var q=this,r=d(q),f=n,s,g={},u=r.is('html,body');switch(typeof f){case'number':case'string':if(/^([+-]=)?\d+(\.\d+)?(px|%)?$/.test(f)){f=p(f);break}f=d(f,this);case'object':if(f.is||f.style)s=(f=d(f)).offset()}d.each(b.axis.split(''),function(a,i){var e=i=='x'?'Left':'Top',h=e.toLowerCase(),c='scroll'+e,l=q[c],m=k.max(q,i);if(s){g[c]=s[h]+(u?0:l-r.offset()[h]);if(b.margin){g[c]-=parseInt(f.css('margin'+e))||0;g[c]-=parseInt(f.css('border'+e+'Width'))||0}g[c]+=b.offset[h]||0;if(b.over[h])g[c]+=f[i=='x'?'width':'height']()*b.over[h]}else{var o=f[h];g[c]=o.slice&&o.slice(-1)=='%'?parseFloat(o)/100*m:o}if(/^\d+$/.test(g[c]))g[c]=g[c]<=0?0:Math.min(g[c],m);if(!a&&b.queue){if(l!=g[c])t(b.onAfterFirst);delete g[c]}});t(b.onAfter);function t(a){r.animate(g,j,b.easing,a&&function(){a.call(this,n,b)})}}).end()};k.max=function(a,i){var e=i=='x'?'Width':'Height',h='scroll'+e;if(!d(a).is('html,body'))return a[h]-d(a)[e.toLowerCase()]();var c='client'+e,l=a.ownerDocument.documentElement,m=a.ownerDocument.body;return Math.max(l[h],m[h])-Math.min(l[c],m[c])};function p(a){return typeof a=='object'?a:{top:a,left:a}}})(jQuery);


//JQuery Unserialize v1.0 by James Campbell
(function($){
$.unserialize = function(Data){
        var Data = Data.split("&");
        var Serialised = new Array();
        $.each(Data, function(){
            var Properties = this.split("=");
            Serialised[Properties[0]] = Properties[1];
        });
        return Serialised;
    };
})(jQuery);


/** 
 * 
 * javascript and query tools for Bazar
 * 
 * */
$(document).ready(function () {
	
	
	//creation des overlay pour bazar
    $('#container').before('<div id="overlay-bazar" class="yeswiki-overlay" style="display:none"></div>');
	
	//antispam javascript
	$("input[name=antispam]").val('1');
	
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
		
		//on ajoute le nom des tabs a partir de la legende du fieldset
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
    
  //accordeon pour bazarliste
	$(".accordion h2.titre_accordeon").bind('click',function() {
		$(this).next("div.pane").slideToggle('fast');
		if ($(this).hasClass('current')) {
			$(this).removeClass('current');
		} else { 
			$(this).addClass('current');
		}
	});
	
	//permet de cliquer sur les liens d'edition sans derouler l'accordeon 
	$(".accordion .liens_titre_accordeon").bind('click',function(event) {
		event.stopPropagation();
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
				var overlay_encours = this
				var lien = overlay_encours.getTrigger().attr("href");
				result = lien.match(/\/iframe/i); 
				if (!result) { lien = lien.replace(/wiki=([a-z0-9]+)&/ig, 'wiki=$1/iframe&', 'g'); }
				$("#overlay div.contentWrap").html('<iframe class="wikiframe" width="630" height="480" frameborder="0" src="' + lien + '"></iframe>');
				//dans la frame, on change le fonctionnement des boutons annuler et sauver, pour retourner comme il faut dans la page de modification principale
				var myFrame = $('#overlay .wikiframe');
				myFrame.load(function() { 
					var contenu_iframe = myFrame.contents();
					contenu_iframe.find('.bouton_annuler').click(function(event) {
						event.preventDefault();
						overlay_encours.close(); 
						return false;
					});
					contenu_iframe.find('input.bouton_sauver').click(function(event) {
						//event.preventDefault();
						//return false;
					});
				});
				
			}		
		});
    });

	//on enleve la fonction doubleclic dans le cas d'une page contenant bazar
	$("#formulaire, #map, #calendar, .accordion").bind('dblclick', function(e) {return false;});

	//permet de gerer des affichages conditionnels quand on choisit une valeur dans une liste deroulante, en fonction de balises div
	$("#formulaire select, #champs_formulaire select").live('change', function() {
		var id = $(this).attr('id');
		$("div[id^='"+id+"']").hide();
		$("div[id='"+id+'_'+$(this).val()+"']").show();
	});
	
//============bidouille pour que les widgets en flash restent en dessous des elements en survol===========
	$("object").append('<param value="opaque" name="wmode">');$("embed").attr('wmode','opaque');
	
	
//============validation formulaire=======================================================================
	$("#formulaire").removeAttr('onSubmit').validator({lang: 'fr', offset: [-10, 10]}).bind("onFail", function(e, errors)  {
		if (e.originalEvent.type == 'submit') {
			
			// loop through Error objects and add the border color
			$.each(errors, function()  {
				var input = this.input;
				input.css({borderColor: 'red'}).focus(function()  {
					input.css({borderColor: '#999'});
				});
			});
			
			//on remonte en haut du formulaire
			$('html, body').animate({scrollTop: $(this).offset().top - 50}, 800);
		}
	});
	
	$.tools.validator.localize("fr", {
		'*'			: 'Veuillez v&eacute;rifier ces champs',
		':email'  	: 'Entrez un email valide',
		':number' 	: 'Entrez un chiffre exclusivement',
		':url' 		: 'Entrez une adresse URL valide',
		'[max]'	 	: 'La valeur ne peut pas d&eacute;passer $1',
		'[min]'		: 'La valeur doit &ecirc;tre plus grande que $1',
		'[required]'	: 'Champs obligatoire'
	});

	
	$.tools.validator.fn(".bazar-select", function(input, value) {
		return value != 0 ? true : {     
			en: "Please make a selection",
			fr: "Il faut choisir une option dans la liste d&eacute;roulante."
		};
	});
	

	//============formulaire de creation de formulaire====================================================

	
	// validator
	$("#champs_formulaire").validator({
		lang: 'fr', 
		offset: [-10, 10],
		position: 'center right'
	});

	// initialise les barres slide (input range)
	$("#form_type_champs :range").rangeinput({
		css: { input:  'range', slider: 'range_slider', progress: 'range_progress', handle: 'range_handle'}
	});
	
	// initialise les tooltips d'aide
    $("#form_type_champs img.tooltip_aide[title]").each(function() {
    	$(this).tooltip({ 
			effect: 'fade',
			fadeOutSpeed: 100,
			predelay: 0,
			position: "top center",
			opacity: 0.7
    	});
    });

	// pour les listes et checkbox, il faut recuperer en json toutes les listes et formulaires
	/*var listes_et_fiches = $.ajax({
	      url: "wakka.php?wiki=BazaR/json&demand=listes_et_fiches",
	      dataType: "json",
	      async:false
	   }
	).responseText;*/
    var listes_et_fiches;
    
	// overlay with masking. when overlay is loaded or closed we hide error messages if needed
	$(".ajout_champs_formulaire").overlay({
		oneInstance:	true,
		closeOnClick:	false,
		mask: {color: '#000', loadSpeed:0, opacity: 0.7},
		onBeforeLoad: function() {
			
			$('#champs_formulaire').appendTo(this.getOverlay()).show();
			
			//on ajoute un titre
			$('#champs_formulaire .titre_overlay').html('Ajouter un nouveau champs au formulaire');
			
			//quand on choisit un type de champs, le formulaire adequat apparait
			$('#type_champs_formulaire').live('change', function() {
				var val_type_champs = $(this).val();
				if (val_type_champs == 0) {
					$('#form_type_champs').html('<div class="clear"></div>');
				} else {
					$('#form_type_champs').load('tools/bazar/presentation/squelettes/champs_'+val_type_champs+'.tpl.html', function() {
						
						if (val_type_champs == 'liste') {				
							// pour les listes et checkbox, il faut recuperer en json toutes les listes et formulaires
							$.getJSON("wakka.php?wiki=BazaR/json&demand=listes_et_fiches", function(data) {
								
								listes_et_fiches = data;
								
								// on recupere les listes
								$.each(listes_et_fiches.listes, function(PageWikiListe, value) {
									$('#list_source').append('<option value="' + PageWikiListe + '">' + value.titre_liste + '</option>');
								});
								
								//on recupere les types de fiches
								$.each(listes_et_fiches.fiches, function(Categorie, value) {
									$.each(value, function(PageWikiTypeFiche, DataTypeFiche) {
										$('#type_sheet_source').append('<option value="' + PageWikiTypeFiche + '">' + DataTypeFiche.bn_label_nature + '</option>');
									});
								});
							});
						}
						
						// quand on change la valeur dans la liste des sources, on vide la liste format et les valeurs par defaut
						$('#source').live('change', function() {
							$('#type_champs').children().removeAttr('selected');
							$('#type_champs:first-child').attr('selected','selected');
							$('#select_default').html('<option value="" selected="selected">Choisir...</option>');	
							$('#checkbox_default').empty();
							$('#type_champs_checkbox, #type_champs_select').hide();							
						});
						
						// quand on choisit un format de type de champs on peut rentrer les valeurs par defaut et autre
						$('#type_champs').live('change', function() {
							
							var format_type_champs = $(this).val();
							var format_affichage = $('#source option:selected').parent().attr('id');
							var PageWiki = $('#source').val();
							
							if (format_affichage == 'type_sheet_source') {
								alert('Les fiches ne marchent pas encore, stay tuned!');
							}
							else if (format_affichage == 'list_source') {
								if (format_type_champs == 'select') {
									
									// on formatte la liste des valeurs par defaut avec les champs correspondants a la liste choisie
									$('#select_default').html('<option value="" selected="selected">Choisir...</option>');										
									$.each(listes_et_fiches.listes[PageWiki].label, function(id, value) {
										$('#select_default').append('<option value="' + id + '">' + value + '</option>');
									});
									
									// on compte le nb d'entree et on ajuste les curseurs range
									var nb = $('#select_default option').length - 1;
									$('#select_nb_choices_min, #select_nb_choices_max, #select_size').attr('max', nb);
									$('#select_nb_choices_min, #select_nb_choices_max, #select_size').rangeinput({
										css: { input:  'range', slider: 'range_slider', progress: 'range_progress', handle: 'range_handle'}
									});
								}
								else if (format_type_champs == 'checkbox') {
									
									//on vide le contenu existant
									$('#checkbox_default').empty();
									
									// on formatte les cases a cocher avec les champs correspondants a la liste choisie										
									$.each(listes_et_fiches.listes[PageWiki].label, function(id, value) {
										$('#checkbox_default').append('<input type="checkbox" id="checkbox_default' + id + '" value="1" name="checkbox_default[' + id + ']" class="element_checkbox" /><label for="checkbox_default' + id + '">' + value + '</label>');
									});
									
									// on compte le nb d'entree et on ajuste les curseurs range
									var nb = $('#checkbox_default input:checkbox').length;
									$('#checkbox_nb_choices_min, #checkbox_nb_choices_max').attr('max', nb);
									//$('#form_type_champs .range_slider').remove();									
									$('#checkbox_nb_choices_min, #checkbox_nb_choices_max').rangeinput({
										css: { input:  'range', slider: 'range_slider', progress: 'range_progress', handle: 'range_handle'}
									});
								}
							} 
						});
						
						// gestion de la taille des selects					
						$('#select_size').live('change', function() {
							var size = $(this).val();
							if (size == 1) {
								$('#select_default').removeAttr('multiple');
							} else {
								$('#select_default').attr('multiple', 'multiple');
							}
							$('#select_default').attr('size', size);
							
						});
						
						// initialise les curseurs range pour les champs texte et textelong
						$("#nb_char_min, #nb_char_max").rangeinput({
							css: { input:  'range', slider: 'range_slider', progress: 'range_progress', handle: 'range_handle'}
						});
						
						// initialise les tooltips d'aide
					    $("#form_type_champs img.tooltip_aide[title]").each(function() {
					    	$(this).tooltip({ 
								effect: 'fade',
								fadeOutSpeed: 100,
								predelay: 0,
								position: "top center",
								opacity: 0.7
					    	});
					    });
					    
					    //validator
						var validateur = $("#champs_formulaire").validator({
							lang: 'fr', 
							offset: [-10, 10],
							position: 'center right'
						});
					
					    $(this).append('<div class="clear"></div>');
					});
				}
			});
		},
		onBeforeClose: function(e) {
			//on vide le contenu de l'overlay type de champs, on le réinitialise puis on le déplace
			$('#form_type_champs').empty();
			$('#type_champs_formulaire').val('0');
			$(".error").hide();
			$('#champs_formulaire').insertAfter('#formulaire').hide();
		}
	});
	
	$('.bouton_ajouter_formulaire').bind("click", function(e){
		
		var validateur = $("#champs_formulaire").validator({
			lang: 'fr', 
			offset: [-10, 10],
			position: 'center right'
		});
		
		// si le formulaire  est valide, on envoie les données au formulaire principal, en formatant les donnees en liste deplacable
		if (validateur.data("validator").checkValidity()) {
			var values = $("#champs_formulaire").serialize();
			
			var champ = '<div class="champ_formulaire_ligne"><label>';
			if ($("#champs_formulaire input[name=required]:checkbox").val() == 'on') {
				champ += '<span class="symbole_obligatoire">*&nbsp;</span>';
			}
			champ += $("#champs_formulaire input[name=label]").val();
			
			if ($("#champs_formulaire input[name=help_tooltip]").val() != '') {
				champ += '&nbsp;<img class="tooltip_aide" title="' + $("#champs_formulaire input[name=help_tooltip]").val() + '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
			}
			
			champ += '</label>';
			
			if ($("#champs_formulaire select[name=type_champs_formulaire]").val() == 'texte') {
				champ += '<input class="input_texte" name="bn_label_nature" type="text" value="' +
							$("#champs_formulaire input[name=default]").val() + 
							'" disabled="disabled" />';
			}
			else if ($("#champs_formulaire select[name=type_champs_formulaire]").val() == 'textelong') {
				champ += '<textarea class="input_textarea" cols="20" rows="3" disabled="disabled"">' +
							$("#champs_formulaire input[name=default]").val() + 
							'</textarea>';
			}
			
			champ += '</div>';
			
			
			var nb = $("#formulaire ul.valeur_formulaire li").length + 1;
			$('#formulaire ul.valeur_formulaire').append('<li id="row' + nb + '" class="ligne_champs_formulaire">' +
					'<a href="#" title="D&eacute;placer l\'&eacute;l&eacute;ment" class="handle"></a>' +
					'<a href="#" class="BAZ_lien_supprimer supprimer_champs_formulaire"></a>' +
					'<a href="#" class="BAZ_lien_modifier modifier_champs_formulaire" rel="#overlay-bazar" ></a>' +
					champ +
					'<input type="hidden" id="champ' + nb + '" value="' + values + '" />'+	
					'<div class="clear"></div></li>');
			$(".ajout_champs_formulaire").overlay().close(); 
			return false;
		};
		
		return false;
    });
	

	//quand on annule, l'overlay disparait
	$('a.bouton_annuler_formulaire').live('click', function() { 
		$(".ajout_champs_formulaire").overlay().close(); 
		return false;
	});
	
	//on supprime la ligne du champs formulaire
	$('a.supprimer_champs_formulaire').live('click', function() { 
		$(this).parents('li.ligne_champs_formulaire').remove();
		$("#formulaire .valeur_formulaire li").each(function(i) {
			$(this).attr('id', 'row'+(i+1));
		});
		return false;
	});

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
	$("#formulaire :date").dateinput({ 
		lang: 'fr', 
		format: 'yyyy-mm-dd',
		offset: [0, 0],
		selectors: true,
		speed: 'fast',
		firstDay: 1     
	});	
	
	// initialise les barres slide (input range)
	$("#formulaire :range").rangeinput({
		css: { input:  'range', slider: 'range_slider', progress: 'range_progress', handle: 'range_handle'}
	});
	
	
	
});



//fonction pour faire des polygones
function createPolygon(coords, color) {
		return new google.maps.Polygon({
			paths: coords,
			strokeColor: "black",
			strokeOpacity: 0.8,
			strokeWeight: 1,
			fillColor: color,
			fillOpacity: 0.4
		});
}

jQuery.fn.limitMaxlength = function(options){

	  var settings = jQuery.extend({
	    attribute: "maxlength",
	    onLimit: function(){},
	    onEdit: function(){}
	  }, options);
	  
	  // Event handler to limit the textarea
	  var onEdit = function(){
	    var textarea = jQuery(this);
	    var maxlength = parseInt(textarea.attr(settings.attribute));

	    if(textarea.val().length > maxlength){
	      textarea.val(textarea.val().substr(0, maxlength));
	      
	      // Call the onlimit handler within the scope of the textarea
	      jQuery.proxy(settings.onLimit, this)();
	    }
	    
	    // Call the onEdit handler within the scope of the textarea
	    jQuery.proxy(settings.onEdit, this)(maxlength - textarea.val().length);
	  }

	  this.each(onEdit);

	  return this.keyup(onEdit)
	        .keydown(onEdit)
	        .focus(onEdit);
	}

	$(document).ready(function(){
	  
	  var onEditCallback = function(remaining){
	    $(this).parents(".formulaire_ligne").find('.charsRemaining').text(" (" + remaining + " caracteres restants)");
	    
	    if(remaining > 0){
	      $(this).css('background-color', 'white');
	    }
	  }
	  
	  var onLimitCallback = function(){
	    $(this).css('background-color', 'red');
	  }
	  
	  $('textarea[maxlength]').limitMaxlength({
	    onEdit: onEditCallback,
	    onLimit: onLimitCallback,
	  });
	  
	  //pour la gestion des listes, on peut rajouter dynamiquement des champs
	  $('.ajout_label_liste').live('click', function() {addFormField();return false;});
	  $('.suppression_label_liste').live('click', function() {removeFormField('#'+$(this).parent('.liste_ligne').attr('id'));return false;});
	});


/*****************************************************************************************************/	
//deplacement des listes
/*****************************************************************************************************/
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
			alert('Le dernier element ne peut etre supprime.');
		}
	}
