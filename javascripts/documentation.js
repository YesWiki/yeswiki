window.$docsify = {
    vueGlobalOptions: {
      // use {% %} for Vue code so it does not conflict with our {{ }} action syntax
      delimiters: ['{%', '%}'],
    },
    homepage: `docs/${wiki.locale}/README.md`,
    loadSidebar: true,
    loadNavbar: true,
    subMaxLevel: 3,
    relativePath: false,
    auto2top: true,
    fallbackLanguages: ['en',`${wiki.locale}`],
    name: _t('DOCUMENTATION_TITLE'),
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
      ['/_sidebar.md']: `/docs/${wiki.locale}/_sidebar.md`, // set default _sidebar.md to locale language
      ['/_navbar.md']: `/docs/${wiki.locale}/_navbar.md`, // set default _sidebar.md to locale language
      [`/${wiki.locale}`]: '/',
      'readme.md': `/docs/${wiki.locale}/README.md`,
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

    }
}