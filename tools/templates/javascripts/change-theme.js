(function($) {
    $("#changetheme").on("change", function(){

    if ($(this).attr("id") === "changetheme") {

        // On change le theme dynamiquement
        var val = $(this).val();
        // pour vider la liste
        var squelette = $("#changesquelette")[0];
        squelette.options.length=0
        for (var i=0; i<themeSquelettes[val].length; i++){
            const newVal = themeSquelettes[val][i] + '.tpl.html'
            o = new Option(newVal,newVal);
            squelette.options[squelette.options.length] = o;
        }
        var style = $("#changestyle")[0];
        style.options.length=0
        for (var i=0; i<themeStyles[val].length; i++){
            const newVal = themeStyles[val][i] + '.css'
            o = new Option(newVal,newVal);
            style.options[style.options.length]=o;
        }
    }

});
})(jQuery);