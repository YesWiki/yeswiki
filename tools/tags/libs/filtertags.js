$(document).ready(function() {
// This filter is later used as the selector for which grid items to show.
      var filter = '';
      var handler;
      var nbfilteredelements;
      var noresults = $('.tags-no-results-message');
      
      // Prepare layout options.
      var options = {
        autoResize: true, // This will auto-update the layout when the browser window is resized.
        container: $(".filter-results"), // Optional, used for some extra CSS styling
        offset: 15 // Optional, the distance between grid items
        //,itemWidth: 210 // Optional, the width of a grid item
      };
      
      // This function filters the grid when a change is made.
      var refresh = function() {
        // Clear our previous handler.
        if(handler) {
          handler.wookmarkClear();
          handler = null;
        }
        
        // This hides all grid items ("inactive" is a CSS class that sets opacity to 0).
        $('.filtered-element').addClass('inactive').removeClass('active');
        
        // Create a new layout selector with our filter.
        handler = $(filter);
        if (handler.length === 0) { noresults.show();}
        else { noresults.hide();}
        $('.nbfilteredelements').text(handler.length);
        
        // This shows the items we want visible.
        handler.removeClass("inactive").addClass("active");
        
        // This updates the layout.
        handler.wookmark(options);
      }
      
      /**
      * This function checks all filter options to see which ones are active.
      * If they have changed, it also calls a refresh (see above).
      */
      var updateFilters = function() {
        var oldFilter = filter;
        filter = '';
        var filters = ['.filtered-element'];
        
        // Collect filter list.
        var lis = $('.filter-buttons .btn-filter-tag');
        var i=0, length=lis.length, li;
        for(; i<length; i++) {
          li = $(lis[i]);
          if(li.hasClass('active')) {
            filters.push('.'+li.attr('data-filter'));
          }
        }
        
        // If no filters active, set default to show all.
        if(filters.length == 0) {
          filters.push('.filtered-element');
        }
        
        // Finalize our filter selector for jQuery.
        filter = filters.join('');

        // If the filter has changed, update the layout.
        if(oldFilter != filter) {
          refresh();
        }
      };
      
      /**
      * When a filter is clicked, toggle it's active state and refresh.
      */
      var onClickFilter = function(event) {
        var item = $(event.currentTarget);
        if (item.parents('.btn-group').data('type')=='buttons-radio') {
        	item.siblings().removeClass('active');
        }
        item.toggleClass('active');
        updateFilters();
      }
      
      // Capture filter click events.
      $('.filter-buttons .btn-filter-tag').on('click', onClickFilter);
      
      // Do initial update (shows all items).
      updateFilters();

      // open filtered elements
      $('.filtered-element[data-wikipage]').on('click', function(e) { 
        window.open($(this).data('wikipage'));
      } );
});