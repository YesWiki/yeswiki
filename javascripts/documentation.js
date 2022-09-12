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

        hook.mounted(function() {
          // Redirect properly to translated language, otherwise we stay on "#/" hash
          // and it cause some translations issues
          if (location.hash == "#/") location.hash = `docs/${locale}/README.md`

          // Move the title inside the navbar to the top of sidebar, and set
          // correct href
          setTimeout(() => {
            let title = document.querySelector("#back")
            if (!title) return

            title.href = baseUrl
            let sidebar = document.querySelector('.sidebar')
            sidebar.insertBefore(title, sidebar.children[0]);
          }, 100)
        });
      }
    ]
}