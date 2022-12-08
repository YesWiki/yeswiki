(function($) {
// Creating the sweetPages jQuery plugin:
  $.fn.sweetPages = function(opts) {
    // If no options were passed, create an empty opts object
    if (!opts) opts = {}

    const resultsPerPage = opts.perPage || 3

    // The plugin works best for unordered lists, althugh ols would do just as well:
    const ul = this
    const li = ul.find('> li')

    li.each(function() {
      // Calculating the height of each li element, and storing it with the data method:
      const el = $(this)
      el.data('height', el.outerHeight(true))
    })

    // Calculating the total number of pages:
    const pagesNumber = Math.ceil(li.length / resultsPerPage)

    // If the pages are less than two, do nothing:
    if (pagesNumber < 2) return this

    // Creating the controls div:
    const swControls = $('<div class="swControls">')

    for (let i = 0; i < pagesNumber; i++) {
      // Slice a portion of the lis, and wrap it in a swPage div:
      li.slice(i * resultsPerPage, (i + 1) * resultsPerPage).wrapAll('<div class="swPage" />')

      // Adding a link to the swControls div:
      swControls.append(`<a href="" class="swShowPage">${i + 1}</a>`)
    }

    ul.append(swControls)

    let maxHeight = 0
    let totalWidth = 0

    const swPage = ul.find('.swPage')
    swPage.each(function() {
      // Looping through all the newly created pages:

      const elem = $(this)

      let tmpHeight = 0
      elem.find('li').each(function() { tmpHeight += $(this).data('height') })

      if (tmpHeight > maxHeight) { maxHeight = tmpHeight }

      totalWidth += elem.outerWidth()

      elem.css('float', 'left').width(ul.width())
    })

    swPage.wrapAll('<div class="swSlider" />')

    // Setting the height of the ul to the height of the tallest page:
    ul.height(maxHeight)

    const swSlider = ul.find('.swSlider')
    swSlider.append('<div class="clear" />').width(totalWidth)

    const hyperLinks = ul.find('a.swShowPage')

    hyperLinks.click(function(e) {
      // If one of the control links is clicked, slide the swSlider div
      // (which contains all the pages) and mark it as active:

      $(this).addClass('active').siblings().removeClass('active')

      swSlider.stop().animate({ 'margin-left': -(parseInt($(this).text()) - 1) * ul.width() }, 'slow')
      e.preventDefault()
    })

    // Mark the first link as active the first time this code runs:
    hyperLinks.eq(0).addClass('active')

    // Center the control div:
    /* swControls.css({
    'left':'50%',
    'margin-left':-swControls.width()/2
  }); */

    return this
  }
}(jQuery))

$(document).ready(() => {
  // Calling the jQuery plugin and splitting the
  // .liste_rss_paginee UL into pages of 2 LIs each:
  $('.liste_rss_paginee').each(function() {
    const classes = $(this).attr('class')
    const exp = /[0-9]/g
    const nb = classes.match(exp)
    $(this).sweetPages({ perPage: nb })
    const controls = $(this).find('.swControls').detach()
    controls.appendTo($(this).parent('.boite_syndication'))
  })
})
