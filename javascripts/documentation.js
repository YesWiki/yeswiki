window.$docsify = {
  loadSidebar: true,
  loadNavbar: true,
  subMaxLevel: 3,
  relativePath: true,
  auto2top: true,
  alias: {
    ['/_sidebar.md']: `/docs/${locale}/_sidebar.md`, // set default _sidebar.md to locale language
    ['/_navbar.md']: `/docs/${locale}/_navbar.md`, // set default _navbar.md to locale language
  },
  search: {
    placeholder: {
      '/docs/fr/': 'Rechercher...',
      '/docs/en/': 'Search...',
      '/': 'Search...' // other pages, default to English
    },
    noData: {
      '/docs/fr/': 'Pas de résultats',
      '/docs/en': 'No results',
      '/': 'No results' // other pages, default to English
    },
    depth: 2,
    pathNamespaces: ['/docs/fr/', '/docs/en/'],
  },
  copyCode: {
    buttonText : {
      '/docs/fr/': 'Copier le code',
      '/docs/en/': 'Copy to clipboard',
      '/': 'Copy to clipboard' // other pages, default to English
    },
    errorText : 'Error',
    successText : {
      '/docs/fr/': 'Copié',
      '/docs/en/': 'Copied',
      '/': 'Copied' // other pages, default to English
    },
  },
  plugins: [
    function(hook, vm) {
      hook.afterEach(function(html) {
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
      });

      hook.ready(function() {
        // Redirect properly to translated language, otherwise we stay on "#/" hash
        // and it cause some translations issues
        if (location.hash == "#/") location.hash = `docs/${locale}/README.md`

        // Move the title inside the navbar to the top of sidebar, and set
        // correct href
        let title = document.querySelector("#back")
        if (!title) return

        title.href = baseUrl
        let sidebar = document.querySelector('.sidebar')
        sidebar.insertBefore(title, sidebar.children[0]);
      });
    }
  ],
}

function insertAfter(referenceNode, newNode) {
  referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}