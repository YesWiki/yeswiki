$(() => {
  jQuery.easing.jswing = jQuery.easing.swing; jQuery.extend(jQuery.easing, { def: 'easeOutQuad', swing(e, f, a, h, g) { return jQuery.easing[jQuery.easing.def](e, f, a, h, g) }, easeInQuad(e, f, a, h, g) { return h * (f /= g) * f + a }, easeOutQuad(e, f, a, h, g) { return -h * (f /= g) * (f - 2) + a }, easeInOutQuad(e, f, a, h, g) { if ((f /= g / 2) < 1) { return h / 2 * f * f + a } return -h / 2 * ((--f) * (f - 2) - 1) + a }, easeInCubic(e, f, a, h, g) { return h * (f /= g) * f * f + a }, easeOutCubic(e, f, a, h, g) { return h * ((f = f / g - 1) * f * f + 1) + a }, easeInOutCubic(e, f, a, h, g) { if ((f /= g / 2) < 1) { return h / 2 * f * f * f + a } return h / 2 * ((f -= 2) * f * f + 2) + a }, easeInQuart(e, f, a, h, g) { return h * (f /= g) * f * f * f + a }, easeOutQuart(e, f, a, h, g) { return -h * ((f = f / g - 1) * f * f * f - 1) + a }, easeInOutQuart(e, f, a, h, g) { if ((f /= g / 2) < 1) { return h / 2 * f * f * f * f + a } return -h / 2 * ((f -= 2) * f * f * f - 2) + a }, easeInQuint(e, f, a, h, g) { return h * (f /= g) * f * f * f * f + a }, easeOutQuint(e, f, a, h, g) { return h * ((f = f / g - 1) * f * f * f * f + 1) + a }, easeInOutQuint(e, f, a, h, g) { if ((f /= g / 2) < 1) { return h / 2 * f * f * f * f * f + a } return h / 2 * ((f -= 2) * f * f * f * f + 2) + a }, easeInSine(e, f, a, h, g) { return -h * Math.cos(f / g * (Math.PI / 2)) + h + a }, easeOutSine(e, f, a, h, g) { return h * Math.sin(f / g * (Math.PI / 2)) + a }, easeInOutSine(e, f, a, h, g) { return -h / 2 * (Math.cos(Math.PI * f / g) - 1) + a }, easeInExpo(e, f, a, h, g) { return (f == 0) ? a : h * 2 ** (10 * (f / g - 1)) + a }, easeOutExpo(e, f, a, h, g) { return (f == g) ? a + h : h * (-(2 ** (-10 * f / g)) + 1) + a }, easeInOutExpo(e, f, a, h, g) { if (f == 0) { return a } if (f == g) { return a + h } if ((f /= g / 2) < 1) { return h / 2 * 2 ** (10 * (f - 1)) + a } return h / 2 * (-(2 ** (-10 * --f)) + 2) + a }, easeInCirc(e, f, a, h, g) { return -h * (Math.sqrt(1 - (f /= g) * f) - 1) + a }, easeOutCirc(e, f, a, h, g) { return h * Math.sqrt(1 - (f = f / g - 1) * f) + a }, easeInOutCirc(e, f, a, h, g) { if ((f /= g / 2) < 1) { return -h / 2 * (Math.sqrt(1 - f * f) - 1) + a } return h / 2 * (Math.sqrt(1 - (f -= 2) * f) + 1) + a }, easeInElastic(f, h, e, l, k) { var i = 1.70158; let j = 0; let g = l; if (h == 0) { return e } if ((h /= k) == 1) { return e + l } if (!j) { j = k * 0.3 } if (g < Math.abs(l)) { g = l; var i = j / 4 } else { var i = j / (2 * Math.PI) * Math.asin(l / g) } return -(g * 2 ** (10 * (h -= 1)) * Math.sin((h * k - i) * (2 * Math.PI) / j)) + e }, easeOutElastic(f, h, e, l, k) { var i = 1.70158; let j = 0; let g = l; if (h == 0) { return e } if ((h /= k) == 1) { return e + l } if (!j) { j = k * 0.3 } if (g < Math.abs(l)) { g = l; var i = j / 4 } else { var i = j / (2 * Math.PI) * Math.asin(l / g) } return g * 2 ** (-10 * h) * Math.sin((h * k - i) * (2 * Math.PI) / j) + l + e }, easeInOutElastic(f, h, e, l, k) { var i = 1.70158; let j = 0; let g = l; if (h == 0) { return e } if ((h /= k / 2) == 2) { return e + l } if (!j) { j = k * (0.3 * 1.5) } if (g < Math.abs(l)) { g = l; var i = j / 4 } else { var i = j / (2 * Math.PI) * Math.asin(l / g) } if (h < 1) { return -0.5 * (g * 2 ** (10 * (h -= 1)) * Math.sin((h * k - i) * (2 * Math.PI) / j)) + e } return g * 2 ** (-10 * (h -= 1)) * Math.sin((h * k - i) * (2 * Math.PI) / j) * 0.5 + l + e }, easeInBack(e, f, a, i, h, g) { if (g == undefined) { g = 1.70158 } return i * (f /= h) * f * ((g + 1) * f - g) + a }, easeOutBack(e, f, a, i, h, g) { if (g == undefined) { g = 1.70158 } return i * ((f = f / h - 1) * f * ((g + 1) * f + g) + 1) + a }, easeInOutBack(e, f, a, i, h, g) { if (g == undefined) { g = 1.70158 } if ((f /= h / 2) < 1) { return i / 2 * (f * f * (((g *= (1.525)) + 1) * f - g)) + a } return i / 2 * ((f -= 2) * f * (((g *= (1.525)) + 1) * f + g) + 2) + a }, easeInBounce(e, f, a, h, g) { return h - jQuery.easing.easeOutBounce(e, g - f, 0, h, g) + a }, easeOutBounce(e, f, a, h, g) { if ((f /= g) < (1 / 2.75)) { return h * (7.5625 * f * f) + a } if (f < (2 / 2.75)) { return h * (7.5625 * (f -= (1.5 / 2.75)) * f + 0.75) + a } if (f < (2.5 / 2.75)) { return h * (7.5625 * (f -= (2.25 / 2.75)) * f + 0.9375) + a } return h * (7.5625 * (f -= (2.625 / 2.75)) * f + 0.984375) + a }, easeInOutBounce(e, f, a, h, g) { if (f < g / 2) { return jQuery.easing.easeInBounce(e, f * 2, 0, h, g) * 0.5 + a } return jQuery.easing.easeOutBounce(e, f * 2 - g, 0, h, g) * 0.5 + h * 0.5 + a } })

  const $sidescroll	= (function() {
    // the row elements
    const $rows			= $('#ss-container > div.ss-row')
    // we will cache the inviewport rows and the outside viewport rows
    let $rowsViewport; let $rowsOutViewport
    // navigation menu links
    const $links			= $('#ss-links > a')
    // the window element
    const $win			= $(window)
    // we will store the window sizes here
    const winSize			= {}
    // used in the scroll setTimeout function
    let anim			= false
    // page scroll speed
    const scollPageSpeed	= 1000
    // page scroll easing
    const scollPageEasing = 'easeInOutExpo'
    // perspective?
    const hasPerspective	= true

    const perspective		= hasPerspective && Modernizr.testAllProps('perspective')
    // initialize function
    const init			= function() {
      // get window sizes
      getWinSize()
      // initialize events
      initEvents()
      // define the inviewport selector
      defineViewport()
      // gets the elements that match the previous selector
      setViewportRows()
      // if perspective add css
      if (perspective) {
        $rows.css({
          '-moz-perspective': 600,
          '-moz-perspective-origin': '50% 0%',
          '-webkit-perspective': 600,
          '-webkit-perspective-origin': '50% 0%'
        })
      }
      // show the pointers for the inviewport rows
      $rowsViewport.find('a.ss-circle').addClass('ss-circle-deco')
      // set positions for each row
      placeRows()
    }
    // defines a selector that gathers the row elems that are initially visible.
    // the element is visible if its top is less than the window's height.
    // these elements will not be affected when scrolling the page.
    var defineViewport	= function() {
      $.extend($.expr[':'], {

        inviewport(el) {
          if ($(el).offset().top < winSize.height) {
            return true
          }
          return false
        }

      })
    }
    // checks which rows are initially visible
    var setViewportRows	= function() {
      $rowsViewport 		= $rows.filter(':inviewport')
      $rowsOutViewport	= $rows.not($rowsViewport)
    }
    // get window sizes
    var getWinSize		= function() {
      winSize.width	= $win.width()
      winSize.height	= $win.height()
    }
    // initialize some events
    var initEvents		= function() {
      // navigation menu links.
      // scroll to the respective section.
      $links.on('click.Scrolling', function(event) {
        // scroll to the element that has id = menu's href
        $('html, body').stop().animate({ scrollTop: $($(this).attr('href')).offset().top }, scollPageSpeed, scollPageEasing)

        return false
      })

      $(window).on({
        // on window resize we need to redefine which rows are initially visible (this ones we will not animate).
        'resize.Scrolling': function(event) {
          // get the window sizes again
          getWinSize()
          // redefine which rows are initially visible (:inviewport)
          setViewportRows()
          // remove pointers for every row
          $rows.find('a.ss-circle').removeClass('ss-circle-deco')
          // show inviewport rows and respective pointers
          $rowsViewport.each(function() {
            $(this).find('div.ss-left')
								   .css({ left: '0%' })
								   .end()
								   .find('div.ss-right')
								   .css({ right: '0%' })
								   .end()
								   .find('a.ss-circle')
								   .addClass('ss-circle-deco')
          })
        },
        // when scrolling the page change the position of each row
        'scroll.Scrolling': function(event) {
          // set a timeout to avoid that the
          // placeRows function gets called on every scroll trigger
          if (anim) return false
          anim = true
          setTimeout(() => {
            placeRows()
            anim = false
          }, 10)
        }
      })
    }
    // sets the position of the rows (left and right row elements).
    // Both of these elements will start with -50% for the left/right (not visible)
    // and this value should be 0% (final position) when the element is on the
    // center of the window.
    var placeRows		= function() {
      // how much we scrolled so far
      const winscroll	= $win.scrollTop()
      // the y value for the center of the screen
      const winCenter	= winSize.height / 2 + winscroll

      // for every row that is not inviewport
      $rowsOutViewport.each(function(i) {
        const $row	= $(this)
        // the left side element
        const $rowL	= $row.find('div.ss-left')
        // the right side element
        const $rowR	= $row.find('div.ss-right')
        // top value
        const rowT	= $row.offset().top

        // hide the row if it is under the viewport
        if (rowT > winSize.height + winscroll) {
          if (perspective) {
            $rowL.css({
              '-moz-transform': 'translate3d(-75%, 0, 0) rotateY(-90deg) translate3d(-75%, 0, 0)',
              '-webkit-transform': 'translate3d(-75%, 0, 0) rotateY(-90deg) translate3d(-75%, 0, 0)',
              opacity: 0
            })
            $rowR.css({
              '-moz-transform': 'translate3d(75%, 0, 0) rotateY(90deg) translate3d(75%, 0, 0)',
              '-webkit-transform': 'translate3d(75%, 0, 0) rotateY(90deg) translate3d(75%, 0, 0)',
              opacity: 0
            })
          } else {
            $rowL.css({ left: '-50%' })
            $rowR.css({ right: '-50%' })
          }
        }
        // if not, the row should become visible (0% of left/right) as it gets closer to the center of the screen.
        else {
          // row's height
          const rowH	= $row.height()
          // the value on each scrolling step will be proporcional to the distance from the center of the screen to its height
          const factor 	= (((rowT + rowH / 2) - winCenter) / (winSize.height / 2 + rowH / 2))
          // value for the left / right of each side of the row.
          // 0% is the limit
          const val		= Math.max(factor * 50, 0)

          if (val <= 0) {
            // when 0% is reached show the pointer for that row
            if (!$row.data('pointer')) {
              $row.data('pointer', true)
              $row.find('.ss-circle').addClass('ss-circle-deco')
            }
          } else {
            // the pointer should not be shown
            if ($row.data('pointer')) {
              $row.data('pointer', false)
              $row.find('.ss-circle').removeClass('ss-circle-deco')
            }
          }

          // set calculated values
          if (perspective) {
            const	t		= Math.max(factor * 75, 0)
            const r		= Math.max(factor * 90, 0)
            const o		= Math.min(Math.abs(factor - 1), 1)

            $rowL.css({
              '-moz-transform': `translate3d(-${t}%, 0, 0) rotateY(-${r}deg) translate3d(-${t}%, 0, 0)`,
              '-webkit-transform': `translate3d(-${t}%, 0, 0) rotateY(-${r}deg) translate3d(-${t}%, 0, 0)`,
              opacity: o
            })
            $rowR.css({
              '-moz-transform': `translate3d(${t}%, 0, 0) rotateY(${r}deg) translate3d(${t}%, 0, 0)`,
              '-webkit-transform': `translate3d(${t}%, 0, 0) rotateY(${r}deg) translate3d(${t}%, 0, 0)`,
              opacity: o
            })
          } else {
            $rowL.css({ left: `${-val}%` })
            $rowR.css({ right: `${-val}%` })
          }
        }
      })
    }

    return { init }
  }())

  $sidescroll.init()
})
