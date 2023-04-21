$(document).ready(function () {
  if (typeof autocompleteFieldnames == 'object' && 'postalCode' in autocompleteFieldnames && 'town' in autocompleteFieldnames ){
    $(`input[name="${autocompleteFieldnames.postalCode}"],input[name="${autocompleteFieldnames.town}"]`).attr("autocomplete", "off");
    var $inputcp = $(`input[name="${autocompleteFieldnames.postalCode}"]`);
    $inputcp.typeahead({
      items: 'all',
      source: function(input, callback) {
        if (input.length === 5) {
          geolocationHelper.getGelocationDataFromPostalCode('France',input)
          .then((data)=>{
              var result = [];
              data.forEach((geoloc)=>{
                result.push({
                  id: geoloc.postalCode,
                  name: `${geoloc.postalCode} ${geoloc.town}`,
                  ville: geoloc.town
                })
              })
              callback(result)
            })
            .catch(()=>{
              callback([{id: input, name: _t('BAZ_POSTAL_CODE_HINT')}])
            })
        } else {
          callback([{id: input, name: _t('BAZ_POSTAL_CODE_HINT')}])
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
        if (input.length >= 3) {
          geolocationHelper.getGelocationDataFromTown('France',input)
          .then((data)=>{
              var result = [];
              if (data.length > 0) {
                data.forEach((geoloc)=>{
                  result.push({
                    id: geoloc.postalCode,
                    name: `${geoloc.postalCode} ${geoloc.town}`,
                    ville: geoloc.town
                  })
                })
                callback(result)
              } else {
                callback([{id: input, name: _t('BAZ_TOWN_NOT_FOUND',{input:input})}])
              }
            })
            .catch(()=>{
              callback([{id: input, name: _t('BAZ_TOWN_HINT')}])
            })
        } else {
          callback([{id: input, name: _t('BAZ_TOWN_HINT')}])
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