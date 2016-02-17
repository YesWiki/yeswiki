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

$(document).ready(function() {
  // on rend les listes deplacables
  var sortables = $('.list-sortables');
  if (sortables.length > 0) {
    sortables.sortable({
      handle: '.handle-listitems',
      update: function() {
        $("#bazar_form_lists .list-sortables input[name^='label']").each(function(i) {
          $(this).attr('name', 'label[' + (i + 1) + ']').
          prev().attr('name', 'id[' + (i + 1) + ']').
          parent('.liste_ligne').attr('id', 'row' + (i + 1)).
          find('input:hidden').attr('name', 'ancienlabel[' + (i + 1) + ']');
        });
      },
    });
  }

  var newitem = $('#empty-new-item').html();

  // pour la gestion des listes, on peut rajouter dynamiquement des champs
  $('.ajout_label_liste').on('click', function() {
    var nb = $("#bazar_form_lists .list-sortables input[name^='label']").length + 1;
    var nextnewitem = newitem.replace(/@nb@/gi, nb);
    $('#bazar_form_lists .list-sortables').append(nextnewitem);
    $("#bazar_form_lists input[name='id[" + nb + "]']").focus();
    return false;
  });

  // on supprime un champs pour une liste
  $('#bazar_form_lists ul.list-sortables').on('click', '.suppression_label_liste', function() {
    var id = '#' + $(this).parent('.liste_ligne').attr('id');
    var nb = $("#bazar_form_lists .list-sortables input[name^='label']").length;
    if (nb > 1) {
      if (confirm('Confirmez-vous la suppression de cette valeur dans la liste ?')) {
        var nom = 'a_effacer_' + $(id).find('input:hidden').attr('name');
        $(id).find('input:hidden').attr('name', nom).appendTo('#bazar_form_lists');
        $(id).remove();
        $("#bazar_form_lists .list-sortables input[name^='label']").each(function(i) {
          $(this).attr('name', 'label[' + (i + 1) + ']').
          prev().attr('name', 'id[' + (i + 1) + ']').
          parent('.liste_ligne').attr('id', 'row' + (i + 1)).
          find('input:hidden').attr('name', 'ancienlabel[' + (i + 1) + ']');
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

  // import de listes à partir d'un yeswiki
  var btnimportlist = $('#btn-import-lists');
  var resultimportlist = $('#import-lists-result');
  var resultimporttable = $('#import-lists-table');
  var resultimportform = $('#import-lists-form');
  var listtranslations = $('#list-translations').data();
  var existinglists = $('#existing-lists-table');
  btnimportlist.click(function() {
    // on enleve les anciens contenus
    resultimportlist.html('');
    resultimportform.addClass('hide');
    resultimporttable.find('tbody').html('');

    // url saisie
    var url = $('#url-import-lists').val();

    // expression réguliere pour trouver une url valide
    var rgHttpUrl = /^(http|https):\/\/(([a-zA-Z0-9$\-_.+!*'(),;:&=]|%[0-9a-fA-F]{2})+@)?(((25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])(\.(25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])){3})|localhost|([a-zA-Z0-9\-\u00C0-\u017F]+\.)+([a-zA-Z]{2,}))(:[0-9]+)?(\/(([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*(\/([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*)*)?(\?([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?(\#([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?)?$/;

    if (rgHttpUrl.test(url)) {
      // on formate l url pour acceder au service json de yeswiki
      var taburl = url.split('wakka.php');
      url = taburl[0].replace(/\/+$/g, '') + '/wakka.php?wiki=BazaR/json&demand=lists';
      resultimportlist.html('<div class="alert alert-info"><span class="throbber">' + listtranslations.loading + '...</span> ' + listtranslations.recuperation + ' ' + url + '</div>');
      $.ajax({
        method: 'GET',
        url: url,
      }).done(function(data) {
        resultimportlist.html('');
        var count = 0;
        for (var idlist in data) {
          if (data.hasOwnProperty(idlist)) {
            count++;
            var select = '<option>' + listtranslations.choose + '</option>';
            for (var key in data[idlist].label) {
              if (data[idlist].label.hasOwnProperty(key)) {
                select += '<option>' + data[idlist].label[key] + '</option>';
              }
            }

            var trclass = '';
            var existingmessage = '';
            if (existinglists.find('td').filter(function() {
                return $(this).text() === idlist;
              }).length > 0) {
              trclass = ' class="error danger"';
              existingmessage = '<br><span class="text-danger">' + listtranslations.existingmessage + '</span>';
            }

            resultimporttable.find('tbody').append('<tr' + trclass + '><td><input type="checkbox" name="imported-list[' + idlist + ']" value="' + JSON.stringify(data[idlist]).replace(/"/g, '&quot;') + '"></td><td>' + idlist + existingmessage + '</td><td>' + data[idlist]['titre_liste'] + '</td><td><select class="form-control">' + select + '</select></td></tr>');
          }
        }

        resultimportform.removeClass('hide');
        resultimportlist.prepend('<div class="alert alert-success">' + listtranslations.nblistsfound + ' : ' + count + '</div>');
      }).fail(function(jqXHR, textStatus, errorThrown) {
        resultimportlist.html('<div class="alert alert-danger">' + listtranslations.noanswers + '.</div>');
      });
    } else {
      resultimportlist.html('<div class="alert alert-danger">' + listtranslations.notvalidurl + ' : ' + url + '</div>');
    }
  });
});
