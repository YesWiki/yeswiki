/**
 * Address search (geocoder).
 *
 * Recherche d'adresse (géocodage).
 */

/**
 * @param address The address to find.
 * @param callbackOk( longitude, latitude ) The code to call when address found.
 * @param callbackError( error_message ) The code to call when address not found or an error occured.
 */
function geocodage( address, callbackOk, callbackError )
{
  // TODO: automatically retrieving the protocol's scheme
  var http = 'http' ;

  address = address.replace(/\\("|\'|\\)/g, " ").trim();

  // requete ajax chez osm pour geolocaliser l'adresse
  $.get(http+'://nominatim.openstreetmap.org/search?q='+encodeURIComponent(address)+'&format=json')
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
