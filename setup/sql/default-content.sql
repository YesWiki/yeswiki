# YesWiki pages
INSERT INTO `{{prefix}}pages` (`tag`, `time`, `body`, `body_r`, `owner`, `user`, `latest`, `handler`, `comment_on`) VALUES
('AnnuaireAlpha',  now(), '{{nav links=\"TrombiAnnuaire, AnnuaireAlpha, CartoAnnuaire, SaisirAnnuaire\" titles=\"Trombinoscope, Annuaire alphabétique, Annuaire cartographique, S\'inscrire dans l\'annuaire\"}}

{{bazarliste id=\"1\" template=\"annuaire_alphabetique\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('BacASable',  now(), '# Bac à sable
## Premiers défis à réaliser
1) premier défi => **écrire dans cette page**
  - cliquez sur \"éditer la page\" (en bas) ou double cliquez dans la page,
  - l\'aspect de la page va légèrement changer car vous êtes en __mode édition__
  - écrivez ce que vous voulez ici => 
  - puis cliquez sur le bouton \"sauver\" (en haut à gauche) et observez votre travail

2) deuxième défi => **insérer un bouton**
  - cliquez sur \"éditer la page\" ou double cliquez dans la page,
  - Positionnez votre curseur ici
  - cliquez sur __composants__ / boutons et laissez vous guider,
  - cliquez sur \"insérer dans la page\",
  - sauvez
   - vous pourrez ensuite explorer les autres composants

3) troisième défi => **modifier votre bouton**
  - Passez la page en mode édition,
  - cliquez sur la ligne correspondant au code du bouton => un petit crayon apparaît dans la marge,
  - cliquez sur __le petit crayon__ et changez les paramètres,
  - cliquez sur \"mettre à jour le code\",
  - sauvez
   - Cette démarche de modification fonctionnera pour tous les codes des composants

4) quatrième défi => **restaurer la version précédente d\'une page** (en cas de préférence ou d\'erreur)
  - cliquez, en bas de la page, sur Dernière édition
  - choisissez une des versions précédentes,
  - cliquez sur \"Restaurer cette version\",
  - le tour est joué.

5) cinquième défi => **insérer une image** 
  - en édition, placez-vous tout en bas de cette page
  - cliquez ensuite sur fichier dans la berre d\'édition
  - choisissez un fichier image (jpeg, png ou gif) de moins de 500 ko sur votre ordinateur
  - jouez avec les paramètres
  - sauvez

6) sixième défi => **trouver le nom d\'une page**
  - Regardez l\'url de cette page 
  - le nom de cette page est le mot se situant après le ?
  - maintenant cherchez le nom de la page d\'accueil
  - ensuite transformer \"retour vers la page d\'accueil\" en un lien cliquable vers cette page

