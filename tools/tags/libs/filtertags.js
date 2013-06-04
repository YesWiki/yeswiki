$(document).ready(function() {

    // options for wookmark
    var wookmarkoptions = {
          autoResize: true, // This will auto-update the layout when the browser window is resized.
          container: $('.filter-results'), // Optional, used for some extra CSS styling
          offset: 15, // Optional, the distance between grid items
          itemWidth: 260 // Optional, the width of a grid item
    };

    // options for mixitup
    var filterresults = $('.filter-results');
    var mixitupoptions = {
      /*onMixStart: activebuttons,*/
      onMixEnd: refresh,
      multiFilter: true,
      /*filterLogic: 'and',*/
      targetSelector: '.filtered-element'
    };
    filterresults.mixitup(mixitupoptions);

    // count the number of resulting filtered elements and move them in optimal shape
    var results;
    function refresh() {
      results = filterresults.find('.filtered-element').filter(function() {
        return $(this).css('opacity') == '1';
      });
console.log('nb results : '+results.length);
      //results.wookmark(wookmarkoptions);
      $('.nbfilteredelements').text(results.length);    
    };

    // initial refresh
    //refresh();
    
    // open filtered elements in new windows
    $('.filtered-element[data-wikipage]').on('click', function(e) { 
        window.open($(this).data('wikipage'));
    } );

    $('.filter').on('click', function() {
      var $this = $(this);
      if ($this.parents('.filter-group').data('type') === 'radio') {$this.siblings('.filter').removeClass('active');}
      // for the radio type filter buttons, just one active in one row
      //var $radio = $('.filter-group').filter('div[data-type=radio]').find('.filter').removeClass('active');
      //if (!active) {
      //  $this.addClass('active');
      //}
      var selectedfilters = $('.controls .active');
      filterString=new Array();var i=0;
      $.each( selectedfilters, function() {        
        //filterString = filterString+' '+$(this).data('filter');
        filterString[i]=$(this).data('filter');
        i++;
      });
      console.log(filterString);

      //filterString = results.join(' ');
      //filter.find('.filtered-element').show();
      filterresults.mixitup('filter',filterString);
      //console.log(filter.mixitup('filter',[filterString]));
      return false;
    });

  });