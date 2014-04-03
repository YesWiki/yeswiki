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
	// on rend les listes deplacables
	$(".list-sortables").sortable({
		handle : '.handle-listitems',
		update : function () {
			$("#bazar_form_lists .list-sortables input[name^='label']").each(function(i) {
				$(this).attr('name', 'label['+(i+1)+']').
				prev().attr('name', 'id['+(i+1)+']').
				parent('.liste_ligne').attr('id', 'row'+(i+1)).
				find("input:hidden").attr('name', 'ancienlabel['+(i+1)+']');
			});
		}
	});

	// pour la gestion des listes, on peut rajouter dynamiquement des champs
	$('.ajout_label_liste').on('click', function() {	
		var nb = $("#bazar_form_lists .list-sortables input[name^='label']").length + 1;	
		$("#bazar_form_lists .list-sortables").append('<li id="row'+nb+'" class="liste_ligne input-prepend input-append">'+
			'<a title="D&eacute;placer l\'&eacute;l&eacute;ment" class="handle-listitems add-on"><i class="icon-move"></i></a>'+
			'<input required type="text" placeholder="Cl&eacute;" name="id['+nb+']" class="input-mini" />' +
			'<input required type="text" placeholder="Texte" name="label['+nb+']" />' +
			'<a class="add-on suppression_label_liste"><i class="icon-trash"></i></a>'+
			'</li>');
		$("#bazar_form_lists input[name='id["+nb+"]']").focus();	
		return false;
	});
	
	// on supprime un champs pour une liste
	$('#bazar_form_lists ul.list-sortables').on('click', '.suppression_label_liste', function() {
		var id = '#'+$(this).parent('.liste_ligne').attr('id');
		var nb = $("#bazar_form_lists .list-sortables input[name^='label']").length;
		if (nb > 1) {
			if (confirm('Confirmez-vous la suppression de cette valeur dans la liste ?')) {
				var nom = 'a_effacer_' + $(id).find("input:hidden").attr('name');
				$(id).find("input:hidden").attr('name', nom).appendTo("#bazar_form_lists");
				$(id).remove();
				$("#bazar_form_lists .list-sortables input[name^='label']").each(function(i) {
					$(this).attr('name', 'label['+(i+1)+']').
					prev().attr('name', 'id['+(i+1)+']').
					parent('.liste_ligne').attr('id', 'row'+(i+1)).
					find("input:hidden").attr('name', 'ancienlabel['+(i+1)+']');
				});
			}
		} else {
			alert('Le dernier élément ne peut être supprimé.');
		}
		return false;
	});
	
	// initialise le validateur
	$('.btn-save-list').click(function() {

	});
});
