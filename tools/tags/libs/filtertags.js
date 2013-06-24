$(document).ready(function() {

    // options for wookmark
    var wookmarkoptions = {
          autoResize: true, // This will auto-update the layout when the browser window is resized.
          container: $('.filter-results'), // Optional, used for some extra CSS styling
          offset: 15, // Optional, the distance between grid items
          itemWidth: 270 // Optional, the width of a grid item
    };

    // initial alignment
    $('.filtered-element').wookmark(wookmarkoptions);
    
    // options for mixitup
    var filterresults = $('.filter-results');
    var mixitupoptions = {
      onMixEnd: refresh,
      filterSelector: '.filter-original', 
      filterLogic: 'and',
      targetSelector: '.filtered-element',
      effects:  ['fade','scale'],
      transitionSpeed: 300
    };
    filterresults.mixitup(mixitupoptions);

    // count the number of resulting filtered elements and move them in optimal shape
    var results;
    function refresh() {
      results = filterresults.find('.filtered-element').filter(function() {
        return $(this).css('opacity') == '1';
      });
      results.wookmark(wookmarkoptions);
      $('.nbfilteredelements').text(results.length);    
    };

    // open filtered elements in new windows
    $('.filtered-element[data-wikipage]').on('click', function(e) { 
        e.stopPropagation();
        window.open($(this).data('wikipage'));
        return false;
    } );

    $('.filter').on('click', function() {
      var $this = $(this);
      $this.toggleClass('active');
      if ($this.parents('.filter-group').data('type') === 'radio') {$this.siblings('.filter').removeClass('active');}
      // for the radio type filter buttons, just one active in one row
      //var $radio = $('.filter-group').filter('div[data-type=radio]').find('.filter').removeClass('active');
      //if (!active) {
      //  $this.addClass('active');
      //}
      var selectedfilters = $('.controls .active');
      filterString='';
      $.each( selectedfilters, function() {        
        filterString = filterString+' '+$(this).data('filter');
      });

      if (filterString === '') {filterString = 'all';}
      console.log(filterString);
      filterresults.mixitup('filter',filterString);
      return false;
    });

  });