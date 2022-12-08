$(document).ready(function () {
  if (typeof autocompleteFieldnames == 'object' && 'postalCode' in autocompleteFieldnames && 'town' in autocompleteFieldnames ){
    $(`input[name="${autocompleteFieldnames.postalCode}"],input[name="${autocompleteFieldnames.town}"]`).attr("autocomplete", "off");
    var $inputcp = $(`input[name="${autocompleteFieldnames.postalCode}"]`);
    $inputcp.typeahead({
      items: 'all',
      source: function(input, callback) {
        var result = [];
        if (input.length === 5) {
          $.get("https://geo.api.gouv.fr/communes?codePostal="+input).done(function( data ) {
            if (data.length > 0) {
              $.each(data, function (index, value) {
                result[index] = {id: value.codesPostaux[0], name: value.codesPostaux[0]+" "+value.nom, ville: value.nom}
              });
            } else {
              result[0] = {id: input, name: _t('BAZ_POSTAL_CODE_NOT_FOUND',{input:input})};
            }
            callback(result);
          });
        } else {
          result[0] = {id: input, name: _t('BAZ_POSTAL_CODE_HINT')};
          callback(result);
        }
      },
      autoSelect: false,
      afterSelect: function(item) {
        $inputcp.val(item.id);
        $inputville.val(item.ville);
        $(".btn-geolocate-address").click();
      }
    });
    var $inputville = $(`input[name="${autocompleteFieldnames.town}"]`);
    $inputville.typeahead({
      items: 12,
      minLength: 3,
      source: function(input, callback) {
        var result = [];
        if (input.length >= 3) {
          $.get("https://geo.api.gouv.fr/communes?nom="+input).done(function( data ) {
            if (data.length > 0) {
              $.each(data, function (index, value) {
                result[index] = {id: value.codesPostaux[0], name: value.nom+" "+value.codesPostaux[0], ville: value.nom}
              });
            } else {
              result[0] = {id: input, name: _t('BAZ_TOWN_NOT_FOUND',{input:input})};
            }
            callback(result);
          });
        } else {
          result[0] = {id: input, name: _t('BAZ_TOWN_HINT')};
          callback(result);
        }
      },
      autoSelect: false,
      afterSelect: function(item) {
        $inputcp.val(item.id);
        $inputville.val(item.ville);
        $(".btn-geolocate-address").click();
      }
    });      
  }
});