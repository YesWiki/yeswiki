// Fonction debogage a supprimer
function dump(arr,level) {
    var dumped_text = "";
    if(!level) level = 0;
    
    //The padding given at the beginning of the line.
    var level_padding = "";
    for(var j=0;j<level+1;j++) level_padding += "    ";
    
    if(typeof(arr) == 'object') { //Array/Hashes/Objects 
        for(var item in arr) {
            var value = arr[item];
            
            if(typeof(value) == 'object') { //If it is an array,
                dumped_text += level_padding + "'" + item + "' ...\n";
                dumped_text += dump(value,level+1);
            } else {
                dumped_text += level_padding + "'" + item + "' => \"" + value + "\"\n";
            }
        }
    } else { //Stings/Chars/Numbers etc.
        dumped_text = "===>"+arr+"<===("+typeof(arr)+")";
    }
    return dumped_text;
}

// Comportement :
// Par defaut, pas d'affichage des points.
// Affichage uniquement si point selectionné
// Pas de gestion du filtre "all" (le code est present au cas ou ...)


$(function(){
        $('#Grid').mixitup({
        targetSelector: '.mix',
        filterSelector: '.filter',
        sortSelector: '.sort',
        buttonEvent: 'click',
        effects: ['fade','scale'],
        listEffects: null,
        easing: 'smooth',
        layoutMode: 'grid',
        targetDisplayGrid: 'inline-block',
        targetDisplayList: 'block',
        listClass: 'list',
        transitionSpeed: 600,
        showOnLoad: 'none',
        sortOnLoad: false,
        multiFilter: true,
        filterLogic: 'or',
        resizeContainer: true,
        minHeight: 0,
        failClass: 'fail',
        perspectiveDistance: '3000',
        perspectiveOrigin: '50% 50%',
        animateGridList: false,
        onMixLoad: function(config){
            $.each(markers, function(i, marker){
                     map.removeLayer(marker);
             });

/* Specifique SMT */
        //Extend the Default marker class
        var GereIcon = L.Icon.Default.extend({
        options: {
                iconUrl: "tools/bazar/presentation/images/marker_bleu.png",
                iconSize:[12,20],
                shadowSize:   [22,20],
                iconAnchor:   [6, 20],
                shadowAnchor: [6, 20]
            }
         });


        var  gereIcon = new GereIcon();


            var geres=Array();
            $.each(places, function(u, place){ // Genere par bazarcato
                 if(place.idtypeannonce=='5') {
                    marker=markers[u];
                    marker.setIcon(gereIcon);
                    marker.unbindPopup();
                    marker.bindPopup(new L.Popup({maxWidth:"1000"}).setContent(place.descriptionlongue));
                    map.addLayer(marker);
                    geres[u]=marker;
                 }
            });
/* Fin Specifique SMT */
        },
        onMixStart: null,
        onMixEnd: function(config){
            // On se sert du rendu mixio pour afficher les points sur la carte
             $('#Grid .mix').map(function() {

                if ($(this).css('opacity')=='1') {
                   if (places[this.id.substring(6)].idtypeannonce=='5') {
                        marker=geres[this.id.substring(6)];
                       // map.removeLayer(marker);
                   }
                   map.addLayer(markers[this.id.substring(6)]);
                }
            });

         
            $.each(places, function(u, place){ // Genere par bazarcato
                 if(place.idtypeannonce=='5') {
                    map.addLayer(markers[u]);
                 }
            });
        }
    });


    // Gestion des filtres

        var $filters = $('#Filters').find('li');

        var filterbydimension=Array();
        var filterString="";


        var dimensions=Array();

        $.each(groups, function(t, group){ // Genere par bazarcato
                dimensions[group]="";
                    
        });
        


        $filters.on('click',function(){

                    // Nettoyage markers
                    $.each(markers, function(i, marker){
                     map.removeLayer(marker);
                    });

                    var $t = $(this),
                    dimension = $t.attr('data-dimension'),
                    filter = $t.attr('data-filter');
                    if (typeof (dimensions[dimension])=="undefined") {
                      dimensions[dimension]="";
                    }
                    filterString = dimensions[dimension];

                    if(filter == 'all'){
                        // If "all"
                        if(!$t.hasClass('active')){
                            // if unchecked, check "all" and uncheck all other active filters
                            $t.addClass('active').siblings().removeClass('active');
                            // Replace entire string with "all"
                            filterString = 'all';
                        } else {
                            // Uncheck
                            $t.removeClass('active');
                            // Emtpy string
                            filterString = '';
                        }
                    }
                    else {
                        // Else, uncheck "all"
                        $t.siblings('[data-filter="all"]').removeClass('active');
                        // Remove "all" from string
                        filterString = filterString.replace(/\ball\b/, "");
                        
                        if(!$t.hasClass('active')){
                            // Check checkbox
                            $t.addClass('active');
                            // Append filter to string
                            filterString = filterString === '' ? filter : filterString+' '+filter;
                        } else {
                            // Uncheck
                            $t.removeClass('active');
                            var re = new RegExp('(\\s|^)'+filter);
                            filterString = filterString.replace(re,'');
                        }
                    }

                    dimensions[dimension] = filterString;

               //alert(dump(dimensions));

            
                    
                    filterbydimension=Array();

                    $.each(groups, function(w, group){
                        if (dimensions[group]!=='') {
                            filterbydimension[w]=dimensions[group];
                        }
                        else {
                            filterbydimension[w]='';
                        }

                    });




          //  alert(dump(filterbydimension));

                    $('#Grid').mixitup('filter',filterbydimension);

        });


    
});