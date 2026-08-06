/**
 * Everything docsify renders, and nothing else.
 *
 * This script was written when /doc was a whole document of its own, so it reached for
 * `nav`, `aside` and `main` bare. On a wiki page those match the wiki's own chrome first:
 * the search and menu icons ended up appended to the site navbar, and "back to my wiki"
 * was inserted into the dashboard rail. Every lookup below goes through here instead.
 *
 * Wrapped in a function because /doc is reached by boosted navigation: leaving for /api and
 * coming back re-runs this file, and a top-level `const` re-declared in the same global
 * scope is a SyntaxError that kills the whole script before it runs. What it kills is the
 * empty <nav> below -- so docsify then found the wiki's OWN navbar with `find('nav')`,
 * emptied it into `_navbar.md`, and the site navigation disappeared until a full reload.
 */
;(() => {
  const docRoot = () =>
    document.querySelector('.yw-dashboard__canvas main') ?? document

  // Docsify builds its navbar with `find('nav') || create('nav')` -- the FIRST <nav> in the
  // document, which on a wiki page is the site's own navigation bar. It would add `app-nav`
  // to it, empty it into the documentation's `_navbar.md`, and move it. Handing it an empty
  // one, ahead of everything else in the body, is what keeps the wiki's navbar the wiki's.
  if (!document.querySelector('nav.app-nav')) {
    document.body.prepend(document.createElement('nav'))
  }

  const AVAILABLE_LOCALES = ['fr'] // replace by ['fr', 'en'] when english documentation is available
  // replace by ['fr', 'en', 'es'] when english and spannish documentations are available
  const FALLBACK_LOCALE = 'fr' // do not change it
  if (!AVAILABLE_LOCALES.includes(locale)) locale = FALLBACK_LOCALE

  window.$docsify = {
    // mounted into the dashboard canvas, not over the whole document: /doc is a wiki page
    // now, with the wiki's navbar above it and the dashboard rail beside it
    el: '#yw-doc',
    loadSidebar: true,
    loadNavbar: true,
    subMaxLevel: 3,
    relativePath: true,
    auto2top: true,
    alias: {
      '.*/_sidebar.md': `/docs/${locale}/_sidebar.md`, // set default _sidebar.md to locale language
      '.*/_navbar.md': `/docs/${locale}/_navbar.md`, // set default _navbar.md to locale language
    },
    search: {
      // maxAge: 0, // when developing, override cache by setting maxAge to 0. Also you need to clear your localStorage
      placeholder: locale === 'fr' ? 'Rechercher...' : 'Search...',
      noData: locale === 'fr' ? 'Pas de résultats' : 'No results',
      namespace: 'yeswiki-doc',
      depth: 3, // which parent title to display in the search result
      paths: [
        `/docs/${locale}/README`,
        `/docs/${locale}/webmaster`,
        `/docs/${locale}/prise-en-main`,
        `/docs/${locale}/bazar`,
        `/docs/${locale}/admin`,
        `/docs/${locale}/communaute`,
        `/docs/${locale}/documentation`,
        `/docs/${locale}/dev`,
        `/docs/${locale}/activitypub`,
        `/docs/${locale}/asso-finances`,
        `/docs/${locale}/semantic`,
        `/docs/${locale}/usage-avance`,
        ...extensions.map((ext) => `/${ext.docPath}`),
      ],
    },
    copyCode: {
      buttonText: {
        '/docs/fr/': 'Copier le code',
        '/': 'Copy to clipboard', // default to English
      },
      errorText: 'Error',
      successText: {
        '/docs/fr/': 'Copié',
        '/': 'Copied', // default to English
      },
    },
    plugins: [
      // Docsify appends its navbar to <body>, not into the element it mounts on -- so on a
      // wiki page it lands above the wiki's own navbar, outside the container, unstyled.
      // Moved into the doc's <main> once it exists, where doc.css can reach it.
      function (hook) {
        const relocateNavbar = () => {
          const nav = document.querySelector('body > .app-nav')
          const main = document.querySelector('.yw-dashboard__canvas main')
          if (nav && main) main.insertBefore(nav, main.firstChild)
        }
        hook.ready(relocateNavbar)
        hook.doneEach(relocateNavbar)
      },
      function (hook, vm) {
        // In docsify v5, the active sidebar link detection changed from prefix matching to
        // exact URL matching. The dummy [Doc](/) link no longer matches any page except the
        // root, so the heading-based sub-sidebar is never generated.
        // Fix: capture it in afterEach (before resetToc() is called) and inject in doneEach.
        let pendingSubSidebar = null
        hook.afterEach((html) => {
          if (vm.config.subMaxLevel > 0) {
            const subHtml = vm.compiler.subSidebar(vm.config.subMaxLevel) || ''
            pendingSubSidebar = subHtml.includes('<li>') ? subHtml : null
          }
          return html
        })

        hook.doneEach(() => {
          if (pendingSubSidebar) {
            const firstLi = docRoot().querySelector('.sidebar-nav > ul > li')
            if (firstLi && !firstLi.querySelector('.app-sub-sidebar')) {
              firstLi.innerHTML += pendingSubSidebar
            }
            pendingSubSidebar = null
          }
        })

        hook.ready(() => {
          // Original code by @rizdaprasetya and further improved with the assistance of ChatGPT, an AI language model by OpenAI
          // true = show debug log
          const dd = false
          const TARGET_QUERY = 'id'
          const SCROLL_DELAY = 750 // in milliseconds
          const EXCLUDED_PARENT_CLASS = 'cover' // Class of parent to exclude
          const { location } = window

          dd && console.log('custom scroll plugin called!')

          // Build the current URL without the hash
          const currentUrlWithoutHash = new URL(
            location.origin + location.pathname + location.search,
          )

          // Get the search parameters from the URL (before the hash)
          const urlQueryParam = currentUrlWithoutHash.searchParams
          const isUrlHasIdQuery = urlQueryParam.has(TARGET_QUERY)

          // Handle the case where the 'id' is in the fragment (hash) part
          // the query params after `#`
          const hashParams = new URLSearchParams(location.hash.split('?')[1])
          const isHashHasIdQuery = hashParams.has(TARGET_QUERY)

          // Check if the URL contains the 'id' query parameter in either search or hash
          if (isUrlHasIdQuery || isHashHasIdQuery) {
            dd && console.log('url or hash has id, will scroll to element')

            // Get the 'id' from either search or hash
            const urlId = isUrlHasIdQuery
              ? urlQueryParam.get(TARGET_QUERY)
              : hashParams.get(TARGET_QUERY)

            // Delay the scrolling to ensure everything is loaded
            setTimeout(() => {
              dd && console.log('will scroll now!')
              try {
                // getElementById references the element directly
                const targetElement = document.getElementById(urlId)
                dd && console.log('Target element ID:', urlId)
                if (targetElement) {
                  // Check if the target element is within an excluded parent
                  const excludedParent = targetElement.closest(
                    `.${EXCLUDED_PARENT_CLASS}`,
                  )

                  if (!excludedParent) {
                    // Use requestAnimationFrame for immediate scroll to the element
                    requestAnimationFrame(() => {
                      // Scroll smoothly to the element's top position
                      window.scrollTo({
                        top:
                          (targetElement.offsetTop +
                            targetElement.offsetParent?.offsetTop || 0) - 160,
                        behavior: 'smooth',
                      })
                    })
                  } else {
                    dd &&
                      console.log(
                        'Target is within an excluded parent:',
                        excludedParent,
                      )
                  }
                } else {
                  dd && console.log('Element not found')
                }
              } catch (e) {
                dd && console.log('custom scroll failed', e)
              }
            }, SCROLL_DELAY)
          }
        })

        hook.afterEach((html) => {
          // Lazy load images and iframes
          html = html.replace(
            /<img src=([^\s]*)/g,
            '<img class="lazyload" data-src=$1',
          )
          html = html.replace(
            /<iframe(.*) src=([^\s]*)/g,
            '<iframe$1 class="lazyload" data-src=$2',
          )

          // Adds footer
          if (vm.route.file.match(/^docs\/.*$/)) {
            const url = `https://github.com/YesWiki/yeswiki/edit/doryphore-dev/${vm.route.file}`
            const footer = `
              <hr/>
              <footer>
                <a href="${url}" target="_blank">${i18n.DOC_EDIT_THIS_PAGE_ON_GITHUB}</a>
              </footer>`
            html += footer
          }

          return html
        })

        hook.doneEach(() => {
          // Adds code preview when requested
          // ```yeswiki preview
          // {{button}}
          // `̀`
          const yeswikiCodes = document.querySelectorAll(
            'pre[data-lang^="yeswiki"]',
          )
          yeswikiCodes.forEach((preDom) => {
            const data = preDom.getAttribute('data-lang')
            preDom.setAttribute('data-lang', data.split(' ')[0])
            if (data.includes('preview')) {
              const height = data.split('preview=')[1] || 200
              const codeDom = preDom.querySelector('code')
              const code = codeDom.textContent
              let url = `${baseUrl}wiki/render`
              url += url.includes('?') ? '&' : '?'
              url += `content=${encodeURIComponent(code)}`
              const preview = document.createElement('div')
              const iframe = document.createElement('iframe')
              iframe.className = 'code-preview'
              iframe.src = url
              iframe.width = '100%'
              iframe.height = String(parseInt(height, 10) || 200)
              iframe.setAttribute('frameborder', '0')
              preview.appendChild(iframe)
              insertAfter(preDom, preview.firstChild)
            }
          })

          // We need to do it each time cause the `nav` element is dynamically replaced by
          // the content of `_navbar.md` each time we navigate to a new page
          initCustomNavMenu()
        })

        hook.ready(() => {
          // Redirect properly to home page, otherwise we stay on "#/" hash
          // and it cause some translations issues
          if (location.hash == '#/') location.hash = `/docs/${locale}/README`

          // adds backdrop for mobile search menu
          const backdrop = document.createElement('div')
          backdrop.classList = 'backdrop'
          backdrop.addEventListener('click', () => {
            docRoot().querySelector('aside').classList.remove('open')
          })
          docRoot().appendChild(backdrop)

          // Back to top button
          const backToTop = document.createElement('button')
          backToTop.classList = 'back-to-top'
          backToTop.setAttribute(
            'aria-label',
            locale === 'fr' ? 'Retour en haut' : 'Back to top',
          )
          backToTop.innerHTML = '<i class="gg-arrow-up"></i>'
          backToTop.addEventListener('click', () =>
            window.scrollTo({ top: 0, behavior: 'smooth' }),
          )
          docRoot().appendChild(backToTop)
          window.addEventListener('scroll', () => {
            backToTop.classList.toggle('visible', window.scrollY > 300)
          })
        })
      },
    ],
  }

  function insertAfter(referenceNode, newNode) {
    referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling)
  }

  // Handle browser back navigation, not handled by hook.doneEach. Registered once for the
  // page's whole life, not once per run of this file -- a boosted visit back to /doc runs
  // it again, and a second listener would only re-do work the first already did.
  if (!window.ywDocHashListener) {
    window.ywDocHashListener = true
    window.addEventListener('hashchange', () => {
      setTimeout(() => {
        initCustomNavMenu()
      }, 0)
    })
  }

  // Improve navbar menu by adding mobile icons and auto fixing the "back to my wiki" url
  function initCustomNavMenu() {
    // the navbar exists only once docsify has rendered it into the doc root
    if (!docRoot().querySelector('nav')) return
    // Do not run this method twice
    if (docRoot().querySelector('nav .search-icon')) return

    // search icon
    const searchIcon = document.createElement('div')
    searchIcon.innerHTML = "<i class='gg-search'></i>"
    searchIcon.classList = 'search-icon'
    searchIcon.addEventListener('click', () => {
      docRoot().querySelector('aside').classList.toggle('open')
      docRoot().querySelector('nav > ul').classList.remove('open')
      docRoot().querySelector('.menu-icon').classList.remove('open')
    })
    docRoot().querySelector('nav').appendChild(searchIcon)

    // menu icon
    const menuIcon = document.createElement('div')
    menuIcon.innerHTML = "<i class='gg-menu'></i><i class='gg-close'></i>"
    menuIcon.classList = 'menu-icon'
    menuIcon.addEventListener('click', function () {
      docRoot().querySelector('nav > ul').classList.toggle('open')
      this.classList.toggle('open')
    })
    docRoot().querySelector('nav').appendChild(menuIcon)

    // The "back to my wiki" link `_navbar.md` carries is not cloned into the menu any more:
    // the documentation renders inside the wiki now, under the wiki's own navigation bar and
    // beside the dashboard rail, so a link back to it is a link to the page you are on.

    // extensions menu
    const element = document.getElementById('extensions-links')
    if (element) {
      if (extensions.length > 0) {
        element.removeAttribute('href')
        const list = document.createElement('ul')
        let html = ''
        extensions.forEach((ext) => {
          html += `<li><a href="#/${ext.docPath}">${ext.name}</a></li>`
        })
        list.innerHTML = html
        element.parentNode.appendChild(list)
      } else {
        element.parentNode.parentNode.removeChild(element.parentNode)
      }
    }
  }
})()
