window.$docsify = {
    homepage: `docs/${wiki.locale}/README.md`,
    loadSidebar: true,
    loadNavbar: true,
    subMaxLevel: 3,
    relativePath: true,
    auto2top: true,
    fallbackLanguages: ['en',`${wiki.locale}`],
    name: _t('DOCUMENTATION_TITLE'),
    nameLink: {
      '/en/': '#/docs/en/',
      // '/es/': '#/docs/es/',
      // '/cat/': '#/docs/cat/',
      '/fr': '#/docs/fr',
      '/': '#/docs/'
    },
    // repo: 'https://github.com/YesWiki/yeswiki/',
    // copyCode: { // not used because extension copy code not installed
    //   buttonText : 'Copier',
    //   errorText  : 'Erreur',
    //   successText: 'Copié'
    // },
    alias: {
      // '/([a-z]{2})/(.*)/(.*)': '/docs/$1/$2/$3', // remove 'docs' in url
      // '/([a-z]{2})/(.*)': '/docs/$1/$2', // remove 'docs' in url
      ['/_sidebar.md']: `/docs/${wiki.locale}/_sidebar.md`, // set default _sidebar.md to locale language
      ['/_navbar.md']: `/docs/${wiki.locale}/_navbar.md`, // set default _sidebar.md to locale language
      [`/${wiki.locale}`]: '/',
      'readme.md': `/docs/${wiki.locale}/README.md`,
    },
    search: {
      placeholder: {
        '/docs/fr/': 'Rechercher...',
        '/docs/en/': 'Type to search',
        '/docs/es/': 'Buscar',
        '/docs/': 'Rechercher...'
      },
      noData: {
        '/docs/fr/': 'Pas de résultat...',
        '/docs/es/': 'No resulto...',
        '/docs/en': 'No result...',
        '/docs/': 'Pas de résultat...'
      },
      depth: 2,
      pathNamespaces: [
        '/docs/fr/',
        '/docs/en/',
        // '/docs/cat/',
        // '/docs/es/',
        '/docs/'
      ],

    }
}