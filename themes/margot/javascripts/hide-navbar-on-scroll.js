// Hide Header on on scroll down
var didScroll;
var lastScrollTop = 0;
var scrolldelta = 5;
var ywnavbar = $('#yw-topnav');
var navbarHeight = ywnavbar.outerHeight();
var minOffsetForHiding = 300;

$(window).scroll(function(event) {
  didScroll = true;
});

setInterval(function() {
  if (didScroll) {
    hasScrolled();
    didScroll = false;
  }
}, 250);

function hasScrolled() {
  var st = $(this).scrollTop();
  // Make sure they scroll more than delta
  if (Math.abs(lastScrollTop - st) <= scrolldelta)
    return;

  // If they scrolled down and are past the navbar, add class .nav-up.
  // This is necessary so you never see what is "behind" the navbar.
  if (st > lastScrollTop && st > navbarHeight && ywnavbar.hasClass('affix') && st > minOffsetForHiding) {
    // Scroll Down
    ywnavbar.addClass('nav-up');
  } else {
    // Scroll Up
    if (st + $(window).height() < $(document).height()) {
      ywnavbar.removeClass('nav-up');
    }
  }

  lastScrollTop = st;
}
