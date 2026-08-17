;(() => {
  const docRoot = () =>
    document.querySelector('.yw-dashboard__canvas main') ?? document

  if (!document.querySelector('nav.app-nav')) {
    document.body.prepend(document.createElement('nav'))
  }

  const AVAILABLE_LOCALES = ['fr']
  const FALLBACK_LOCALE = 'fr'
  // into a local -- writing back to a page-injected global is not ours to do.
  const docLocale = AVAILABLE_LOCALES.includes(locale)
    ? locale
    : FALLBACK_LOCALE

  window.$docsify = {
    el: '#yw-doc',
    loadSidebar: true,
    loadNavbar: true,
    subMaxLevel: 3,
    relativePath: true,
    auto2top: true,
    alias: {
      '.*/_sidebar.md': `/docs/${docLocale}/_sidebar.md`,
      '.*/_navbar.md': `/docs/${docLocale}/_navbar.md`,
    },
    search: {
      placeholder: docLocale === 'fr' ? 'Rechercher...' : 'Search...',
      noData: docLocale === 'fr' ? 'Pas de résultats' : 'No results',
      namespace: 'yeswiki-doc',
      depth: 3,
      paths: [
        `/docs/${docLocale}/README`,
        `/docs/${docLocale}/webmaster`,
        `/docs/${docLocale}/prise-en-main`,
        `/docs/${docLocale}/bazar`,
        `/docs/${docLocale}/admin`,
        `/docs/${docLocale}/communaute`,
        `/docs/${docLocale}/documentation`,
        `/docs/${docLocale}/dev`,
        `/docs/${docLocale}/activitypub`,
        `/docs/${docLocale}/asso-finances`,
        `/docs/${docLocale}/semantic`,
        `/docs/${docLocale}/usage-avance`,
        ...extensions.map((ext) => `/${ext.docPath}`),
      ],
    },
    copyCode: {
      buttonText: {
        '/docs/fr/': 'Copier le code',
        '/': 'Copy to clipboard',
      },
      errorText: 'Error',
      successText: {
        '/docs/fr/': 'Copié',
        '/': 'Copied',
      },
    },
    plugins: [
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
          const dd = false
          const TARGET_QUERY = 'id'
          const SCROLL_DELAY = 750
          const EXCLUDED_PARENT_CLASS = 'cover'
          const { location } = window

          dd && console.log('custom scroll plugin called!')

          const currentUrlWithoutHash = new URL(
            location.origin + location.pathname + location.search,
          )

          const urlQueryParam = currentUrlWithoutHash.searchParams
          const isUrlHasIdQuery = urlQueryParam.has(TARGET_QUERY)

          const hashParams = new URLSearchParams(location.hash.split('?')[1])
          const isHashHasIdQuery = hashParams.has(TARGET_QUERY)

          if (isUrlHasIdQuery || isHashHasIdQuery) {
            dd && console.log('url or hash has id, will scroll to element')

            const urlId = isUrlHasIdQuery
              ? urlQueryParam.get(TARGET_QUERY)
              : hashParams.get(TARGET_QUERY)

            setTimeout(() => {
              dd && console.log('will scroll now!')
              try {
                const targetElement = document.getElementById(urlId)
                dd && console.log('Target element ID:', urlId)
                if (targetElement) {
                  const excludedParent = targetElement.closest(
                    `.${EXCLUDED_PARENT_CLASS}`,
                  )

                  if (!excludedParent) {
                    requestAnimationFrame(() => {
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
          let rendered = html
            .replace(/<img src=([^\s]*)/g, '<img class="lazyload" data-src=$1')
            .replace(
              /<iframe(.*) src=([^\s]*)/g,
              '<iframe$1 class="lazyload" data-src=$2',
            )

          if (vm.route.file.match(/^docs\/.*$/)) {
            const url = `https://github.com/YesWiki/yeswiki/edit/doryphore-dev/${vm.route.file}`
            const footer = `
              <hr/>
              <footer>
                <a href="${url}" target="_blank">${i18n.DOC_EDIT_THIS_PAGE_ON_GITHUB}</a>
              </footer>`
            rendered += footer
          }

          return rendered
        })

        hook.doneEach(() => {
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

          initCustomNavMenu()
        })

        hook.ready(() => {
          if (location.hash === '#/')
            location.hash = `/docs/${docLocale}/README`

          const backdrop = document.createElement('div')
          backdrop.classList = 'backdrop'
          backdrop.addEventListener('click', () => {
            docRoot().querySelector('aside').classList.remove('open')
          })
          docRoot().appendChild(backdrop)

          const backToTop = document.createElement('button')
          backToTop.classList = 'back-to-top'
          backToTop.setAttribute(
            'aria-label',
            docLocale === 'fr' ? 'Retour en haut' : 'Back to top',
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

  if (!window.ywDocHashListener) {
    window.ywDocHashListener = true
    window.addEventListener('hashchange', () => {
      setTimeout(() => {
        initCustomNavMenu()
      }, 0)
    })
  }

  function initCustomNavMenu() {
    if (!docRoot().querySelector('nav')) return
    if (docRoot().querySelector('nav .search-icon')) return

    const searchIcon = document.createElement('div')
    searchIcon.innerHTML = "<i class='gg-search'></i>"
    searchIcon.classList = 'search-icon'
    searchIcon.addEventListener('click', () => {
      docRoot().querySelector('aside').classList.toggle('open')
      docRoot().querySelector('nav > ul').classList.remove('open')
      docRoot().querySelector('.menu-icon').classList.remove('open')
    })
    docRoot().querySelector('nav').appendChild(searchIcon)

    const menuIcon = document.createElement('div')
    menuIcon.innerHTML = "<i class='gg-menu'></i><i class='gg-close'></i>"
    menuIcon.classList = 'menu-icon'
    menuIcon.addEventListener('click', function () {
      docRoot().querySelector('nav > ul').classList.toggle('open')
      this.classList.toggle('open')
    })
    docRoot().querySelector('nav').appendChild(menuIcon)

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
