window.$docsify = {
    homepage: `docs/${locale}/README.md`,
    loadSidebar: true,
    loadNavbar: true,
    subMaxLevel: 3,
    relativePath: false,
    auto2top: true,
    fallbackLanguages: ['fr'],
    nameLink: {
      '/en/': '#/en/',
      '/es/': '#/es/',
      '/cat/': '#/cat/',
      '/fr': '#/fr',
      '/': '#/'
    },
    // repo: 'https://github.com/YesWiki/yeswiki/',
    // copyCode: { // not used because extension copy code not installed
    //   buttonText : 'Copier',
    //   errorText  : 'Erreur',
    //   successText: 'Copié'
    // },
    alias: {
      '/([a-z]{2})/(.*)/(.*)': '/docs/$1/$2/$3', // remove 'docs' in url
      '/([a-z]{2})/(.*)': '/docs/$1/$2', // remove 'docs' in url
      ['/_sidebar.md']: `/docs/${locale}/_sidebar.md`, // set default _sidebar.md to locale language
      ['/_navbar.md']: `/docs/${locale}/_navbar.md`, // set default _sidebar.md to locale language
      [`/${locale}`]: '/',
      'readme.md': `/docs/${locale}/README.md`,
    },
    search: {
      placeholder: {
        '/fr/': 'Rechercher...',
        '/en/': 'Type to search',
        '/es/': 'Buscar',
        '/': 'Rechercher...'
      },
      noData: {
        '/fr/': 'Pas de résultat...',
        '/es/': 'No resulto...',
        '/en': 'No result...',
        '/': 'Pas de résultat...'
      },
      depth: 2,
      pathNamespaces: ['/fr/', '/en/','/', '/cat/', '/es/'],

    },
    plugins: [
      function(hook, vm) {
        hook.mounted(function() {
          // Move the title inside the navbar to the top of sidebar, and set
          // correct href
          setTimeout(() => {
            let title = document.querySelector("#back")
            title.href = baseUrl
            let sidebar = document.querySelector('.sidebar')
            sidebar.insertBefore(title, sidebar.children[0]);
          }, 100)
        });
      }
    ]
}