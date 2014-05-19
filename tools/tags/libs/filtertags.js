$(document).ready(function() {
  var filterelements = Array();
  var filterresults = Array();
  //var mixitupoptions = Array();
  var wookmarkoptions = Array();
  var results;
  $('.filter-container').each(function(index, value) {
    var $this = $(this);
    filterelements[index] = $this.find('.filtered-element');
    filterelements[index].css('width', $this.data('element-width'));
    filterresults[index] = $this.find('.filtered-results');
    /*mixitupoptions[index] = {
      onMixEnd: function(index) {
        results = filterelements[index].filter(function() {
          return $(this).css('opacity') == '1';
        });
        results.wookmark(wookmarkoptions[index]);
        $('.nbfilteredelements').text(results.length);
      },
      filterSelector: '.filter-original', 
      targetSelector: filterelements[index],
      filterLogic: 'and',
      effects:  ['fade','scale'],
      transitionSpeed: 300
    };
    filterresults[index].mixitup(mixitupoptions[index]);
*/
    wookmarkoptions[index] = {
      autoResize: true, // This will auto-update the layout when the browser window is resized.
      container: filterresults[index], // Optional, used for some extra CSS styling
      offset: $this.data('element-width'), // Optional, the distance between grid items
      itemWidth: $this.data('element-offset'), // Optional, the width of a grid item
      fillEmptySpace: true
    };
    filterresults[index].imagesLoaded(function() {
      filterelements[index].wookmark(wookmarkoptions[index]);
    });

    $this.prev('.controls').find('.filter').on('click', function() {
      var $filter = $(this);
      $filter.toggleClass('active');
      if ($filter.parents('.filter-group').data('type') === 'radio') {$filter.siblings('.filter').removeClass('active');}
      // for the radio type filter buttons, just one active in one row
      //var $radio = $('.filter-group').filter('div[data-type=radio]').find('.filter').removeClass('active');
      //if (!active) {
      //  $this.addClass('active');
      //}
      var filtercontrols = $filter.parents('.controls');
      var selectedfilters = filtercontrols.find('.active');
      filterString='';
      var activeFilters = [];
      $.each( selectedfilters, function() {        
        /*filterString = filterString+' '+$(this).data('filter');*/
        activeFilters.push($(this).data('filter'));
      });
console.log(filterelements[index].wookmarkInstance.filter(activeFilters));
      /*if (filterString === '') {filterString = 'all';}
      
      filtercontrols.next('.filter-container').find('.filter-results').mixitup('filter', filterString);*/

      filterelements[index].wookmarkInstance.filter(activeFilters);
      return false;
    });

  });

});