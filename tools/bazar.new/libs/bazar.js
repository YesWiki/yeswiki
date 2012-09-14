/** 
 * +------------------------------------------------------------------------------------------------------+
 * | Copyright (C) 2011 Outils-Reseaux (accueil@outils-reseaux.org)                                       |
 * +------------------------------------------------------------------------------------------------------+
 * | This library is free software; you can redistribute it and/or                                        |
 * | modify it under the terms of the GNU Lesser General Public                                           |
 * | License as published by the Free Software Foundation; either                                         |
 * | version 2.1 of the License, or (at your option) any later version.                                   |
 * |                                                                                                      |
 * | This library is distributed in the hope that it will be useful,                                      |
 * | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
 * | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
 * | Lesser General Public License for more details.                                                      |
 * |                                                                                                      |
 * | You should have received a copy of the GNU Lesser General Public                                     |
 * | License along with this library; if not, write to the Free Software                                  |
 * | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
 * +------------------------------------------------------------------------------------------------------+
 * CVS : $Id: bazar.js,v 1.10 2011/10/12 14:19:03 mrflos Exp $
 *
 * javascript and query tools for Bazar
 *
 *
 * @package bazar
 * @author        Florian Schmitt <florian@outils-reseaux.org>
 * 
 * 
 **/

$(document).ready(function () {
	
	//antispam javascript
	$("input[name=antispam]").val('1');
	
	//on enleve la fonction doubleclic dans le cas d'une page contenant bazar
	$("#bazar_form_entry, #map, #calendar, .accordion").bind('dblclick', function(e) {return false;});
		
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
	$(".BAZ_cadre_fiche, #bazar_form_entry").each(function() {
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
    $("#bazar_form_entry img.tooltip_aide[title]").each(function() {
    	$(this).tooltip({ 
			effect: 'fade',
			fadeOutSpeed: 100,
			predelay: 0,
			position: "top center",
			opacity: 0.7
    	});
    });

    // menus cachés "parametres avancés"
    $('.collapse').on('hide', function () {
   		$(this).prev().find('.arrow').html('&#9658;');
    }).on('show', function () {
    	$(this).prev().find('.arrow').html('&#9660;');
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

	//permet de gerer des affichages conditionnels, en fonction de balises div
	$("select[id^='list_']").each( function() {
		var id = $(this).attr('id');
		id = id.replace("list_", ""); 
		$("div[id^='"+id+"']").hide();
		$("div[id='"+id+'_'+$(this).val()+"']").show();
	});
	$("select[id^='list_']").change( function() {
		var id = $(this).attr('id');
		id = id.replace("list_", ""); 
		$("div[id^='"+id+"']").hide();
		$("div[id='"+id+'_'+$(this).val()+"']").show();
	});
	
	$('.BAZ_rubrique:hidden').parent().show();

	
	//============bidouille pour que les widgets en flash restent en dessous des éléments en survol===========
	$("object").append('<param value="opaque" name="wmode">');$("embed").attr('wmode','opaque');
	
	// initialise les curseurs range pour les champs texte et textelong
	/*$("#nb_char_min, #nb_char_max, #image_max, #thumbnail_max").rangeinput({
		css: { input:  'range', slider: 'range_slider', progress: 'range_progress', handle: 'range_handle'}
	});*/
		
	//============validation formulaire=======================================================================
	$.tools.validator.localize("fr", {
		'*'			: 'Veuillez v&eacute;rifier ces champs',
		':email'  	: 'Entrez un email valide',
		':number' 	: 'Entrez un chiffre exclusivement',
		':url' 		: 'Entrez une adresse URL valide',
		'[max]'	 	: 'La valeur ne peut pas d&eacute;passer $1',
		'[min]'		: 'La valeur doit &ecirc;tre plus grande que $1',
		'[required]'	: 'Champ obligatoire'
	});

	
	$.tools.validator.fn(".bazar-select:required", function(input, value) {
		return value != 0 ? true : {     
			en: "Please make a selection",
			fr: "Il faut choisir une option dans la liste d&eacute;roulante."
		};
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
	/*$(":date").dateinput({ 
		lang: 'fr', 
		format: 'yyyy-mm-dd',
		offset: [0, 0],
		selectors: true,
		speed: 'fast',
		firstDay: 1,
		yearRange: [-70,30]  
	});*/

	// initialise le validateur
	$('.bouton_save_entry').click(function() {
		var validateur = $("#bazar_form_entry").validator({
			lang: 'fr',
			offset: [-12, 0],
			position: 'center right',
			message: '<div></div>',
			speed: 0,
			messageClass: 'error form-message'
		});	
		if (validateur.data("validator").checkValidity() == false) {
			//On zoome sur les champs obligatoires
			$('html, body').animate({
				scrollTop: $("div.form-message:first").offset().top -50
			}, 400);
		}
	});

});
