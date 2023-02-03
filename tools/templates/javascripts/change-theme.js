(function($) {
    $("#changetheme").on("change", function(){

    if ($(this).attr("id") === "changetheme") {

        // On change le theme dynamiquement
        var val = $(this).val();
        // pour vider la liste
        var squelette = $("#changesquelette")[0];
        squelette.options.length=0
        for (var i=0; i<tab1[val].length; i++){
            o = new Option(tab1[val][i],tab1[val][i]);
            squelette.options[squelette.options.length] = o;
        }
        var style = $("#changestyle")[0];
        style.options.length=0
        for (var i=0; i<tab2[val].length; i++){
            o = new Option(tab2[val][i],tab2[val][i]);
            style.options[style.options.length]=o;
        }
    }

});
})(jQuery);