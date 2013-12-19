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
        showOnLoad: 'all',
        sortOnLoad: false,
        multiFilter: false,
        filterLogic: 'or',
        resizeContainer: true,
        minHeight: 0,
        failClass: 'fail',
        perspectiveDistance: '3000',
        perspectiveOrigin: '50% 50%',
        animateGridList: false,
        onMixLoad: null,
        onMixStart: null,
        onMixEnd: function(config){

 
            // On se sert du rendu mixio pour afficher les points

            $.each(markers, function(i, marker){
                     map.removeLayer(marker);
             });

             $('#Grid .mix').map(function() {

                if ($(this).css('opacity') == '1') {
                   map.addLayer(markers[this.id.substring(6)]);
                }
            });
          
        }
    });

    
        var $filters = $('#Filters').find('li');

        var filterbydimension=Array();
        var filterString="";
        var dimensions=Array();

        $.each(groups, function(t, group){
                dimensions[group]="";
                    
        });
        //alert(dump(dimensions));

        $filters.on('click',function(){
                    var $t = $(this),
                    dimension = $t.attr('data-dimension'),
                    filter = $t.attr('data-filter');
                    if (typeof (dimensions[dimension])=="undefined") {
                      dimensions[dimension]="";
                    }
                    filterString = dimensions[dimension];
                        
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

                    dimensions[dimension] = filterString;

  
            
                    
                    filterbydimension=Array();

                    $.each(groups, function(w, group){
                        if (dimensions[group]!=='') {
                            filterbydimension[w]=dimensions[group];
                        }
                        else {
                         filterbydimension[w]="all";
                        }
                    });

  

                    $('#Grid').mixitup('filter',filterbydimension);

        });


    
});