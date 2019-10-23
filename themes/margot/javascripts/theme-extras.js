// replace full calendar icons
$(document).ready(function() {
  $('.fc-prev-button').html('<span class="fa fa-chevron-left"></span>').prependTo('.fc-toolbar.fc-header-toolbar')
  $('.fc-next-button').html('<span class="fa fa-chevron-right"></span>').appendTo('.fc-toolbar.fc-header-toolbar')
})

// polyfill pour variables css
var cssVarPoly = {
  init: function init() {
    // first lets see if the browser supports CSS variables
    // No version of IE supports window.CSS.supports, so if that isn't supported in the first place we know CSS variables is not supported
    // Edge supports supports, so check for actual variable support
    if (window.CSS && window.CSS.supports && window.CSS.supports('(--foo: red)')) {
      // this browser does support variables, abort
      //console.log('your browser supports CSS variables, aborting and letting the native support handle things.');
      return;
    } else {
      // edge barfs on console statements if the console is not open... lame!
      console.log('no support for you! polyfill all (some of) the things!!');
      document.querySelector('body').classList.add('cssvars-polyfilled');
    }

    cssVarPoly.ratifiedVars = {};
    cssVarPoly.varsByBlock = {};
    cssVarPoly.oldCSS = {};

    // start things off
    cssVarPoly.findCSS();
    cssVarPoly.updateCSS();
  },

  // find all the css blocks, save off the content, and look for variables
  findCSS: function findCSS() {
    var styleBlocks = document.querySelectorAll('style:not(.inserted),link[type="text/css"]');

    // we need to track the order of the style/link elements when we save off the CSS, set a counter
    var counter = 1;

    // loop through all CSS blocks looking for CSS variables being set
    [].forEach.call(styleBlocks, function (block) {
      // console.log(block.nodeName);
      var theCSS = undefined;
      if (block.nodeName === 'STYLE') {
        // console.log("style");
        theCSS = block.innerHTML;
        cssVarPoly.findSetters(theCSS, counter);
      } else if (block.nodeName === 'LINK') {
        // console.log("link");
        cssVarPoly.getLink(block.getAttribute('href'), counter, function (counter, request) {
          cssVarPoly.findSetters(request.responseText, counter);
          cssVarPoly.oldCSS[counter] = request.responseText;
          cssVarPoly.updateCSS();
        });
        theCSS = '';
      }
      // save off the CSS to parse through again later. the value may be empty for links that are waiting for their ajax return, but this will maintain the order
      cssVarPoly.oldCSS[counter] = theCSS;
      counter++;
    });
  },

  // find all the "--variable: value" matches in a provided block of CSS and add them to the master list
  findSetters: function findSetters(theCSS, counter) {
    // console.log(theCSS);
    cssVarPoly.varsByBlock[counter] = theCSS.match(/(--.+:.+;)/g) || [];
  },

  // run through all the CSS blocks to update the variables and then inject on the page
  updateCSS: function updateCSS() {
    // first lets loop through all the variables to make sure later vars trump earlier vars
    cssVarPoly.ratifySetters(cssVarPoly.varsByBlock);

    // loop through the css blocks (styles and links)
    for (var curCSSID in cssVarPoly.oldCSS) {if (window.CP.shouldStopExecution(1)){break;}
      // console.log("curCSS:",oldCSS[curCSSID]);
      var newCSS = cssVarPoly.replaceGetters(cssVarPoly.oldCSS[curCSSID], cssVarPoly.ratifiedVars);
      // put it back into the page
      // first check to see if this block exists already
      if (document.querySelector('#inserted' + curCSSID)) {
        // console.log("updating")
        document.querySelector('#inserted' + curCSSID).innerHTML = newCSS;
      } else {
        // console.log("adding");
        var style = document.createElement('style');
        style.type = 'text/css';
        style.innerHTML = newCSS;
        style.classList.add('inserted');
        style.id = 'inserted' + curCSSID;
        document.getElementsByTagName('head')[0].appendChild(style);
      }
    }
window.CP.exitedLoop(1);
;
  },

  // parse a provided block of CSS looking for a provided list of variables and replace the --var-name with the correct value
  replaceGetters: function replaceGetters(curCSS, varList) {
    // console.log(varList);
    for (var theVar in varList) {if (window.CP.shouldStopExecution(2)){break;}
      // console.log(theVar);
      // match the variable with the actual variable name
      var getterRegex = new RegExp('var\\(\\s*' + theVar + '\\s*\\)', 'g');
      // console.log(getterRegex);
      // console.log(curCSS);
      curCSS = curCSS.replace(getterRegex, varList[theVar]);

      // now check for any getters that are left that have fallbacks
      var getterRegex2 = new RegExp('var\\(\\s*.+\\s*,\\s*(.+)\\)', 'g');
      // console.log(getterRegex);
      // console.log(curCSS);
      var matches = curCSS.match(getterRegex2);
      if (matches) {
        // console.log("matches",matches);
        matches.forEach(function (match) {
          // console.log(match.match(/var\(.+,\s*(.+)\)/))
          // find the fallback within the getter
          curCSS = curCSS.replace(match, match.match(/var\(.+,\s*(.+)\)/)[1]);
        });
      }

      // curCSS = curCSS.replace(getterRegex2,varList[theVar]);
    }
window.CP.exitedLoop(2);
;
    // console.log(curCSS);
    return curCSS;
  },

  // determine the css variable name value pair and track the latest
  ratifySetters: function ratifySetters(varList) {
    // console.log("varList:",varList);
    // loop through each block in order, to maintain order specificity
    for (var curBlock in varList) {if (window.CP.shouldStopExecution(3)){break;}
      var curVars = varList[curBlock];
      // console.log("curVars:",curVars);
      // loop through each var in the block
      curVars.forEach(function (theVar) {
        // console.log(theVar);
        // split on the name value pair separator
        var matches = theVar.split(/:\s*/);
        // console.log(matches);
        // put it in an object based on the varName. Each time we do this it will override a previous use and so will always have the last set be the winner
        // 0 = the name, 1 = the value, strip off the ; if it is there
        cssVarPoly.ratifiedVars[matches[0]] = matches[1].replace(/;/, '');
      });
    }
window.CP.exitedLoop(3);
;
    // console.log(ratifiedVars);
  },

  // get the CSS file (same domain for now)
  getLink: function getLink(url, counter, success) {
    var request = new XMLHttpRequest();
    request.open('GET', url, true);
    request.overrideMimeType('text/css;');
    request.onload = function () {
      if (request.status >= 200 && request.status < 400) {
        // Success!
        // console.log(request.responseText);
        if (typeof success === 'function') {
          success(counter, request);
        }
      } else {
        // We reached our target server, but it returned an error
        console.warn('an error was returned from:', url);
      }
    };

    request.onerror = function () {
      // There was a connection error of some sort
      console.warn('we could not get anything from:', url);
    };

    request.send();
  }
};
cssVarPoly.init();




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
