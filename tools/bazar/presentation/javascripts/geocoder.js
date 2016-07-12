/**
 *
 */

function geocodage( address, callbackOk, callbackError )
{
  var http = 'http' ;
  address = address.replace(/\\("|\'|\\)/g, " ").trim();

  // requete ajax chez osm pour geolocaliser l adresse
  $.get(http+'://nominatim.openstreetmap.org/search?q='+address+'&format=json')
  .done(function(data)
  {
    if( data.length > 0 ) {
      // Le 1er resultat trouvé
      callbackOk( data[0].lon, data[0].lat );
    } else {
      callbackError('not found');
    }
  })
  .fail(function(error)
  {
    callbackError(error);
  })
  .always(function()
  {
    //console.log( "GetLocations finished" );
  });
};