Une aide simple est aisément accessible en cliquant sur l\'icone \"?\" lorsque vous êtes en mode édition.', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('BazaR',  now(), '{{bazar showexportbuttons=\"1\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('CartoAnnuaire',  now(), '{{nav links=\"TrombiAnnuaire, AnnuaireAlpha, CartoAnnuaire, SaisirAnnuaire\" titles=\"Trombinoscope, Annuaire alphabétique, Annuaire cartographique, S\'inscrire dans l\'annuaire\"}}

{{bazarliste id=\"1\" template=\"map\" markersize=\"small\" height=\"800px\" zoom=\"6\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('DerniersChangementsRSS',  now(), '{{recentchangesrss}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ExempleFormulaire',  now(), '# Exemples de formulaires à adapter (ou à jeter)
Les formulaires qui vous sont proposés dans ce menu sont souvent demandés par les collectifs.
Ils sont fournis pour inspiration et __sont bien sûr adaptables (ou supprimables)__ via la page BazaR.
Vous pouvez aussi renommer-réorganiser-enlever les pages de ce menu selon vos besoins. 

Vous trouverez un formulaire permettant 
 - de gérer un [annuaire](TrombiAnnuaire) (des membres du collectif par exemple)
 - un [agenda](VueActivite) pour présenter les activités __à venir__ ou une vue globale en calendrier
 - une [ressourcerie](FacetteRessource) pour collecter, filtrer et partager des ressources
 - un [blog](VoirBlog) permettant d\'afficher l\'actualité du collectif', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('FacetteRessource',  now(), '{{nav links=\"FacetteRessource, SaisirRessource\" titles=\"Les ressources, Déposer une ressource\"}}

{{bazarliste id=\"4\" template=\"liste_accordeon\" correspondance=\"soustitre=bf_description\" groups=\"checkboxListeType\" titles=\"Tri par type\" voirmenu=\"0\" vue=\"recherche\" }}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererConfig',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurices, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

{{editconfig}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererDroits',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurices, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

{{button class=\"btn-primary btn-xs pull-right\" hideifnoaccess=\"true\" icon=\"fas fa-arrow-right\" link=\"GererDroitsActions\" text=\"Droits des actions/handlers\" }}
#### Gérer les droits des pages
{{gererdroits}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererDroitsActions',  now(), '//Droits d\'accès//
{{nav class=\"nav nav-tabs\" hideifnoaccess=\"true\" links=\"GererDroitsActions, GererDroitsHandlers, GererDroits\" titles=\"Actions, Handlers, Pages\" }}

#### Droits d\'accès aux actions

{{editactionsacls}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererDroitsHandlers',  now(), '//Droits d\'accès//
{{nav class=\"nav nav-tabs\" hideifnoaccess=\"true\" links=\"GererDroitsActions, GererDroitsHandlers, GererDroits\" titles=\"Actions, Handlers, Pages\" }}

#### Droits d\'accès aux handlers

{{edithandlersacls}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererMisesAJour',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurices, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

#### Mises à jour / extensions
{{update}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererSauvegardes',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurices, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

{{adminbackups}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererSite',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurices, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

{{attach file=\"modele.jpg\" desc=\"image Dessin_sans_titre.jpg (66.9kB)\" size=\"big\" class=\"right\" nofullimagelink=\"1\"}}
#### Gérer les menus et pages spéciales de ce wiki
 - [Éditer la Page Titre](PageTitre/edit)
 - [Éditer la Page Menu Haut](PageMenuHaut/edit)
 - [Éditer la Page Footer](PageFooter/edit)
##### Et éventuellement
 - [Éditer la Page Rapide Haut](PageRapideHaut/edit)
##### Mais aussi
 - [Éditer le menu vertical](PageMenu/edit)
 - [Éditer l\'aide mémoire](ReglesDeFormatage/edit)
 - [Afficher un bandeau sur toutes les pages](PageHeader/edit "Aplat de couleurs ou images")
\"\"<div class=\"clearfix\"></div>\"\"
#### Gestion des mots clés 
{{admintag}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererThemes',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurices, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

{{button class=\"btn-info\" link=\"LookWiki\" text=\"Personnaliser le look de ce wiki (couleurs, police...)\" }}
{{button class=\"btn-info pull-right\" link=\"PageCss\" text=\"Ajouter du code CSS (zone sensible)\" title=\"Cette page ne peut contenir QUE du Css / voir le document\" }}

------

#### Gérer les thèmes des pages
{{gererthemes}}
-----
#### Gérer le thème par défaut du wiki
{{setwikidefaulttheme}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererUtilisateurs',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurices, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

#### Gérer les groupes d\'utilisateurices
{{editgroups}}

#### Gérer les utilisateurices
{{userstable}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('LookWiki',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurices, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

{{button class=\"btn-info btn-block\" link=\"LookWiki\" text=\"Personnaliser le thème de ce wiki (couleurs, police...)\" }}
{{button class=\"btn-default btn-block\" link=\"PageCss\" text=\"Ajouter du code CSS (zone sensible)\" title=\"Cette page ne peut contenir QUE du Css / voir le document\" }}


# Personnaliser l\'apparence

{{section bgcolor=\"#d9ead3\" class=\"cover black  shape-rounded text-left\" }}
Pour modifier l\'apparence de votre wiki, utilisez le sélecteur de thème ci-dessous.
L\'aperçu de cette page sera mis à jour en direct mais le style ne sera appliqué à votre site que lorsqu\'un administrateur aura validé en cliquant sur le bouton \"Mettre à jour\".
Pour plus dinformations sur les possibiltés de personnalisation graphique [consultez la documentation](?doc#/docs/fr/admin?id=visualisermodifier-le-thème-graphique-affecté-à-chaque-page-de-votre-wiki)
{{end elem=\"section\"}}

{{grid}}
{{col size=\"4\"}}
## Thème, Squelette, Style
### Choix du squelette	
**1col.tpl.html** (squelette par defaut)
**1col.vertical-menu.tpl.html** : déplace le menu principal vers une colonne gauche
**2cols-left.tpl.html** ajoute une colonne à gauche qui contient PageMenu
**2cols-right.tpl.html** ajoute une colonne à droite qui contient PagecolonneDroite
**full-page** exploite lécran en pleine largeur
### Choix du style
**Margot.css** : Barre de menu colorée
**Margot-fun.css** : design de la barre de menu sous forme donglets et formes géométriques sur les titres de page
**Light.css** : Barre de menu blanche
{{end elem=\"col\"}}
{{col size=\"4\"}}
## Configuration graphique
En créant \"une nouvelle configuration graphique\", vous pouvez définir des variantes de couleurs :
**une couleur primaire** : cest votre couleur dominante, elle sera utilisée par défaut pour la barre de menu ainsi que pour les titres et les liens.
**2 couleurs secondaires** peu visibles par defaut mais vous pourrez facilement les utiliser dans vos éléments de mise en forme.
**la couleur de votre texte** et couleur du fond : si vous les modifiez, veillez à bien respecter le contraste pour assurer la lisibilité des contenus.
{{end elem=\"col\"}}
{{col size=\"4\"}}
{{section class=\"well\" nocontainer=\"1\"}}
	### Choisir le thème, styles et squelettes associés
	{{themeselector}}
{{end elem=\"section\"}}
{{end elem=\"col\"}}
{{end elem=\"grid\"}}

{{section class=\"full-width black text-center\" bgcolor=\"var(--secondary-color-2)\" height=\"250\"}}
# Composants graphiques
 
Ce bandeau de couleur a été créé grâce au composant de mise en forme nommé **section**

{{end elem=\"section\"}}

### Boutons
{{button link=\"config/root_page\" class=\"btn btn-default\" text=\"btn-default\"}}
{{button link=\"config/root_page\" class=\"btn btn-primary\" text=\"btn-primary\"}}
{{button link=\"config/root_page\" class=\"btn btn-secondary-1\" text=\"btn-secondary-1\"}}
{{button link=\"config/root_page\" class=\"btn btn-secondary-2\" text=\"btn-secondary-2\"}}
{{button link=\"config/root_page\" class=\"btn btn-success\" text=\"btn-success\"}}
{{button link=\"config/root_page\" class=\"btn btn-info\" text=\"btn-info\"}}

### Etiquettes
{{label}}Label-default{{end elem=\"label\"}}
{{label class=\"label-primary\"}}label-primary{{end elem=\"label\"}}   
{{label class=\"label-secondary-1\"}}label-secondary-1{{end elem=\"label\"}}   
{{label class=\"label-secondary-2\"}}label-secondary-2{{end elem=\"label\"}}
{{label class=\"label-success\"}}label-success{{end elem=\"label\"}}  
{{label class=\"label-info\"}}label-info{{end elem=\"label\"}}  
{{label class=\"label-warning\"}}label-warning{{end elem=\"label\"}}  
{{label class=\"label-danger\"}}label-danger{{end elem=\"label\"}}

### Encadrés
{{panel title=\"Titre default\" type=\"collapsed\"}}
Contenu panel-default
{{end elem=\"panel\"}}
{{panel class=\"panel-primary\" title=\"Titre primary\" type=\"collapsible\"}}
Contenu panel-primary
{{end elem=\"panel\"}}
{{panel class=\"panel-secondary-1\" title=\"Titre secondary-1\"}}
Contenu panel-secondary-1
{{end elem=\"panel\"}}
{{panel class=\"panel-secondary-2\" title=\"Titre secondary-2\"}}
Contenu panel-secondary-2
{{end elem=\"panel\"}}

### Accordéons
{{accordion}}
{{panel class=\"panel-success\" title=\"Titre success\"}}
Contenu panel-success
{{end elem=\"panel\"}}
{{panel class=\"panel-warning\" title=\"Titre warning\"}}
Contenu panel-warning
{{end elem=\"panel\"}}
{{panel class=\"panel-danger\" title=\"Titre danger\"}}
Contenu panel-danger
{{end elem=\"panel\"}}
{{end elem=\"accordion\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('MesContenus',  now(), '# Mes contenus
{{accordion }}
{{panel title=\"Mes paramètres\"}}
{{UserSettings}}
{{lostpassword}}
{{end elem=\"panel\"}}
{{panel title=\"Mes pages\"}}
{{mypages}}
{{end elem=\"panel\"}}
{{panel title=\"Mes favoris\"}}
{{myfavorites template=\"my-favorites-tiles.twig\" }}
{{myfavorites template=\"my-favorites-with-titles.twig\"}}
{{myfavorites template=\"my-favorites-table.twig\"}}
{{end elem=\"panel\"}}
{{panel title=\"Mes fiches bazar\"}}
{{bazarliste template=\"liste_accordeon\" dynamic=\"true\" filteruserasowner=\"true\"}}
{{end elem=\"panel\"}}
{{panel title=\"Mes changements\"}}
{{mychanges}}
{{end elem=\"panel\"}}
{{panel title=\"Mes votes, réactions\"}}
{{userreactions}}
{{end elem=\"panel\"}}
{{panel title=\"Mes commentaires\"}}
{{usercomments}}
{{end elem=\"panel\"}}
{{end elem=\"accordion\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('MotDePassePerdu',  now(), '{{lostpassword}}
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageColonneDroite',  now(), 'Double cliquer sur ce texte pour éditer cette colonne.











\"\"\"\"
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageCss',  now(), '/*
Voici un exemple de css custom pour le theme margot, il agit sur les variables css non personnalisables dans le theme et permet de faire des css sur mesure.
Chaque ligne ci-dessous est à décommenter pour etre utilisée
Pour en savoir plus, <a href=\"?doc#/docs/fr/admin?id=ajouter-du-code-css-personnalis%c3%a9\">voyez la documentation à ce sujet</a>.
*/



/*:root { */

/* couleur des titres */
/* --title-h1-color:var(--neutral-color); */
/* --title-h2-color:var(--primary-color); */
/* --title-h3-color:var(--secondary-color-1); */
/* --title-h4-color:var(--secondary-color-2); */
 
/* couleur pour les messages positifs par defaut vert */
/* --success-color: #3cab3b; */

/* couleur pour les messages d\'erreur par defaut rouge */
/* --danger-color: #d8604c;  */

/* couleur pour les messages d\'alerte par defaut orange */ 
/* --warning-color: #D78958; */

/* couleur de fond de la partie centrale votre wiki */
/* --main-container-bg-color:var(--neutral-light-color); */

/* couleur des liens */
/* --link-color: var(--primary-color);  */

/* couleur des liens au survol */
/* --link-hover-color: var(--primary-color);  */

/* couleur de la barre de menu */
/* --navbar-bg-color: var(--primary-color); */

/* --navbar-text-color: var(--neutral-light-color); */

/* --navbar-link-color: var(--neutral-light-color); */

/* --navbar-link-bg-color: transparent; */

/* --navbar-link-hover-color: rgba(255,255,255,0.85); */

/* --navbar-link-bg-hover-color: transparent; */

/* --navbar-border: none; */

/* --navbar-border-radius: 0; */

/* --navbar-shadow: none; */

/* --header-bg-color: var(--neutral-light-color); */

/* --header-text-color: var(--neutral-color); */

/* --header-title-color: var(--primary-color); */

/* couleur de fond du pied de page */
/* --footer-bg-color: transparent; */

/* --footer-text-color: var(--main-text-color); */

/* --footer-title-color: var(--main-text-color); */

/* --footer-border: 3px solid var(--neutral-soft-color); */

/* --btn-border: none; */

/* --btn-border-radius: .5em; */

/* --checkbox-color: var(--primary-color); */

/* } */', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageFooter',  now(), '{{section class=\"text-center\"}}
{{yeswikiversion}}
{{end elem=\"section\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageHeader',  now(), '{# Si vous activez ce code, une page header (bandeau du haut) apparaitra dans TOUTES les pages de votre site :

{{section bgcolor=\"var(--neutral-color)\" class=\"white text-center cover\" file=\"bandeau.webp\" }}
# Titre de ce bandeau
{{end elem=\"section\"}}

Pour changer l\'image du bandeau : renommer bandeau.webp par le nom de votre nouvelle image (webp, jpg, png). Sauver, puis charger votre image préalablement préparée
(cette image devra avoir comme taille 1920 X 300 et 90 dpi de résolution)

Aplat de couleur : supprimer file=\"bandeau.webp\", cliquez sur section, cliquez sur le petit crayon dans la marge et laissez vous guider. vous pourrez 
 - changer la tonalité du texte
 - le caler à droite, le centrer
 - faire varier la hauteur du bandeau...#}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageLogin',  now(), '{{login}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageMenu',  now(), ' - [Accueil]({{rootPage}})
 - [Test du look](LookWiki)

---- 

Double cliquer sur ce texte pour éditer cette colonne.











\"\"\"\"', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageMenuHaut',  now(), ' - [Bac à sable](BacASable)
 - Menu exemple
  - [Exemple annuaire](TrombiAnnuaire)
  - [Exemple agenda](VueActivite)
  - [Exemple ressourcerie](FacetteRessource)
  - [Exemple blog](VoirBlog)
{#INFO CACHÉE
Vous êtes dans la page qui se nomme PageMenuHaut qui sert à modifier le menu du haut. Pour faire évoluer le menu, inspirez vous du menu exemple.
#}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('{{rootPage}}',  now(), '{{section bgcolor=\"#eeeeee\" class=\"cover full-width text-left\" file=\"\"}}
# Félicitations, votre wiki est installé !
**Pour modifier ce bandeau, éditez cette page.**
{{end elem=\"section\"}}

{#----------------------------------------------------------------------
- 👆Pour modifier ce bandeau, cliquez sur la ligne section et le petit crayon et laissez-vous guider 
- 👇Texte par défaut de la page d\'accueil à remplacer par le votre 👇
----------------------------------------------------------------------#}

## YesWiki : un outil convivial potentiellement collaboratif
\"\"YesWiki\"\" a été conçu pour rester **simple d\'usage**.
Il renferme des **fonctionnalités cachées**, installées par défaut, pouvant **être activées au fur et à mesure** de l\'émergence des besoins.
Pour cela vous pourrez facilement dans [YesWiki](https://www.yeswiki.net \"Voir le site officiel de YesWiki\"){.newtab} : 
 - modifier une page, (ré)organiser les menus
 - choisir **le rendu graphique** ou l\'adapter à vos envies
 - concevoir des **formulaires pour récolter des donnés** diverses
 - présenter ces données [sous des rendus variés](ExempleFormulaire \"sous des rendus variés\"){.newtab} (agenda, carte, listes, annuaire, album...)
 - **exporter ou importer** des données sous des formats ouverts (csv, json, webhooks)
 - triturer, **adapter**, prototyper complètement le site [selon vos besoins](https://yeswiki.net/?IlsUtilisentYesWiki \"Voir des exemples de wikis\"){.newtab}
 - récupérer la structure, les formulaires d\'autres \"\"YesWikis\"\" pour les adapter à vos projets
 - installer des extensions pour activer de nouvelles fonctionnalités (LMS pour créer des parcours de formation, générateur d\'ebooks, authentification LDAP...)

Si vous voulez **vous exercer** sereinement, vous pouvez essayer de modifier la page [bac à sable](BacASable) où quelques défis vous seront proposés. 

## YesWiki : une communauté
En plus d\'être un logiciel de création de wikis, \"\"YesWiki\"\" est aujourd\'hui maintenu et amélioré par une communauté de professionnels et d\'utilisateurices issus d\'horizons différents qui prend du plaisir à partager ses rêves, ses créations et ses développements. Nous serons ravi·e·s de vous y accueillir !

Pour nous rejoindre ou avoir une vision sur les chantiers actuellement en cours, voici notre [espace central](https://yeswiki.net/?LaGareCentrale).

Si vous souhaitez simplement être tenu·e informé·e des nouveautés de l\'outil et de ses améliorations, [💌 abonnez-vous à notre newsletter](https://landing.mailerlite.com/webforms/landing/c0j7n7 \"abonnez-vous à notre newsletter\"){.newtab} 

Yeswiki repose sur le bénévolat et le don. [En contribuant (même juste un peu)](https://www.helloasso.com/associations/yeswiki/formulaires/1) vous permettez de maintenir les serveurs et de développer de nouvelles fonctionnalités. Merci', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageRapideHaut',  now(), '{{moteurrecherche template=\"moteurrecherche_button.tpl.html\"}}
{{buttondropdown icon=\"cog\" caret=\"0\" title=\"Gestion du site\"}}
 - {{button nobtn=\"1\" icon=\"fa fa-tachometer-alt\" text=\"Tableau de bord\" link=\"TableauDeBord\"}}
 - {{button nobtn=\"1\" icon=\"fa fa-question\" text=\"Documentation\" link=\"doc\"}}
 - ------
 - {{button nobtn=\"1\" icon=\"fa fa-wrench\" text=\"Gestion du site\" link=\"GererSite\"}}
 - {{button nobtn=\"1\" icon=\"fas fa-user\" text=\"Mes contenus\" link=\"MesContenus\"}}
 - {{button nobtn=\"1\" icon=\"fa fa-briefcase\" text=\"Formulaires\" link=\"BazaR\"}}
{{end elem=\"buttondropdown\"}}
{{login template=\"modal.tpl.html\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageTitre',  now(), '{{configuration param=\"wakka_name\" }}

{#Astuce, vous pouvez remplacer le code précédent par ce que vous souhaitez afficher comme titre en haut à gauche #}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ParametresUtilisateur',  now(), '{{UserSettings}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('RechercheTexte',  now(), '{{newtextsearch}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ReglesDeFormatage',  now(), 'N\'hésitez pas à personnaliser cette page d\'aide (code utile, astuce...) en cliquant sur [ReglesDeFormatage](ReglesDeFormatage/edit){.newtab}
{#Placez votre aide personnalisée entre ici#}

{#et ici#}
{{grid}}
{{col size=\"6\"}}
#### Insérer un iframe
//Inclure un autre site, ou un pad, ou une vidéo youtube, etc...//
`\"\"<iframe width=100% height=\"1250\" src=\"http://exemple.com\" frameborder=\"0\" allowfullscreen></iframe>\"\"`

#### Ecrire en html
\"\"<pre>si vous déposez du html dans la page wiki, 
il faut l\'entourer de &quot;&quot; <bout de html> &quot;&quot; 
pour qu\'il soit interprété</pre>\"\"
#### Obtenir un tableau
\"\"<pre>
&#x5B;|
|**Nom**|**prénom**|**Couleurs préférées**|
|Lagaffe|Gaston|jaune|
|Lapalice|Jean|vert|
|]
</pre>\"\"
donne
[|
|**Nom**|**prénom**|**Couleurs préférées**|
|Lagaffe|Gaston|jaune|
|Lapalice|Jean|vert|
|]
#### Eviter qu\'un mot avec deux majuscules ne soit reconnu comme lien vers une page wiki
\"\"<pre>Il faut l\'entourer de &quot;&quot; &quot;&quot; (ex : &quot;&quot; MonMotAvecDeuxMajuscules &quot;&quot;) pour qu\'il ne soit pas interprété comme lien wiki</pre>\"\"
#### Créer une ancre, un lien qui envoie sur une partie de votre page
Le code suivant permet de créer le lien qui ira vers votre paragraphe
`\"\"<a href=\"#ancre1\">Aller vers le paragraphe cible</a>\"\"`
Et cette partie de code sera à placer juste au dessus du paragraphe cible
`\"\"<div id=\"ancre1\"></div>\"\"`
{{end elem=\"col\"}}
{{col size=\"6\"}}
#### Mettre du texte en couleur
`\"\"<span style=\"color:#votrecodecouleur;\">votre texte à colorer</span>\"\"`
//Quelques codes couleur => mauve : #990066 / vert : #99cc33 / rouge : #cc3333 / orange : #ff9900 / bleu : #006699//
//Voir les codes hexa des couleurs : [https://fr.wikipedia.org/wiki/Liste_de_couleurs](https://fr.wikipedia.org/wiki/Liste_de_couleurs)//

#### Aligner du texte
Placez votre texte dans une section (voir composant) et choisissez votre alignement (gauche, centré, droite, justifié)

#### Utiliser des icônes Emoji
Il est possible de copier des icônes dans des sites sources puis de les coller dans votre wiki. \"\"<a href=\"http://getemoji.com\" target=\"_blank\" class=\"btn btn-primary btn-xs\">Par exemple sur ce site</a>\"\"

#### Afficher une barre de progression
&#x5B;10%] donne [10%]
&#x5B;40%] donne [40%]
&#x5B;80%] donne [80%]

#### Vous trouverez beaucoup d\'autres astuces dans
 - [La documentation](?doc){.newtab}
 - [Le forum](https://forum.yeswiki.net/){.newtab}
 - [Comment faire pour...](https://yeswiki.net/?CommentFairePour){.newtab}

{{end elem=\"col\"}}
{{end elem=\"grid\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('SaisirAgenda',  now(), '{{nav links=\"VueActivite, VueAgenda, SaisirAgenda\" titles=\"Voir les prochaines activités, Voir l\'agenda, Proposer une activité\"}}

{{bazar voirmenu=\"0\" vue=\"saisir\" id=\"2\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('SaisirAnnuaire',  now(), '{{nav links=\"TrombiAnnuaire, AnnuaireAlpha, CartoAnnuaire, SaisirAnnuaire\" titles=\"Trombinoscope, Annuaire alphabétique, Annuaire cartographique, S\'inscrire dans l\'annuaire\"}}

{{bazar voirmenu=\"0\" vue=\"saisir\" id=\"1\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('SaisirBlog',  now(), '{{nav links=\"VoirBlog, VoirBlogSimple, SaisirBlog\" titles=\"Le blog avec Une, Le blog sans Une, Déposer une actu\"}}

{{bazar vue=\"saisir\" voirmenu=\"0\" id=\"3\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('SaisirRessource',  now(), '{{nav links=\"FacetteRessource, SaisirRessource\" titles=\"Les ressources, Déposer une ressource\"}}

{{bazar voirmenu=\"0\" vue=\"saisir\" id=\"4\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('TableauDeBord',  now(), '# Tableau de bord
{{accordion }}

{{panel title=\"Dernières modifications sur le wiki\" type=\"collapsible\" }}
{{grid}}
{{col size=\"6\"}}
### 12 Derniers comptes utilisateurices 
{{Listusers/last=\"12\"}}
{{end elem=\"col\"}}
{{col size=\"6\"}}
### 12 Dernières pages modifiées 
{{recentchanges max=\"12\"}}
{{end elem=\"col\"}}
{{end elem=\"grid\"}}
{{end elem=\"panel\"}}
{{panel title=\"Index des pages et fiches du wiki\" type=\"collapsed\" }}
{{grid}}
{{col size=\"4\"}}
###  Pages orphelines 
{{OrphanedPages}}
{{end elem=\"col\"}}
{{col size=\"4\"}}
###  Index des fiches bazar 
{{bazarrecordsindex}}
{{end elem=\"col\"}}
{{col size=\"4\"}}
###  Index des pages seules 
{{pageonlyindex}}
{{end elem=\"col\"}}
{{end elem=\"grid\"}}
{{end elem=\"panel\"}}
{{end elem=\"accordion\" }}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('TrombiAnnuaire',  now(), '{{nav links=\"TrombiAnnuaire, AnnuaireAlpha, CartoAnnuaire, SaisirAnnuaire\" titles=\"Trombinoscope, Annuaire alphabétique, Annuaire cartographique, S\'inscrire dans l\'annuaire\"}}

{{bazarliste id=\"1\" template=\"card\"  displayfields=\"visual=imagebf_image,title=bf_titre\" imgstyle=\"contain\" nbcol=\"3\" style=\"square\" }}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('VoirBlog',  now(), '{{nav links=\"VoirBlog, VoirBlogSimple, SaisirBlog\" titles=\"Le blog avec Une, Le blog sans Une, Déposer une actu\"}}

{{bazarliste id=\"3\" template=\"blog\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('VoirBlogSimple',  now(), '{{nav links=\"VoirBlog, VoirBlogSimple, SaisirBlog\" titles=\"Le blog avec Une, Le blog sans Une, Déposer une actu\"}}

{{bazarliste id=\"3\" template=\"blog\" header=\"no\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('VueActivite',  now(), '{{nav links=\"VueActivite, VueAgenda, SaisirAgenda\" titles=\"Voir les prochaines activités, Voir l\'agenda, Proposer une activité\"}}

{{bazarliste template=\"agenda\" id=\"2\" ordre=\"desc\" champ=\"bf_date_debut_evenement\" agenda=\"futur\" }}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('VueAgenda',  now(), '{{nav links=\"VueActivite, VueAgenda, SaisirAgenda\" titles=\"Voir les prochaines activités, Voir l\'agenda, Proposer une activité\"}}

{{bazarliste id=\"2\" template=\"calendar\" }}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('WikiAdmin',  now(), '{{redirect page=\"GererSite\"}}
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('YesWiki',  now(), 'YesWiki', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', '');
# end YesWiki pages

# Bazar forms
INSERT INTO `{{prefix}}nature` (`bn_id_nature`, `bn_label_nature`, `bn_description`, `bn_condition`, `bn_sem_context`, `bn_sem_type`, `bn_sem_use_template`, `bn_template`, `bn_ce_i18n`, `bn_only_one_entry`, `bn_only_one_entry_message`) VALUES
('2', 'Agenda', '', '', 'https://www.w3.org/ns/activitystreams', 'Event', '1', 'texte***bf_titre***Nom de l\'événement***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\r\ntextelong***bf_description***Description***40*** *** *** ***wiki***0*** *** *** * *** * *** *** *** ***\r\nlistedatedeb***bf_date_debut_evenement***Début de l\'événement*** *** ***today*** *** ***1*** *** *** * *** * *** *** *** ***\r\nlistedatefin***bf_date_fin_evenement***Fin de l\'événement*** *** ***today*** *** ***1*** *** *** * *** * *** *** *** ***\r\nlien_internet***bf_site_internet***Adresse url*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***\r\nimage***bf_image***Image (facultatif)***140***140***600***600***right***0*** ***Votre image doit être au format .jpg ou .gif ou .png*** * *** * *** *** *** ***\r\nfichier***fichier***Documents***20000000*** *** *** ***file***0*** *** *** * *** * *** *** *** ***\r\ntexte***bf_adresse***Adresse***50***50*** *** ***text***0*** *** *** * *** * *** *** *** ***\r\ntexte***bf_code_postal***Code postal***8***8*** *** ***text***0*** *** *** * *** * *** *** *** ***\r\ntexte***bf_ville***Ville***50***80*** *** ***text***0*** *** *** * *** * *** *** *** ***\r\nmap***bf_latitude***bf_longitude*** *** *** *** *** ***0***\r\nlabelhtml***<h3>Il ne vous reste plus qu\'à valider ! </h3>*** *** ***\r\n', 'fr-FR', 'N', ''),
('1', 'Annuaire', '', '', '', '', '1', 'titre***{{bf_nom}} {{bf_prenom}}***Titre Automatique***\r\ntexte***bf_nom***Nom***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\r\ntexte***bf_prenom***Prénom***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\r\nimage***bf_image***Image de présentation (facultatif mais c\'est plus sympa)***140***140***600***600***right***0*** *** *** * *** * *** *** *** ***\r\ntexte***bf_fonction***Mon métier, ma fonction***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***\r\ntextelong***bf_projet***Ma présentation***5***5*** *** ***html***0*** *** *** * *** * *** *** *** ***\r\nchamps_mail***bf_mail***Email (n\'apparaitra pas sur le web)*** *** *** ***form*** ***1***0*** *** * *** * *** *** *** ***\r\ntexte***bf_structure***Nom de la structure***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***\r\nlien_internet***bf_site_internet***Site Internet*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***\r\ntexte***bf_adresse***Adresse***50***50*** *** ***text***0*** *** *** * *** * *** *** *** ***\r\ntexte***bf_code_postal***Code postal***8***8*** *** ***text***0*** *** *** * *** * *** *** *** ***\r\ntexte***bf_ville***Ville***50***80*** *** ***text***0*** *** *** * *** * *** *** *** ***\r\nmap***bf_latitude***bf_longitude*** *** ***\r\nlabelhtml***<h3>Il ne vous reste plus qu\'à valider ! </h3>*** *** ***\r\n', 'fr-FR', 'N', ''),
('3', 'Blog-actu', '', '', '', '', '1', 'image***bf_image***Image***400***300***1200***900***right***1*** *** *** * *** * *** *** *** ***\r\ntexte***bf_titre***Titre***80***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\r\ntextelong***bf_chapeau***Résumé***40***3*** *** ***wiki***1*** *** *** * *** * *** *** *** ***\r\ntextelong***bf_description***Billet***40***9*** *** ***wiki***1*** *** *** * *** * *** *** *** ***\r\n', 'fr-FR', 'N', ''),
('4', 'Ressources', 'Un formulaire pour créer un espace de ressources partagées. ', '', '', '', '1', 'texte***bf_titre***Nom de la ressource***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\r\nlien_internet***bf_url***Site web***40***255*** *** ***url***0*** *** *** * *** * *** *** *** ***\r\ncheckbox***ListeType***Type de ressource*** *** *** *** *** ***1*** *** *** * *** * *** *** *** ***\r\ntextelong***bf_description***Description***5***5*** *** ***wiki***0*** *** *** * *** * *** *** *** ***\r\ntexte***bf_auteur***Auteur***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***\r\nimage***bf_image***Image de présentation (facultatif)***140***140***600***600***right***0*** *** *** * *** * *** *** *** ***\r\nfichier***fichier***Documents***20000000*** *** *** ***file***0*** *** *** * *** * *** *** *** ***\r\nlabelhtml***<h3>Il ne vous reste plus qu\'à valider ! </h3>*** *** ***\r\n', 'fr-FR', 'N', '');
# end Bazar forms

# Bazar lists
INSERT INTO `{{prefix}}pages` (`tag`, `time`, `body`, `body_r`, `owner`, `user`, `latest`, `handler`, `comment_on`) VALUES
('ListeType',  now(), '{\"label\":{\"1\":\"Site web ressource\",\"2\":\"Exp\\u00e9rience inspirante\",\"3\":\"Partenaire ressource\",\"4\":\"M\\u00e9thodologie \\/ guide\"},\"titre_liste\":\"Type\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', '');
INSERT INTO `{{prefix}}triples` (`resource`, `property`, `value`) VALUES
('ListeType', 'http://outils-reseaux.org/_vocabulary/type', 'liste');
# end Bazar lists

# Bazar entries
INSERT INTO `{{prefix}}pages` (`tag`, `time`, `body`, `body_r`, `owner`, `user`, `latest`, `handler`, `comment_on`) VALUES
('Bordeaux',  now(), '{\"bf_titre\":\"Super \\u00e9v\\u00e9nement \\u00e0 Bordeaux\",\"bf_description\":\"Un \\u00e9v\\u00e9nement autour du vin, c\'est pour cela qu\'il est \\u00e0 Bordeaux...\",\"bf_date_debut_evenement\":\"2024-04-10\",\"bf_date_fin_evenement\":\"2024-04-12\",\"bf_site_internet\":\"\",\"bf_adresse\":\"\",\"bf_code_postal\":\"\",\"bf_ville\":\"Bordeaux\",\"bf_latitude\":\"44.841225\",\"bf_longitude\":\"-0.5800364\",\"id_typeannonce\":\"2\",\"id_fiche\":\"Bordeaux\",\"fichierfichier\":\"\",\"geolocation\":{\"bf_latitude\":\"44.841225\",\"bf_longitude\":\"-0.5800364\"},\"date_creation_fiche\":\"2021-06-21 19:33:56\",\"statut_fiche\":\"1\",\"imagebf_image\":\"\",\"date_maj_fiche\":\"2024-04-02 16:17:09\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('YeswikiLeSiteOfficiel',  now(), '{\"bf_titre\":\"Yeswiki : le site officiel\",\"bf_url\":\"https:\\/\\/yeswiki.net\",\"bf_description\":\"Tout ce qu\'il y a \\u00e0 savoir sur Yeswiki \",\"bf_auteur\":\"\",\"id_typeannonce\":\"4\",\"id_fiche\":\"YeswikiLeSiteOfficiel\",\"fichierfichier\":\"\",\"date_creation_fiche\":\"2020-02-12 11:10:01\",\"statut_fiche\":\"1\",\"checkboxListeType\":\"1\",\"imagebf_image\":null,\"date_maj_fiche\":\"2021-09-07 12:10:10\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('FramasofT',  now(), '{\"bf_titre\":\"Framasoft\",\"bf_url\":\"https:\\/\\/framasoft.org\\/fr\\/\",\"bf_description\":\"Framasoft, c\\u2019est une association d\\u2019\\u00e9ducation populaire, un groupe d\\u2019ami\\u00b7es convaincu\\u00b7es qu\\u2019un monde num\\u00e9rique \\u00e9mancipateur est possible, persuad\\u00e9\\u00b7es qu\\u2019il adviendra gr\\u00e2ce \\u00e0 des actions concr\\u00e8tes sur le terrain et en ligne avec vous et pour vous !\",\"bf_auteur\":\"\",\"id_typeannonce\":\"4\",\"id_fiche\":\"FramasofT\",\"fichierfichier\":\"\",\"date_creation_fiche\":\"2020-02-12 14:12:58\",\"statut_fiche\":\"1\",\"checkboxListeType\":\"3\",\"imagebf_image\":null,\"date_maj_fiche\":\"2021-09-07 12:07:38\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('UnBeauLogoPourYeswiki',  now(), '{\"bf_titre\":\"Un beau logo pour Yeswiki\",\"bf_chapeau\":\"Il fallait le rafraichir, nous l\'avons fait ! \",\"bf_description\":\"Apr\\u00e8s multiples discussions, tests et essais, un logo plus actuel a \\u00e9t\\u00e9 cr\\u00e9\\u00e9 pour Yeswiki\\r\\nNous esp\\u00e9rons que vous l\'aimerez ;-) \",\"id_typeannonce\":\"3\",\"id_fiche\":\"UnBeauLogoPourYeswiki\",\"date_creation_fiche\":\"2020-02-12 13:16:06\",\"statut_fiche\":\"1\",\"imagebf_image\":\"UnBeauLogoPourYeswiki_yeswiki-logo.png\",\"date_maj_fiche\":\"2021-09-05 13:23:52\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('UnNouveauThemePourYeswiki',  now(), '{\"bf_titre\":\"Un nouveau th\\u00e8me pour Yeswiki\",\"bf_chapeau\":\"Margot, voil\\u00e0 le nom du nouveau th\\u00e8me qui sera distribu\\u00e9 avec la prochaine version de Yeswiki\\t\",\"bf_description\":\"Plus moderne, mieux pens\\u00e9, plus graphiqu.\\r\\nMargot permettra d\'unifier les rendus graphiques des wikis.\",\"id_typeannonce\":\"3\",\"id_fiche\":\"UnNouveauThemePourYeswiki\",\"date_creation_fiche\":\"2020-02-12 12:17:49\",\"statut_fiche\":\"1\",\"imagebf_image\":\"UnNouveauThemePourYeswiki_capture-de\\u0301cran-2020-02-12-a\\u0300-13.16.33.png\",\"date_maj_fiche\":\"2020-02-12 12:17:50\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ElizabethJFeinler',  now(), '{\"bf_titre\":\"JFeinler Elizabeth\",\"bf_nom\":\"JFeinler\",\"bf_prenom\":\"Elizabeth\",\"bf_fonction\":\"informaticienne, pionni\\u00e8re de l\'internet\",\"bf_projet\":\"En 1974, j\'ai cr\\u00e9\\u00e9 le nouveau Network Information Center (NIC) de l\'ARPANET.  \",\"bf_mail\":\"\",\"bf_structure\":\"Stanford Research Institute et NASA \",\"bf_site_internet\":\"https:\\/\\/fr.wikipedia.org\\/wiki\\/Elizabeth_J._Feinler\",\"bf_adresse\":\"\",\"bf_code_postal\":\"\",\"bf_ville\":\"Paris\",\"bf_latitude\":\"48.8566969\",\"bf_longitude\":\"2.3514616\",\"id_typeannonce\":\"1\",\"id_fiche\":\"ElizabethJFeinler\",\"imagebf_image\":\"ElizabethJFeinler_elizabethfeinler-2011.jpg\",\"geolocation\":{\"bf_latitude\":\"48.8566969\",\"bf_longitude\":\"2.3514616\"},\"date_creation_fiche\":\"2021-05-24 22:07:17\",\"statut_fiche\":\"1\",\"date_maj_fiche\":\"2021-08-06 10:31:00\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('TesT2',  now(), '{\"bf_titre\":\"Sortie Culturelle\",\"bf_description\":\"La culture, moins on en a, plus on l\'\\u00e9tale!\",\"bf_date_debut_evenement\":\"2024-05-30T18:00:00+02:00\",\"bf_date_fin_evenement\":\"2024-05-30T20:00:00+02:00\",\"bf_site_internet\":\"https:\\/\\/www.yeswiki.net\",\"bf_adresse\":\"Avenue des Champs Elys\\u00e9es\",\"bf_code_postal\":\"75000\",\"bf_ville\":\"Paris\",\"bf_latitude\":\"48.8659085\",\"bf_longitude\":\"2.3197651\",\"id_typeannonce\":\"2\",\"id_fiche\":\"TesT2\",\"imagebf_image\":\"TesT2_presence-photo.png\",\"fichierfichier\":\"\",\"geolocation\":{\"bf_latitude\":\"48.8659085\",\"bf_longitude\":\"2.3197651\"},\"date_creation_fiche\":\"2024-04-02 16:25:51\",\"statut_fiche\":\"1\",\"date_maj_fiche\":\"2024-04-02 16:48:20\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('LovelaceAda',  now(), '{\"bf_titre\":\"Lovelace Ada\",\"bf_nom\":\"Lovelace\",\"bf_prenom\":\"Ada\",\"bf_fonction\":\"Pionni\\u00e8re de la science informatique \",\"bf_projet\":\"<p>J\'ai r\\u00e9alis\\u00e9 le premier v\\u00e9ritable programme informatique, lors de mon travail sur un anc\\u00eatre de l\'ordinateur : la machine analytique de Charles Babbage. <br><\\/p>\",\"bf_mail\":\"\",\"bf_structure\":\"Universit\\u00e9 de Cambridge \",\"bf_site_internet\":\"https:\\/\\/fr.wikipedia.org\\/wiki\\/Ada_Lovelace\",\"bf_adresse\":\"\",\"bf_code_postal\":\"\",\"bf_ville\":\"Londres \",\"bf_latitude\":\"51.5073219\",\"bf_longitude\":\"-0.1276474\",\"id_typeannonce\":\"1\",\"id_fiche\":\"LovelaceAda\",\"geolocation\":{\"bf_latitude\":\"51.5073219\",\"bf_longitude\":\"-0.1276474\"},\"date_creation_fiche\":\"2021-05-25 11:00:19\",\"statut_fiche\":\"1\",\"imagebf_image\":\"LovelaceAda_lovelace.png\",\"date_maj_fiche\":\"2021-05-25 11:01:13\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', '');
INSERT INTO `{{prefix}}triples` (`resource`, `property`, `value`) VALUES
('Bordeaux', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('YeswikiLeSiteOfficiel', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('FramasofT', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('UnBeauLogoPourYeswiki', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('UnNouveauThemePourYeswiki', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('ElizabethJFeinler', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('TesT2', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('LovelaceAda', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar');
# end Bazar entries
