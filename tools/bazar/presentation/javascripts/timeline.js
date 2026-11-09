$(() => {
  jQuery.easing.jswing = jQuery.easing.swing
  jQuery.extend(jQuery.easing, {
    def: 'easeOutQuad',
    swing(e, f, a, h, g) {
      return jQuery.easing[jQuery.easing.def](e, f, a, h, g)
    },
    easeOutQuad(e, f, a, h, g) {
      const t = f / g
      return -h * t * (t - 2) + a
    },
    easeInOutExpo(e, f, a, h, g) {
      if (f === 0) {
        return a
      }
      if (f === g) {
        return a + h
      }
      const t = f / (g / 2)
      if (t < 1) {
        return (h / 2) * 2 ** (10 * (t - 1)) + a
      }
      return (h / 2) * (-(2 ** (-10 * (t - 1))) + 2) + a
    },
  })

  const $sidescroll = (function () {
    // the row elements
    const $rows = $('#ss-container > div.ss-row')
    // we will cache the inviewport rows and the outside viewport rows
    let $rowsViewport
    let $rowsOutViewport
    // navigation menu links
    const $links = $('#ss-links > a')
    // the window element
    const $win = $(window)
    // we will store the window sizes here
    const winSize = {}
    // used in the scroll setTimeout function
    let anim = false
    // page scroll speed
    const scollPageSpeed = 1000
    // page scroll easing
    const scollPageEasing = 'easeInOutExpo'
    // perspective?
    const hasPerspective = true

    const perspective = hasPerspective && Modernizr.testAllProps('perspective')
    // initialize function
    const init = function () {
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
          '-webkit-perspective-origin': '50% 0%',
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
    var defineViewport = function () {
      $.extend($.expr[':'], {
        inviewport(el) {
          if ($(el).offset().top < winSize.height) {
            return true
          }
          return false
        },
      })
    }
    // checks which rows are initially visible
    var setViewportRows = function () {
      $rowsViewport = $rows.filter(':inviewport')
      $rowsOutViewport = $rows.not($rowsViewport)
    }
    // get window sizes
    var getWinSize = function () {
      winSize.width = $win.width()
      winSize.height = $win.height()
    }
    // initialize some events
    var initEvents = function () {
      // navigation menu links.
      // scroll to the respective section.
      $links.on('click.Scrolling', function (_event) {
        // scroll to the element that has id = menu's href
        $('html, body')
          .stop()
          .animate(
            { scrollTop: $($(this).attr('href')).offset().top },
            scollPageSpeed,
            scollPageEasing,
          )

        return false
      })

      $(window).on({
        // on window resize we need to redefine which rows are initially visible (this ones we will not animate).
        'resize.Scrolling': function (_event) {
          // get the window sizes again
          getWinSize()
          // redefine which rows are initially visible (:inviewport)
          setViewportRows()
          // remove pointers for every row
          $rows.find('a.ss-circle').removeClass('ss-circle-deco')
          // show inviewport rows and respective pointers
          $rowsViewport.each(function () {
            $(this)
              .find('div.ss-left')
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
        'scroll.Scrolling': function (_event) {
          // set a timeout to avoid that the
          // placeRows function gets called on every scroll trigger
          if (anim) return false
          anim = true
          setTimeout(() => {
            placeRows()
            anim = false
          }, 10)
        },
      })
    }
    // sets the position of the rows (left and right row elements).
    // Both of these elements will start with -50% for the left/right (not visible)
    // and this value should be 0% (final position) when the element is on the
    // center of the window.
    var placeRows = function () {
      // how much we scrolled so far
      const winscroll = $win.scrollTop()
      // the y value for the center of the screen
      const winCenter = winSize.height / 2 + winscroll

      // for every row that is not inviewport
      $rowsOutViewport.each(function (_i) {
        const $row = $(this)
        // the left side element
        const $rowL = $row.find('div.ss-left')
        // the right side element
        const $rowR = $row.find('div.ss-right')
        // top value
        const rowT = $row.offset().top

        // hide the row if it is under the viewport
        if (rowT > winSize.height + winscroll) {
          if (perspective) {
            $rowL.css({
              '-moz-transform':
                'translate3d(-75%, 0, 0) rotateY(-90deg) translate3d(-75%, 0, 0)',
              '-webkit-transform':
                'translate3d(-75%, 0, 0) rotateY(-90deg) translate3d(-75%, 0, 0)',
              opacity: 0,
            })
            $rowR.css({
              '-moz-transform':
                'translate3d(75%, 0, 0) rotateY(90deg) translate3d(75%, 0, 0)',
              '-webkit-transform':
                'translate3d(75%, 0, 0) rotateY(90deg) translate3d(75%, 0, 0)',
              opacity: 0,
            })
          } else {
            $rowL.css({ left: '-50%' })
            $rowR.css({ right: '-50%' })
          }
        }
        // if not, the row should become visible (0% of left/right) as it gets closer to the center of the screen.
        else {
          // row's height
          const rowH = $row.height()
          // the value on each scrolling step will be proporcional to the distance from the center of the screen to its height
          const factor =
            (rowT + rowH / 2 - winCenter) / (winSize.height / 2 + rowH / 2)
          // value for the left / right of each side of the row.
          // 0% is the limit
          const val = Math.max(factor * 50, 0)

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
            const t = Math.max(factor * 75, 0)
            const r = Math.max(factor * 90, 0)
            const o = Math.min(Math.abs(factor - 1), 1)

            $rowL.css({
              '-moz-transform': `translate3d(-${t}%, 0, 0) rotateY(-${r}deg) translate3d(-${t}%, 0, 0)`,
              '-webkit-transform': `translate3d(-${t}%, 0, 0) rotateY(-${r}deg) translate3d(-${t}%, 0, 0)`,
              opacity: o,
            })
            $rowR.css({
              '-moz-transform': `translate3d(${t}%, 0, 0) rotateY(${r}deg) translate3d(${t}%, 0, 0)`,
              '-webkit-transform': `translate3d(${t}%, 0, 0) rotateY(${r}deg) translate3d(${t}%, 0, 0)`,
              opacity: o,
            })
          } else {
            $rowL.css({ left: `${-val}%` })
            $rowR.css({ right: `${-val}%` })
          }
        }
      })
    }

    return { init }
  })()

  $sidescroll.init()
})
