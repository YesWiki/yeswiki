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
 * CVS : $Id: bazar.edit_lists.js,v 1.10 2011/10/12 14:19:03 mrflos Exp $
 *
 * javascript for editing lists
 *
 *
 * @package bazar
 * @author        Florian Schmitt <florian@outils-reseaux.org>
 * 
 * 
 **/

$(document).ready(function () {
	// on rend les listes déplacables
	$(".valeur_liste").sortable({
		handle : '.handle_liste',
		update : function () {
			$("#bazar_form_lists .valeur_liste input[name^='label']").each(function(i) {
				$(this).attr('name', 'label['+(i+1)+']').
				prev().attr('name', 'id['+(i+1)+']').
				parent('.liste_ligne').attr('id', 'row'+(i+1)).
				find("input:hidden").attr('name', 'ancienlabel['+(i+1)+']');
			});
		}
	});

	// pour la gestion des listes, on peut rajouter dynamiquement des champs
	$('.ajout_label_liste').live('click', function() {	
		var nb = $("#bazar_form_lists .valeur_liste input[name^='label']").length + 1;	
		$("#bazar_form_lists .valeur_liste").append('<li class="liste_ligne" id="row'+nb+'">'+
				'<a href="#" title="D&eacute;placer l\'&eacute;l&eacute;ment" class="handle_liste"></a>'+
				'<input required type="text" placeholder="Id" name="id['+nb+']" class="span1" />' +
				'<input required type="text" placeholder="Texte" name="label['+nb+']" class="span3" />' +
				'<a href="#" class="BAZ_lien_supprimer suppression_label_liste"></a>'+
				'</li>');
		//$("#bazarform input.input_liste_id[name='id["+nb+"]']").focus();	
		return false;
	});
	
	// on supprime un champs pour une liste
	$('.suppression_label_liste').live('click', function() {
		var id = '#'+$(this).parent('.liste_ligne').attr('id');
		var nb = $("#bazar_form_lists .valeur_liste input[name^='label']").length;
		if (nb > 1) {
			var nom = 'a_effacer_' + $(id).find("input:hidden").attr('name');
			$(id).find("input:hidden").attr('name', nom).appendTo("#bazar_form_lists");
			$(id).remove();
			$("#bazar_form_lists .valeur_liste input[name^='label']").each(function(i) {
				$(this).attr('name', 'label['+(i+1)+']').
				parent('.liste_ligne').attr('id', 'row'+(i+1)).
				find("input:hidden").attr('name', 'ancienlabel['+(i+1)+']');
			});
		} else {
			alert('Le dernier élément ne peut être supprimé.');
		}
		return false;
	});
	
	// initialise le validateur
	$('.bouton_save_list').click(function() {
		var validateur = $("#bazar_form_lists").validator({
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
