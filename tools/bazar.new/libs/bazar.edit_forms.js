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
 * CVS : $Id: bazar.create_form.js,v 1.10 2011/10/12 14:19:03 mrflos Exp $
 *
 * javascript for editing forms
 *
 *
 * @package bazar
 * @author        Florian Schmitt <florian@outils-reseaux.org>
 * 
 * 
 **/


// fonction pour afficher le formulaire caché adapté au choix du champ à ajouter ou modifier
function afficher_formulaire_champ(valeurschamps, idrow) {
	var field_type = valeurschamps["field_type"];
	var id_boite_personnalise_champ;
	
	//on cache la possibilité d'ajouter un nouveau champs, puisqu'on affiche déja le formulaire des champs
	$('#choose_field_type_to_add').hide();
	
	//cas d'une modification : on se met à l'intérieur de la liste déplacable du champs à modifier
	if (idrow != '') {
		//pour une modification : on cache le champ affiché, et on déplace le formulaire de modification du champ
		$('#' + idrow + ' .control-group').hide();
		var divformchamp = $('#change_field').detach();
		divformchamp.appendTo($('#' + idrow));
		id_boite_personnalise_champ = '#change_field .edit_field';
		$('#change_field').show();
		
	} 
	//sinon c'est un ajout de champs
	else {
		id_boite_personnalise_champ = '#add_field .edit_field';
		$('#add_field').show();
	}

	if (field_type != '') {
		// on charge le bon formulaire, et on cache le choix des champs
		$(id_boite_personnalise_champ).html($('#champs_' + field_type).html());		

		$(id_boite_personnalise_champ).prepend('<input type="hidden" id="field_type" value="'+field_type+'" name="field_type" />');

		// quand on change la valeur dans la liste des sources, on change les valeurs par defaut
		$('#source').on('change', function() {
			/*$('#field_format').children().removeAttr('selected');
			$('#field_format:first-child').attr('selected','selected');
			$('#select_default').html('<option value="" selected="selected">Choisir...</option>');
			$('#checkbox_default').empty();
			$('#field_format_checkbox, #field_format_select').hide();*/
		});

		// quand on choisit un format de type de champs on peut rentrer les valeurs par defaut et autre
		$('#field_format, #source').on('change', function() {			
			var field_format = $('#field_format').val();
			var format_affichage = $('#source option:selected').parent().attr('id');
			var PageWiki = $('#source').val();
			if (format_affichage == 'type_sheet_source') {
				//TODO gerer l'affichage des valeurs par défaut des fiches
			}
			else if (format_affichage == 'list_source') {
				if (field_format == 'select') {
					// on cache les valeurs par défaut d'un autre format
					$('#checkbox_default').empty();
					$('#field_format_checkbox').hide();
					$('#field_format_select').show();
					
					// on formatte la liste des valeurs par defaut avec les champs correspondants a la liste choisie
					$('#select_default').html('<option value="" selected="selected">Choisir...</option>');
					$.each(lists_and_entries.lists[PageWiki], function(id, value) {
						$('#select_default').append('<option value="' + id + '">' + value + '</option>');
					});

					// on compte le nb d'entree et on ajuste les curseurs range
					var nb = $('#select_default option').length - 1;
					$('#select_nb_choices_min, #select_nb_choices_max, #select_size').attr('max', nb);
					$('#select_nb_choices_min, #select_nb_choices_max, #select_size').rangeinput({
						css: { input:  'range', slider: 'range_slider', progress: 'range_progress', handle: 'range_handle'}
					});
				}
				else if (field_format == 'checkbox') {
					// on cache les valeurs par défaut d'un autre format
					$('#select_default').empty();
					$('#field_format_select').hide();
					$('#field_format_checkbox').show();
								
					// on vide le contenu existant
					$('#checkbox_default').empty();

					// on formatte les cases a cocher avec les champs correspondants a la liste choisie
					$.each(lists_and_entries.lists[PageWiki], function(id, value) {
						$('#checkbox_default').append('<input type="checkbox" id="checkbox_default' + id + '" value="1" name="checkbox_default[' + id + ']" class="element_checkbox" /><label for="checkbox_default' + id + '">' + value + '</label>');
					});

					// on compte le nb d'entree et on ajuste les curseurs range
					var nb = $('#checkbox_default input:checkbox').length;
					$('#checkbox_nb_choices_min, #checkbox_nb_choices_max').attr('max', nb);

					$('#checkbox_nb_choices_min, #checkbox_nb_choices_max').rangeinput({
						css: { input:  'range', slider: 'range_slider', progress: 'range_progress', handle: 'range_handle'}
					});
				}
			}
		});

		
		// initialise les tooltips d'aide
		$(id_boite_personnalise_champ + " img.tooltip_aide[title]").each(function() {
			$(this).tooltip({
				effect: 'fade',
				delay: 0,
				predelay: 0,
				position: "top center",
				opacity: 0.9
			});
		});

		// mettre les valeurs par défaut
		for (index in valeurschamps) {	
			if (valeurschamps[index] === 'on' )	{
				$(':checkbox[name=' + index + ']').prop("checked", true);
			} else {
				$('input[name=' + index + ']').val(valeurschamps[index]);
				$('textarea[name=' + index + ']').val(valeurschamps[index]);
				$('select[name=' + index + ']').val(valeurschamps[index]);
			}		
		}

		// menus cachés "parametres avancés"
	    $('.collapse').on('hide', function () {
	   		$(this).prev().find('.arrow').html('&#9658;');
	    }).on('show', function () {
	    	$(this).prev().find('.arrow').html('&#9660;');
	    });

	    // quand on annule l'ajout d'un champ, la personnalisation du champ disparait, et la liste deroulante de choix des champs revient
		$('#add_field .button_cancel_add_field').on('click', function() {		
			$('#add_field .edit_field').html('');
			$('#add_field').hide();
			
			// on cache les erreurs de validation, si présentes
			$('#add_field .error').remove();
			
			// on remet la liste de choix de champs sur 0 et on la 
			$('#choose_field_type').val('0');
			$('#choose_field_type_to_add').show();
			return false;
		});
		
		// quand on annule la modification d'un champ, la personnalisation du champ disparait, et la liste deroulante de choix des champs revient
		$('#change_field .button_cancel_change_field').on('click', function() {		
			$('#change_field .edit_field').html('');
			$('#change_field').hide();
			
			// on cache les erreurs de validation, si présentes
			$('#change_field .error').remove();
			
			// on remet le texte d'origine pour le titre
			$('span.action_champ').text('Ajouter');
			
			// on reaffiche le champs pour prévisualisation
			$(this).parent().parent().parent().find('.form_line').show();
			
			// on remet la liste de choix de champs sur 0 et on l'affiche
			$('#choose_field_type').val('0');
			$('#choose_field_type_to_add').show();
			return false;
		});
		
		// on place le curseur sur le premier champs a saisir
		$(id_boite_personnalise_champ + " input:visible:first").focus();
	}
 }

