window.$docsify = {
  themeColor: "#1a89a0",
  loadSidebar: true,
  loadNavbar: true,
  subMaxLevel: 3,
  relativePath: true,
  auto2top: true,
  alias: {
    ['.*/_sidebar.md']: `/docs/users/${locale}/_sidebar.md`, // set default _sidebar.md to locale language
    ['.*/_navbar.md']: `/docs/users/${locale}/_navbar.md`, // set default _navbar.md to locale language
  },
  search: {
    // maxAge: 0, // when developing, override cache by setting maxAge to 0. Also you need to clear your localStorage
    placeholder: {
      '/docs/users/fr/': 'Rechercher...',
      '/': 'Search...' // default to English
    },
    noData: {
      '/docs/users/fr/': 'Pas de résultats',
      '/': 'No results' // default to English
    },
    namespace: "yeswiki-doc",
    depth: 3, // which parent title to display in the search result
  },
  copyCode: {
    buttonText : {
      '/docs/users/fr/': 'Copier le code',
      '/': 'Copy to clipboard' // default to English
    },
    errorText : 'Error',
    successText : {
      '/docs/users/fr/': 'Copié',
      '/': 'Copied' // default to English
    },
  },
  plugins: [
    function(hook, vm) {
      hook.afterEach(function(html) {
        // Lazy load images and iframes
        html = html.replace(/<img src=([^\s]*)/g, '<img class="lazyload" data-src=$1')
        html = html.replace(/<iframe(.*) src=([^\s]*)/g, '<iframe$1 class="lazyload" data-src=$2')

        // Adds footer
        const url = `https://github.com/YesWiki/yeswiki/edit/doryphore-dev/${vm.route.file}`
        const footer = `
          <hr/>
          <footer>
            <a href="${url}" target="_blank">${i18n.DOC_EDIT_THIS_PAGE_ON_GITHUB}</a>
          </footer>`

        return html + footer;
      });

      hook.doneEach(function() {
        // Adds code preview when requested
        // ```yeswiki preview
        // {{button}}
        // `̀`
        const yeswikiCodes = document.querySelectorAll('pre[data-lang^="yeswiki"]')
        yeswikiCodes.forEach(preDom => {
          const data = preDom.getAttribute('data-lang')
          preDom.setAttribute('data-lang', data.split(' ')[0])
          if (data.includes('preview')) {
            const height = data.split('preview=')[1] || 200
            const codeDom = preDom.querySelector('code')
            const code = codeDom.textContent
            let url = baseUrl + 'root/render'
            url += url.includes('?') ? '&' : '?'
            url += `content=${encodeURIComponent(code)}`
            let preview = document.createElement('div');
            preview.innerHTML = `<iframe class="code-preview" src="${url}" width="100%" height="${height}" frameborder="0"></iframe>`;
            insertAfter(preDom, preview.firstChild);
          }
        })

        // search icon
        let searchIcon = document.createElement('div')
        searchIcon.innerHTML = "<i class='gg-search'></i>"
        searchIcon.classList = "search-icon"
        searchIcon.addEventListener('click', function() {
          document.querySelector('aside').classList.toggle('open')
          document.querySelector('nav > ul').classList.remove('open')
          document.querySelector('.menu-icon').classList.remove("open")
        })
        document.querySelector('nav').appendChild(searchIcon)

        // menu icon
        let menuIcon = document.createElement('div')
        menuIcon.innerHTML = "<i class='gg-menu'></i><i class='gg-close'></i>"
        menuIcon.classList = "menu-icon"
        menuIcon.addEventListener('click', function() {
          document.querySelector('nav > ul').classList.toggle('open')
          this.classList.toggle("open")
        })
        document.querySelector('nav').appendChild(menuIcon)

        // Back button url correct
        let backBtn = document.querySelector("#back")
        if (backBtn) {
          backBtn.href = baseUrl
          const backBtnClone = backBtn.cloneNode(true)
          const li = document.createElement('li')
          li.appendChild(backBtnClone)
          const nav = document.querySelector('nav > ul')
          nav.insertBefore(li, nav.children[0])
        }
      });

      hook.ready(function() {
        // Redirect properly to home page, otherwise we stay on "#/" hash
        // and it cause some translations issues
        if (location.hash == "#/") location.hash = `/docs/users/${locale}/`

        // backdrop
        let backdrop = document.createElement('div')
        backdrop.classList = "backdrop"
        backdrop.addEventListener('click', function() {
          document.querySelector('aside').classList.remove('open')
        })
        document.querySelector('main').appendChild(backdrop)
      });
    }
  ],
}

function insertAfter(referenceNode, newNode) {
  referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}