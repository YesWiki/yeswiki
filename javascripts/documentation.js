window.$docsify = {
    homepage: 'docs/fr/README.md',
    loadSidebar: 'docs/fr/_sidebar.md',
    loadNavbar: 'docs/fr/_navbar.md',
    subMaxLevel: 3,
    relativePath: false,
    auto2top: true,
    name: 'Documentation YesWiki',
    nameLink: {
      '/en/': '#/en/',
      '/es/': '#/es/',
      '/cat/': '#/cat/',
      '/fr': '#/fr',
      '/': '#/'
    },
    repo: '',
    copyCode: {
      buttonText : 'Copier',
      errorText  : 'Erreur',
      successText: 'Copié'
    },
    alias: {
      '/fr' : '/'
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
      pathNamespaces: ['/fr/', '/', '/cat/', '/es/'],

    }
}