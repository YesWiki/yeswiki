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

  var asyncCalls = new Array();

  // async call to find the full address
  asyncCalls.push(
    $.get(http+'://nominatim.openstreetmap.org/search?q='+encodeURIComponent(address)+'&format=json')
  );

  if( found = address.match( /^\d+(.*)/i ) )
  {
    var address2 = found[1] ;
    // async call to find without address street number
    asyncCalls.push(
      $.get(http+'://nominatim.openstreetmap.org/search?q='+encodeURIComponent(address2)+'&format=json')
    );
  }

  $.when.apply( $, asyncCalls )
  .then( function( c1, c2 )
  {
    // All async calls done.
    if( typeof(c2) == 'string' && c1.length > 0 )
    {
      // case: only one query
      console.log("c1 found");
      callbackOk( c1[0].lon, c1[0].lat );
    }
    else if( typeof(c2) != 'string' && c1[0].length > 0 )
    {
      console.log("c1 found");
      callbackOk( c1[0][0].lon, c1[0][0].lat );
    }
    else if( typeof(c2) != 'string' && c2[0].length > 0 )
    {
      console.log("c2 found");
      callbackOk( c2[0][0].lon, c2[0][0].lat );
    }
    else
    {
      callbackError('not found');
    }

  });

};
