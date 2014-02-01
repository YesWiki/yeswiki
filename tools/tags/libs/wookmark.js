$(document).ready(function() {
    // initial alignment
    $('.grid-elements').each(function() {
    	$(this).find('.gradient-grid-box').wookmark({ autoResize: true, container: $(this), offset: 10, itemWidth: 200 });
    });
});