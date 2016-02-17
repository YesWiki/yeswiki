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
 * javascript for editing forms
 *
 *
 * @package bazar
 * @author        Florian Schmitt <florian@outils-reseaux.org>
 *
 *
 **/

$(document).ready(function() {

  // import de formes à partir d'un yeswiki
  var btnimportform = $('#btn-import-forms');
  var resultforms = $('#import-forms-result');
  var resultimporttable = $('#import-forms-table');
  var resultimportform = $('#import-forms-form');
  var formtranslations = $('#form-translations').data();
  var existingforms = $('#existing-forms-table');
  btnimportform.click(function() {
    // on enleve les anciens contenus
    resultforms.html('');
    resultimportform.addClass('hide');
    resultimporttable.find('tbody').html('');

    // url saisie
    var url = $('#url-import-forms').val();

    // expression réguliere pour trouver une url valide
    var rgHttpUrl = /^(http|https):\/\/(([a-zA-Z0-9$\-_.+!*'(),;:&=]|%[0-9a-fA-F]{2})+@)?(((25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])(\.(25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])){3})|localhost|([a-zA-Z0-9\-\u00C0-\u017F]+\.)+([a-zA-Z]{2,}))(:[0-9]+)?(\/(([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*(\/([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*)*)?(\?([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?(\#([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?)?$/;

    if (rgHttpUrl.test(url)) {
      // on formate l url pour acceder au service json de yeswiki
      var taburl = url.split('wakka.php');
      url = taburl[0].replace(/\/+$/g, '') + '/wakka.php?wiki=BazaR/json&demand=forms';
      resultforms.html('<div class="alert alert-info"><span class="throbber">' + formtranslations.loading + '...</span> ' + formtranslations.recuperation + ' ' + url + '</div>');
      $.ajax({
        method: 'GET',
        url: url,
      }).done(function(data) {
        resultforms.html('');
        var count = 0;
        for (var idform in data) {
          if (data.hasOwnProperty(idform)) {
            count++;
            var trclass = '';
            var existingmessage = '';
            if (existingforms.find('td').filter(function() {
                return $(this).find('strong').text() === data[idform].bn_label_nature;
              }).length > 0) {
              trclass = ' class="error danger"';
              existingmessage = '<br><span class="text-danger">' + formtranslations.existingmessagereplace + '</span>';
            } else if (existingforms.find('td').filter(function() {
                return $(this).text() === idform;
              }).length > 0) {
              trclass = ' class="warning"';
              existingmessage = '<br><span class="text-warning">' + formtranslations.existingmessage + '</span>';
            }

            var tablerow = '<tr' + trclass + '><td><input type="checkbox" name="imported-form[' + idform + ']" value="' + JSON.stringify(data[idform]).replace(/"/g, '&quot;') + '"></td><td><strong>' + data[idform].bn_label_nature + '</strong>';
            if (data[idform].bn_description && 0 !== data[idform].bn_description.length) {
              tablerow += '<br>' + data[idform].bn_description;
            }

            tablerow += existingmessage + '</td><td>' + data[idform].bn_type_fiche + '</td><td>' + idform + '</td></tr>';
            resultimporttable.find('tbody').append(tablerow);
          }
        }

        resultimportform.removeClass('hide');
        resultforms.prepend('<div class="alert alert-success">' + formtranslations.nbformsfound + ' : ' + count + '</div>');
      }).fail(function(jqXHR, textStatus, errorThrown) {
        resultforms.html('<div class="alert alert-danger">' + formtranslations.noanswers + '.</div>');
      });
    } else {
      resultforms.html('<div class="alert alert-danger">' + formtranslations.notvalidurl + ' : ' + url + '</div>');
    }
  });
});
