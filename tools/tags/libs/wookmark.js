$(document).ready(function() {
    // initial alignment
    $('.filter-results').each(function() {
    	$(this).find('.filtered-element').wookmark({ autoResize: true, container: $(this), offset: 15, itemWidth: 270 });
    });

    // open filtered elements in new windows
    $('.filtered-element[data-wikipage]').each(function() {
    	$(this).on('click', function(e) { 
	    	e.stopPropagation();
	        window.open($(this).data('wikipage'));
	        return false;
	    });
    });
});