function generer_affichage_champ_formulaire(mode) {
	if (mode==='add') {
		// on passe les valeurs dans un string pour les exploiter plus tard
		var values = $("#add_field .edit_field input, #add_field .edit_field textarea, #add_field .edit_field select").serialize();
		var idfield = "#add_field .edit_field";
	}
	else if (mode==='change') {
		// on passe les valeurs dans un string pour les exploiter plus tard
		var values = $("#change_field .edit_field input, #change_field .edit_field textarea, #change_field .edit_field select").serialize();
		var idfield = "#change_field .edit_field";
	}

	// on incremente le nombre d elements presents dans le formulaire pour generer les id
	var nb = $("#bazar_form_forms ul.form_fields li").length + 1;
	var choix = ''; var cases_cochees = new Array();

	// cas de l'insertion d'une checkbox : on n'a pas le label a mettre devant mais on prepare la legende
	if ($("#field_type").val() == 'list' && $("#field_format").val() == 'checkbox') {
		$('#checkbox_default input:checked').each(function(index) {
			cases_cochees[index] = $(this).attr('id');
		});
		var champ = '<fieldset id="checkbox' + nb + '" class="bazar_fieldset"><legend>';
		if ($("#checkbox_nb_choices_min").val() > 0) {
			champ += '<span class="required_symbol">*&nbsp;</span>';
		}
		champ += $('#label_liste').val();
		if ($(idfield+" input[name=help_tooltip]").val() != '') {
			champ += '&nbsp;<img class="tooltip_aide" title="' + $(idfield+" input[name=help_tooltip]").val() + '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		}
		champ += '</legend>' + $('#checkbox_default').html() + '</fieldset>';
	}

	// cas de l'insertion d'un fichier
	else if ($("#field_type").val() == 'file') {
		var champ = '<span class="link-title">' + $('#link-title').val() + '</span>';
		if ($('#link-description').val() != '') {
			champ += '<span class="link-description"> : ' + $('#link-description').val()+'</span>';
		}
		champ += '<div class="form_line"><label>';
		if ($("#required:checked").val() == 'on') {
			champ += '<span class="required_symbol">*&nbsp;</span>';
		}
		champ += 'T&eacute;l&eacute;verser le fichier</label><input class="attach_file" type="file" disabled="disabled" /></div>';
	}

	// cas de l'insertion d'une image
	else if ($("#field_type").val() == 'image') {
		var champ = '<span class="image_description">' + $('#image_description').val() + '</span>';
		champ += '<div class="form_line"><label class="attach_file ' + $("#field_type").val() + '">';
		if ($("#required:checked").val() == 'on') {
			champ += '<span class="required_symbol">*&nbsp;</span>';
		}
		champ += 'T&eacute;l&eacute;verser l\'image</label><input class="attach_file" type="file" disabled="disabled" /></div>';
	}

	// cas de l'insertion d'un texte wiki
	else if ($("#field_type").val() == 'wiki') {
		var champ = '';
		if ($('#type_html').val() == 'title') {
			champ += '<h3 class="section_title">' + $("#title_text").val() + '</h3>';
		}
		else if ($('#type_html').val() == 'HTML') {
			champ += '<div class="html_text">' + $("#html_text").val() + '</div>';
		}
		champ += '<em class="appears_for">Apparait pour : ';

		$('#form_field_format input:checked + label').each(function(i) {
			if (i>0) {
				champ +=  ', ' + $(this).text();
			}
			else {
				champ +=  $(this).text();
			}
		});
		champ += '</em>';
	}

	// on prepare le label du formulaire des champs texte, textelong, liste deroulante, fichier, image
	else {
		var champ = '<div class="control-group"><label class="control-label">';

		// on ajoute l'asterisque pour les champs obligatoires
		if ($(idfield+" #required:checked").length > 0) {
			champ += '<span class="required_symbol">*&nbsp;</span>';
		}
		
		// le texte du label
		champ += $(idfield+" input[name=label]").val();

		// on ajoute l'info bulle si elle existe
		if ($(idfield+" input[name=help_tooltip]").val() != '') {
			champ += '&nbsp;<img class="tooltip_aide" title="' + $(idfield+" input[name=help_tooltip]").val() + '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		}

		champ += '</label>';
	}
	champ += '<div class="controls">';

	//insertion d'un champs textelong
	if ($("#field_type").val() == 'textarea') {
		champ += '<textarea class="span3" cols="20" rows="3" disabled="disabled"">' +
			$(idfield+" input[name=default]").val() +
			'</textarea></div>';
	}

	//insertion d'une liste deroulante
	else if ($("#field_type").val() == 'list' && $("#field_format").val() == 'select') {
		choix = $('#select_default:visible').val();
		champ += '<select class="span3" id="select' + nb + '">' + $('#select_default').html() + '</select></div>';
	}

	//insertion d'un champs input
	else {
		champ += '<input class="span3" name="form_name" type="' + $('#field_format').val() + '" value="' +
			$(idfield+" input[name=default]").val() +
			'" disabled="disabled" /></div>';
			$('.updatable-input-text').each(function(){
				$('<option>').val($(idfield+" input[name=id]").val())
							 .text($(idfield+" input[name=label]").val())
							 .insertAfter($(this).find('option:first'));
			});
	}
	champ += '</div>';
	
	// on ajoute les valeurs par defaut et on desactive la saisie pour les listes deroulantes
	$('#select' + nb).val(choix).attr('disabled', true);

	// on ajoute les valeurs par defaut et on desactive la saisie pour les cases a cocher
	for ( var i=0; i<cases_cochees.length; i++ ) {
		$('#'+ cases_cochees[i]).attr('checked', true);
	}
	$('#checkbox' + nb + ' input.element_checkbox').attr('disabled', true);
	
	// on affiche le champ dans la pré-visualisation du formulaire
	if (mode==='add') {
		$('#bazar_form_forms ul.form_fields').append('<li id="row' + nb + '" class="form_line">' +
			'<a href="#" title="D&eacute;placer l\'&eacute;l&eacute;ment" class="handle"></a>' +
			'<a href="#" class="BAZ_lien_supprimer supprimer_champs_formulaire"></a>' +
			'<a href="#" class="BAZ_lien_modifier modifier_champs_formulaire" ></a>' +
			champ + '<input type="hidden" id="field' + nb + '" name="field[' + nb + ']" value="' + values + '" />'+
			'</li>');
	}
	else if (mode==='change') {
		// on récupère l'ancienne ligne cachée du formulaire, a partir de laquelle on peut obtenir le numéro de ligne
		var form_line_edited = $('#change_field').siblings('.control-group:hidden');
		var id_form_line = form_line_edited.parent().attr('id').replace('row', '');
		
		// on replace la valeur du champ caché
		$('#field'+id_form_line).val(values).before(champ);
		
		// on supprime celui caché, que l'on remplace par le nouveau
		form_line_edited.remove();
		
		// on remet le formulaire à sa place
		var change_field = $('#change_field').detach();
		$('#add_field').after(change_field);
	}
	
	// on remet la liste déroulante d'ajout de champ, on cache le reste
	$(idfield).html('');
	$('#add_field, #change_field').hide();
	$('#choose_field_type_to_add').show();
	$('#choose_field_type').val('0');

	return false;
}

$(document).ready(function () {
	// les champs ajoutés sont déplacables
	$("#bazar_form_forms .form_fields").sortable({
		handle : '.handle',
		update : function () {
			// on remet les id dans l'ordre
			$("#bazar_form_forms .form_fields li").each(function(i) {
				$(this).attr('id', 'row'+(i+1));
				$(this).find('input[name^="field"]').attr('id', 'field'+(i+1)).attr('name', 'field['+(i+1)+']');
			});
		}
	});
		
	//on enleve la fonction doubleclic dans le cas d'une page contenant bazar
	$("#bazar_form_forms").bind('dblclick', function(e) {return false;});
		
	// initialise les tooltips d'aide
    $("#bazar_form_forms img.tooltip_aide[title]").each(function() {
    	$(this).tooltip({ 
			effect: 'fade',
			fadeOutSpeed: 100,
			predelay: 0,
			position: "top center",
			opacity: 0.7
    	});
    });
	
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
		'[required]': 'Champ obligatoire'
	});

	
	$.tools.validator.fn(".bazar-select:required", function(input, value) {
		return value != 0 ? true : {     
			en: "Please make a selection",
			fr: "Il faut choisir une option dans la liste d&eacute;roulante."
		};
	});

	//============formulaire de creation de formulaire====================================================

	// quand on choisit un type de champs, le formulaire adequat apparait
	$('#choose_field_type').on('change', function() {
		if ($(this).val() !== '') {
			var tab = new Array();
			tab["field_type"] = $(this).val();
			afficher_formulaire_champ(tab, '');
		}
	});

	// quand on ajoute un champs et qu'il est valide, on renvoie le champ a l'autre formulaire en le previsualisant
	$('#add_field .button_add_field').on("click", function() {
		var thereisanerror = false;
		$("#add_field input:required, #add_field textarea:required, #add_field select:required").each(function() {
			if ($(this).val()=='') {
				thereisanerror = true;
				$(this).parents('.control-group').addClass('error');
				$(this).on('change', function() {$(this).parents('.control-group').removeClass('error');});
			} else {
				$(this).parents('.control-group').removeClass('error');
			}
		});

		if (thereisanerror === false) {
			generer_affichage_champ_formulaire('add');
		}

		return false;	
	});
		
	// quand on modifie un champs et qu'il est valide, on renvoie le champ a l'autre formulaire en le previsualisant
	$('#change_field .button_change_field').on("click", function() {
		// on enleve les champs caches pour la validation
		/*var entrees_cachee_html = $("#type_html_HTML:hidden").detach();
		var entrees_cachee_title = $("#type_html_title:hidden").detach();

		var champajoute = $("#change_field :input, #change_field textarea, #change_field select").validator({
			lang: 'fr',
			offset: [-12, 0],
			position: 'center right',
			message: '<div></div>',
			speed: 0,
			messageClass: 'error form-message'
		});
		// si le formulaire est valide, on envoie les données au formulaire principal, en formatant les données en liste déplacable
		if (champajoute.data("validator").checkValidity()) {

		}
		// le formulaire n'est pas valide, on zoome sur les champs obligatoires
		else {
			$('html, body').animate({scrollTop: $("div.form-message:first").offset().top -50}, 400);
		};
		*/

		var thereisanerror = false;
		$("#change_field input:required, #change_field textarea:required, #change_field select:required").each(function() {
			if ($(this).val()=='') {
				thereisanerror = true;
				$(this).parents('.control-group').addClass('error');
				$(this).on('change', function() {$(this).parents('.control-group').removeClass('error');});
			} else {
				$(this).parents('.control-group').removeClass('error');
			}
		});

		if (thereisanerror === false) {
			/*// on remet les champs caches
			entrees_cachee_html.after('#change_field div.form_line:first');
			entrees_cachee_title.after('#change_field div.form_line:first');*/
			generer_affichage_champ_formulaire('change');
		}


		return false;	
	});
	
	// on modifie une ligne du formulaire prévisualisé
	$('a.modifier_champs_formulaire').on('click', function() {
		// si un autre champs est en cours d'édition, on le ferme et on réaffiche le champs pour prévisualisation
		$('#change_field').siblings('.form_line').show();
		// on cache les erreurs de validation, si présentes
		$('#change_field .error').remove();
		
		// on cache aussi le champ d'ajout
		$('#add_field .edit_field').html('');
		$('#add_field').hide();
		
		// on cache les erreurs de validation, si présentes
		$('#add_field .error').remove();
		
		// on remet la liste de choix de champs sur 0 et on la 
		$('#choose_field_type').val('0');
		
		// on change le titre du formulaire pour comprendre qu'on modifie un champ
		$('span.action_champ').text('Modifier');
		
		// on récupère toutes les parametres du champs dans la variable cachée, et on la décode
		var valeurschamps = $(this).siblings('input[id=^field]').val();
		var tabvalchamps = valeurschamps.split('&');
		var tab = new Array();
		var tabvalchampsdecoup = new Array();
		for (var binome=0; binome<tabvalchamps.length; binome++) {
			tabvalchampsdecoup = tabvalchamps[binome].split('=');
			tab[tabvalchampsdecoup[0]] = decodeURIComponent( tabvalchampsdecoup[1].replace( /\+/g, '%20' ).replace( /\%21/g, '!' ).replace( /\%27/g, "'" ).replace( /\%28/g, '(' ).replace( /\%29/g, ')' ).replace( /\%2A/g, '*' ).replace( /\%7E/g, '~' ) );
		}
		// on affiche le formulaire de modif d'un champ, au bon endroit
		afficher_formulaire_champ(tab, $(this).parent().attr('id'));
		return false;
	});
	
	// on supprime une ligne du formulaire prévisualisé
	$('a.supprimer_champs_formulaire').on('click', function() {
		if (confirm('Etes vous sur de vouloir supprimer ce champ?')) {
			$(this).parents('li.form_line').remove();
			$("#bazar_form_forms .form_fields li").each(function(i) {
				$(this).attr('id', 'row'+(i+1));
			});
		}
		return false;
	});
	/*
	var validateur = $("#bazar_form_forms").validator({
		lang: 'fr',
		offset: [-12, 0],
		position: 'center right',
		message: '<div></div>',
		speed: 0,
		messageClass: 'error form-message'
	});	*/

	// initialise le validateur général à la création du formulaire
	$('.button_save_form').on('click', function() {
		if ($('.form_fields li').length == 0) {
			alert('Vous devez créer au moins un champ texte dans votre formulaire pour pouvoir le sauver.');
			return false;
		}
		if ($('#list_title_generator').val() === 'personalized' && $('#personalized_title').val() === '' ) {
			alert('Le champs titre personnalisé doit contenir quelque chose...');
			return false;
		}
		var thereisanerror = false;
		$("#bazar_form_forms input:required, #bazar_form_forms textarea:required, #bazar_form_forms select:required").each(function() {
			if ($(this).val()=='') {
				thereisanerror = true;
				$(this).parents('.control-group').addClass('error');
				$(this).on('change', function() {$(this).parents('.control-group').removeClass('error');});
			} else {
				$(this).parents('.control-group').removeClass('error');
			}
		});

		if (thereisanerror === false) {
			return true;
		}

		return false;

	});
	
});
