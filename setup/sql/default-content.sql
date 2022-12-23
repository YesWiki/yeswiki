# YesWiki pages
INSERT INTO `{{prefix}}pages` (`tag`, `time`, `body`, `body_r`, `owner`, `user`, `latest`, `handler`, `comment_on`) VALUES
('AccueilYeswiki',  now(), '{{grid}}
{{col size=\"6\"}}
===\"\"YesWiki\"\" : un outil convivial potentiellement collaboratif===
\"\"YesWiki\"\" a été conçu pour rester **simple d\'usage**.
Il renferme des **fonctionnalités cachées**, installées par défaut, pouvant **être activées au fur et à mesure** de l\'émergence des besoins.
Pour cela vous pourrez facilement dans {{button class=\"new-window\" link=\"https://www.yeswiki.net\" nobtn=\"1\" text=\"YesWiki\" title=\"YesWiki\"}} : 
 - modifier une page, (ré)organiser les menus
 - choisir **le rendu graphique** ou l\'adapter à vos envies
 - concevoir des **formulaires pour récolter des donnés** diverses
 - présenter ces données **{{button class=\"new-window\" link=\"ExempleFormulaire\" nobtn=\"1\" text=\"sous des rendus variés\" title=\"sous des rendus variés\"}}** (agenda, carte, listes, annuaire, album...)
 - **exporter ou importer** des données sous des formats ouverts (csv, json, webhooks)
 - triturer, **adapter**, prototyper complètement le site **{{button class=\"new-window\" link=\"https://yeswiki.net/?IlsUtilisentYesWiki\" nobtn=\"1\" text=\"selon vos besoins\" title=\"selon vos besoins\"}}**
 - récupérer la structure, les formulaires d\'autres \"\"YesWikis\"\" pour les adapter à vos projets
 - installer des extensions pour activer de nouvelles fonctionnalités (LMS pour créer des parcours de formation, générateur d\'ebooks, authentification LDAP...)

Si vous voulez **vous exercer** sereinement, vous pouvez essayer de modifier la page [[BacASable bac à sable]] où quelques défis vous seront proposés. 
{{end elem=\"col\"}}
{{col size=\"6\"}}
===\"\"YesWiki\"\" : une communauté===
En plus d\'être un logiciel de création de wikis, \"\"YesWiki\"\" est aujourd\'hui maintenu et amélioré par une communauté de professionnels et d\'utilisateurs issus d\'horizons différents qui prend du plaisir à partager ses rêves, ses créations et ses développements. Nous serons ravi·e·s de vous y accueillir !

Pour nous rejoindre ou avoir une vision sur les chantiers actuellement en cours, voici notre [[https://yeswiki.net/?LaGareCentrale espace central]].

Si vous souhaitez simplement être tenu·e informé·e des nouveautés de l\'outil et de ses améliorations, **{{button class=\"new-window\" link=\"https://landing.mailerlite.com/webforms/landing/c0j7n7\" nobtn=\"1\" text=\"💌 abonnez-vous à notre newsletter\" title=\"abonnez-vous à notre newsletter\"}}** 


Yeswiki repose sur le bénévolat et le don. **[[https://www.helloasso.com/associations/yeswiki/formulaires/1 En contribuant (même juste un peu)]]** vous permettez de maintenir les serveurs et de développer de nouvelles fonctionnalités. Merci

{{end elem=\"col\"}}
{{end elem=\"grid\"}}
{{bazarliste id=\"https://www.yeswiki.net|7\" template=\"carousel.tpl.html\" champ=\"bf_ordre\" ordre=\"asc\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('AnnuaireAlpha',  now(), '{{nav links=\"TrombiAnnuaire, AnnuaireAlpha, CartoAnnuaire, SaisirAnnuaire\" titles=\"Trombinoscope, Annuaire alphabétique, Annuaire cartographique, S\'inscrire dans l\'annuaire\"}}

{{bazarliste id=\"1\" template=\"annuaire_alphabetique\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('BacASable',  now(), '=====Bac à sable=====
===Premiers défis à réaliser===
1) premier défi => **écrire dans cette page**
  - cliquez sur \"éditer la page\" (en bas) ou double cliquez dans la page,
  - l\'aspect de la page va légèrement changer car vous êtes en __mode édition__
  - écrivez ce que vous voulez ici => 
  - puis cliquez sur le bouton \"sauver\" (en haut à gauche) et observez votre travail

2) deuxième défi => **insérer un bouton**
  - cliquez sur \"éditer la page\" ou double cliquez dans la page,
  - Positionnez votre curseur ici => 
  - cliquez sur __composants__ / boutons et laissez vous guider,
  - cliquez sur \"insérer dans la page\",
  - sauvez
   - vous pourrez ensuite explorer les autres composants

3) troisième défi => **modifier votre bouton**
  - Passez la page en mode édition,
  - cliquez sur la ligne correspondant au code du bouton (commençant par \"\"{{button}}\"\")=> un petit crayon apparaît dans la marge,
  - cliquez sur __le petit crayon__ et changez les paramètres,
  - cliquez sur \"mettre à jour le code\",
  - sauvez
   - Cette démarche de modification fonctionnera pour tous les codes des composants

4) quatrième défi => **trouver le nom d\'une page**
  - Regardez l\'url de cette page 
  - le nom de cette page est le mot se situant après le ?

5) Et enfin => **restaurer la version précédente d\'une page** (en cas de préférence ou d\'erreur)
  - cliquez, en bas de la page, sur Dernière édition : 17 jan 2022
  - choisissez une des versions précédentes,
  - cliquez sur \"Restaurer cette version\",
  - le tour est joué.

Une aide simple est aisément accessible en cliquant sur \"aide mémoire ?\" lorsque vous êtes en mode édition.', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('BazaR',  now(), '{{bazar showexportbuttons=\"1\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('CartoAnnuaire',  now(), '{{nav links=\"TrombiAnnuaire, AnnuaireAlpha, CartoAnnuaire, SaisirAnnuaire\" titles=\"Trombinoscope, Annuaire alphabétique, Annuaire cartographique, S\'inscrire dans l\'annuaire\"}}

{{bazarliste id=\"1\" template=\"map\" markersize=\"small\" height=\"800px\" zoom=\"6\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('CoursUtilisationYesWiki',  now(), '======Cours sur l\'utilisation de YesWiki======
====Le principe \"Wiki\"====
Wiki Wiki signifie rapide, en Hawaïen.
==N\'importe qui peut modifier la page==

**Les Wiki sont des dispositifs permettant la modification de pages Web de façon simple, rapide et interactive.**
YesWiki fait partie de la famille des wiki. Il a la particularité d\'être très facile à installer.

=====Mettre du contenu=====
====écrire ou coller du texte====
 - Dans chaque page du site, un double clic sur la page ou un clic sur le lien \"éditer cette page\" en bas de page permet de passer en mode \"édition\".
 - On peut alors écrire ou coller du texte
 - On peut voir un aperçu des modifications ou sauver directement la page modifiée en cliquant sur les boutons en bas de page.

====écrire un commentaire (optionnel)====
Si la configuration de la page permet d\'ajouter des commentaires, on peut cliquer sur : Afficher commentaires/formulaire en bas de chaque page.
Un formulaire apparaîtra et vous permettra de rajouter votre commentaire.


=====Mise en forme : Titres et traits=====
--> Voir la page ReglesDeFormatage

====Faire un titre====
======Très gros titre======
s\'écrit en syntaxe wiki : \"\"======Très gros titre======\"\"


==Petit titre==
s\'écrit en syntaxe wiki : \"\"==Petit titre==\"\"


//On peut mettre entre 2 et 6 = de chaque coté du titre pour qu\'il soit plus petit ou plus grand//

====Faire un trait de séparation====
Pour faire apparaître un trait de séparation
----
s\'écrit en syntaxe wiki : \"\"----\"\"

=====Mise en forme : formatage texte=====
====Mettre le texte en gras====
**texte en gras**
s\'écrit en syntaxe wiki : \"\"**texte en gras**\"\"

====Mettre le texte en italique====
//texte en italique//
s\'écrit en syntaxe wiki : \"\"//texte en italique//\"\"

====Mettre le texte en souligné====
__texte en souligné__
s\'écrit en syntaxe wiki : \"\"__texte en souligné__\"\"

=====Mise en forme : listes=====
====Faire une liste é puce====
 - point 1
 - point 2

s\'écrit en syntaxe wiki :
\"\" - point 1\"\"
\"\" - point 2\"\"

Attention : de bien mettre un espace devant le tiret pour que l\'élément soit reconnu comme liste


====Faire une liste numérotée====
 1) point 1
 2) point 2

s\'écrit en syntaxe wiki :
\"\" 1) point 1\"\"
\"\" 2) point 2\"\"

=====Les liens : le concept des \"\"ChatMots\"\"=====
====Créer une page YesWiki : ====
La caractéristique qui permet de reconnaître un lien dans un wiki : son nom avec un mot contenant au moins deux majuscules non consécutives (un \"\"ChatMot\"\", un mot avec deux bosses).

==== Lien interne====
 - On écrit le \"\"ChatMot\"\" de la page YesWiki vers laquelle on veut pointer.
  - Si la page existe, un lien est automatiquement créé
  - Si la page n\'existe pas, apparaît un lien avec crayon. En cliquant dessus on arrive vers la nouvelle page en mode \"édition\".

=====Les liens : personnaliser le texte=====
====Personnaliser le texte du lien internet====
entre double crochets : \"\"[[AccueiL aller à la page d\'accueil]]\"\", apparaîtra ainsi : [[AccueiL aller à la page d\'accueil]].

====Liens vers d\'autres sites Internet====
entre double crochets : \"\"[[http://outils-reseaux.org aller sur le site d\'Outils-Réseaux]]\"\", apparaîtra ainsi : [[http://outils-reseaux.org aller sur le site d\'Outils-Réseaux]].


=====Télécharger une image, un document=====
====On dispose d\'un lien vers l\'image ou le fichier====
entre double crochets :
 - \"\"[[http://mondomaine.ext/image.jpg texte de remplacement de l\'image]]\"\" pour les images.
 - \"\"[[http://mondomaine.ext/document.pdf texte du lien vers le téléchargement]]\"\" pour les documents.

====L\'action \"attach\"====
En cliquant sur le pictogramme représentant une image dans la barre d\'édition, on voit apparaître la ligne de code suivante :
\"\"{{attach file=\" \" desc=\" \" class=\"left\" }} \"\"

Entre les premières guillemets, on indique le nom du document (ne pas oublier son extension (.jpg, .pdf, .zip).
Entre les secondes, on donne quelques éléments de description qui deviendront le texte du lien vers le document
Les troisièmes guillemets, permettent, pour les images, de positionner l\'image à gauche (left), ou à droite (right) ou au centre (center)
\"\"{{attach file=\"nom-document.doc\" desc=\"mon document\" class=\"left\" }} \"\"

Quand on sauve la page, un lien en point d\'interrogation apparaît. En cliquant dessus, on arrive sur une page avec un système pour aller chercher le document sur sa machine (bouton \"parcourir\"), le sélectionner et le télécharger.

=====Intégrer du html=====
Si on veut faire une mise en page plus compliquée, ou intégrer un widget, il faut écrire en html. Pour cela, il faut mettre notre code html entre double guillemets.
Par exemple : \"\"<textarea style=\"width:100%;\">&quot;&quot;<span style=\"color:#0000EE;\">texte coloré</span>&quot;&quot;</textarea>\"\"
donnera :
\"\"<span style=\"color:#0000EE;\">texte coloré</span>\"\"


=====Les pages spéciales=====
 - PageHeader
 - PageFooter
 - PageMenuHaut
 - PageMenu
 - PageRapideHaut

 - PagesOrphelines
 - TableauDeBordDeCeWiki


=====Les actions disponibles=====
Voir la page spéciale : ListeDesActionsWikini

**les actions à ajouter dans la barre d\'adresse:**
rajouter dans la barre d\'adresse :
/edit : pour passer en mode Edition
/slide_show : pour transformer la texte en diaporama

===La barre du bas de page permet d\'effectuer diverses action sur la page===
 - voir l\'historique
 - partager sur les réseaux sociaux
...

=====Suivre la vie du site=====
 - Dans chaque page, en cliquant sur la date en bas de page on accède à **l\'historique** et on peut comparer les différentes versions de la page.

 - **Le TableauDeBordDeCeWiki : ** pointe vers toutes les pages utiles à l\'analyse et à l\'animation du site.

 - **La page DerniersChangements** permet de visualiser les modifications qui ont été apportées sur l\'ensemble du site, et voir les versions antérieures. Pour l\'avoir en flux RSS DerniersChangementsRSS

 - **Les lecteurs de flux RSS** :  offrent une façon simple, de produire et lire, de façon standardisée (via des fichiers XML), des fils d\'actualité sur internet. On récupère les dernières informations publiées. On peut ainsi s\'abonner à différents fils pour mener une veille technologique par exemple.
[[http://www.wikini.net/wakka.php?wiki=LecteursDeFilsRSS Différents lecteurs de flux RSS]]



=====L\'identification=====
====Première identification = création d\'un compte YesWiki====
    - aller sur la page spéciale ParametresUtilisateur,
    - choisir un nom YesWiki qui comprend 2 majuscules. //Exemple// : JamesBond
    - choisir un mot de passe et donner un mail
    - cliquer sur s\'inscrire

====Identifications suivantes====
    - aller sur ParametresUtilisateur,
    - remplir le formulaire avec son nom YesWiki et son mot de passe
    - cliquer sur \"connexion\"



=====Gérer les droits d\'accès aux pages=====
 - **Chaque page possède trois niveaux de contrôle d\'accès :**
     - lecture de la page
     - écriture/modification de la page
     - commentaire de la page

 - **Les contrôles d\'accès ne peuvent être modifiés que par le propriétaire de la page**
On est propriétaire des pages que l\'ont créent en étant identifié. Pour devenir \"propriétaire\" d\'une page, il faut cliquer sur Appropriation.

 - Le propriétaire d\'une page voit apparaître, dans la page dont il est propriétaire, l\'option \"**éditer permissions**\" : cette option lui permet de **modifier les contrôles d\'accès**.
Ces contrôles sont matérialisés par des colonnes où le propriétaire va ajouter ou supprimer des informations.
Le propriétaire peut compléter ces colonnes par les informations suivantes, séparées par des espaces :
     - le nom d\'un ou plusieurs utilisateurs : par exemple \"\"JamesBond\"\"
     - le caractère ***** désignant tous les utilisateurs
     - le caractère **+** désignant les utilisateurs enregistrés
     - le caractère **!** signifiant la négation : par exemple !\"\"JamesBond\"\" signifie que \"\"JamesBond\"\" **ne doit pas** avoir accès à cette page

 - **Droits d\'accès par défaut** : pour toute nouvelle page créée, YesWiki applique des droits d\'accès par défaut : sur ce YesWiki, les droits en lecture et écriture sont ouverts à tout internaute.

=====Supprimer une page=====

 - **2 conditions :**
    - **on doit être propriétaire** de la page et **identifié** (voir plus haut),
    - **la page doit être \"orpheline\"**, c\'est-à-dire qu\'aucune page ne pointe vers elle (pas de lien vers cette page sur le YesWiki), on peut voir toutes les pages orphelines en visitant la page : PagesOrphelines

 - **On peut alors cliquer sur l\'\'option \"Supprimer\"** en bas de page.



=====Changer le look et la disposition=====
En mode édition, si on est propriétaire de la page, ou que les droits sont ouverts, on peut changer la structure et la présentation du site, en jouant avec les listes déroulantes en bas de page : Thème, Squelette, Style.', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('DerniersChangementsRSS',  now(), '{{recentchangesrss}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ExempleAgenda',  now(), '{{nav links=\"VueActivite, VueAgenda, SaisirAgenda\" titles=\"Voir les prochaines activités, Voir l\'agenda, Proposer une activité\"}}

{{bazar voirmenu=\"0\" vue=\"saisir\" id=\"2\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ExempleAnnuaire',  now(), '======Type annuaire======
Voici quelques possibilités autour des annuaires (à copier-coller - adapter)
{{nav links=\"SaisirAnnuaire, AnnuaireAlpha, CartoAnnuaire, TrombiAnnuaire\" titles=\"S\'inscrire dans l\'annuaire, Annuaire alphabétique, Annuaire cartographique, Trombinoscope\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ExempleRessource',  now(), '{{nav links=\"FacetteRessource, SaisirRessource\" titles=\"Les ressources, Déposer une ressource\"}}

{{bazar voirmenu=\"0\" vue=\"saisir\" id=\"4\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ExemPles',  now(), ' - Exemples
  - [[TrombiAnnuaire Type annuaire]]
  - [[VueActivite Type agenda]]
  - [[FacetteRessource Type ressourcerie]]
  - [[VoirBlog Type blog]]
  - [[LookWiki Mise en page avancée]]', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('FacetteRessource',  now(), '{{nav links=\"FacetteRessource, SaisirRessource\" titles=\"Les ressources, Déposer une ressource\"}}

{{bazarliste id=\"4\" template=\"liste_accordeon\" correspondance=\"soustitre=bf_description\" groups=\"checkboxListeType\" titles=\"Tri par type\" voirmenu=\"0\" vue=\"recherche\" }}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererConfig',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMotsClef, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurs, Mots clefs, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

{{editconfig}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererDroits',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMotsClef, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurs, Mots clefs, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

{{button class=\"btn-primary btn-xs pull-right\" hideifnoaccess=\"true\" icon=\"fas fa-arrow-right\" link=\"GererDroitsActions\" text=\"Droits des actions/handlers\" }}===Gérer les droits des pages===
{{gererdroits}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererDroitsActions',  now(), '//Droits d\'accès//
{{nav class=\"nav nav-tabs\" hideifnoaccess=\"true\" links=\"GererDroitsActions, GererDroitsHandlers, GererDroits\" titles=\"Actions, Handlers, Pages\" }}

===Droits d\'accès aux actions===

{{editactionsacls}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererDroitsHandlers',  now(), '//Droits d\'accès//
{{nav class=\"nav nav-tabs\" hideifnoaccess=\"true\" links=\"GererDroitsActions, GererDroitsHandlers, GererDroits\" titles=\"Actions, Handlers, Pages\" }}

===Droits d\'accès aux handlers===

{{edithandlersacls}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererMisesAJour',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMotsClef, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurs, Mots clefs, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

===Mises à jour / extensions===
{{update}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererMotsClef',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMotsClef, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurs, Mots clefs, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

===Gestion des mots clés ===
{{admintag}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererSite',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMotsClef, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurs, Mots clefs, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

{{attach file=\"modele.jpg\" desc=\"image Dessin_sans_titre.jpg (66.9kB)\" size=\"big\" class=\"right\"}}===Gérer les menus et pages spéciales de ce wiki===
 - [[PageTitre/edit Éditer la Page Titre]]
 - [[PageHeader/edit Éditer la Page Header]]
 - [[PageMenuHaut/edit Éditer la Page Menu Haut]]
 - [[PageFooter/edit Éditer la Page Footer]]
==Et éventuellement==
 - [[PageRapideHaut/edit Éditer la Page Rapide Haut]]
==Mais aussi==
 - [[PageMenu/edit Éditer le menu vertical]]
 - [[ReglesDeFormatage/edit Éditer le mémo de formatage]]', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererThemes',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMotsClef, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurs, Mots clefs, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

{{button class=\"btn-info btn-block\" link=\"LookWiki\" text=\"Personnaliser le thème de ce wiki (couleurs, police...)\" }}
{{button class=\"btn-default btn-block\" link=\"PageCss\" text=\"Ajouter du code CSS (zone sensible)\" title=\"Cette page ne peut contenir QUE du Css / voir la doc sur https://yeswiki.net/?DocumentationThemeMargot\" }}

------

===Gérer les thèmes des pages===
{{gererthemes}}
-----
===Gérer le thème par défaut du wiki===
{{setwikidefaulttheme}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererUtilisateurs',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMotsClef, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurs, Mots clefs, Fichier de conf, MAJ / extensions, Sauvegardes\"}}

===Gérer les groupes d\'utilisateurs===
{{editgroups}}

===Gérer les utilisateurs===
{{userstable}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('LookWiki',  now(), '======Tester les thèmes \"\"YesWiki\"\"======
{{grid}}
{{col size=\"4\"}}
=====Titre 1=====
	Bla blabla blablabla bla [[{{rootPage}} Retour a la page d\'accueil]].

	Etiam a {{label class=\"label-primary\"}}pri_ffr{{end elem=\"label\"}} sagittis justo. Aliquam vel egestas eros. Quisque eget dolor ornare, accumsan sem et, rhoncus diam. Morbi sodales neque vitae lorem ultrices, sit amet sollicitudin lectus tempor.** Donec quis mauris quis sem blandit faucibus** ut elementum lacus. //Orci varius natoque// penatibus et __magnis dis parturient__ montes, nascetur ridiculus mus. Interdum et malesuada @@fames ac ante ipsum primis @@in faucibus. Suspendisse vitae egestas nisi. **//__Pellentesque faucibus a elit vitae luctus__//**. Mauris condimentum vitae diam ut egestas. Etiam sed dui et lorem luctus pulvinar vel nec diam. 
{{end elem=\"col\"}}
{{col size=\"4\"}}
	=====Titre 2=====
	Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec congue magna at dapibus facilisis. Suspendisse nisi ante, vehicula vel dolor non, laoreet eleifend ante. Aenean augue elit, cursus nec urna et, tincidunt commodo augue. Maecenas sed ex rhoncus, vehicula mauris sit amet, laoreet libero. Aliquam egestas ac risus sit amet cursus. Pellentesque vestibulum elit in dolor aliquam, quis molestie risus fermentum. Etiam non sem accumsan, faucibus est in, hendrerit nisi. Vivamus auctor in dui et egestas. Duis non ante sit amet risus euismod pulvinar. Suspendisse potenti. Duis sit amet malesuada lectus. 
{{end elem=\"col\"}}
{{col size=\"4\"}}
{{section class=\"well\" nocontainer=\"1\"}}
	====Choisir le thème, styles et squelettes associés====
	{{themeselector}}
{{end elem=\"section\"}}
{{end elem=\"col\"}}
{{end elem=\"grid\"}}

{{section class=\"full-width white\" bgcolor=\"var(--secondary-color-1)\" height=\"25\"}}
	======Insertion de pad ou de vidéo======
	Il est possible d\'incruster dans son wiki des pads, des vidéos, des lignes du temps...
{{end elem=\"section\"}}

{{grid}}
{{col size=\"6\"}}
=====Incrustation d\'un pad=====
\"\"<iframe name=\"embed_readwrite\" src=\"https://pad.coop.tools/p/pad-wikidebase?showControls=true&showChat=true&showLineNumbers=true&useMonospaceFont=false\" width=100% height=400></iframe>\"\"
{{end elem=\"col\"}}
{{col size=\"6\"}}
=====Incrustation d\'une vidéo=====
\"\"<iframe width=\"100%\" height=\"315\" sandbox=\"allow-same-origin allow-scripts\" src=\"https://video.coop.tools/videos/embed/e5add04e-d195-41af-8a60-095f8b215fa1\" frameborder=\"0\" allowfullscreen></iframe>\"\"
Ceci est aussi possible à partir des plateformes youtbe/vimeo/dailymotion...
{{end elem=\"col\"}}

{{end elem=\"grid\"}}


{{section class=\"full-width white\" bgcolor=\"var(--secondary-color-2)\" height=\"250\"}}
	======Composants graphiques======
	Des petits composants pour rendre vos pages plus conviviales 
	Ceci en est déjà un en vous permettant de créer une bande de couleur sur la largeur de la page ;-)
{{end elem=\"section\"}}

====Etiquettes====
{{label}}Label-default{{end elem=\"label\"}}
{{label class=\"label-primary\"}}label-primary{{end elem=\"label\"}}   
{{label class=\"label-secondary-1\"}}label-secondary-1{{end elem=\"label\"}}   
{{label class=\"label-secondary-2\"}}label-secondary-2{{end elem=\"label\"}}
{{label class=\"label-success\"}}label-success{{end elem=\"label\"}}  
{{label class=\"label-info\"}}label-info{{end elem=\"label\"}}  
{{label class=\"label-warning\"}}label-warning{{end elem=\"label\"}}  
{{label class=\"label-danger\"}}label-danger{{end elem=\"label\"}}

====Encadrés====
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

====Accordéons====
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
{{end elem=\"accordion\"}}

====Boutons====
{{button link=\"{{rootPage}}\" class=\"btn btn-default\" text=\"btn-default\"}}
{{button link=\"{{rootPage}}\" class=\"btn btn-primary\" text=\"btn-primary\"}}
{{button link=\"{{rootPage}}\" class=\"btn btn-secondary-1\" text=\"btn-secondary-1\"}}
{{button link=\"{{rootPage}}\" class=\"btn btn-secondary-2\" text=\"btn-secondary-2\"}}
{{button link=\"{{rootPage}}\" class=\"btn btn-success\" text=\"btn-success\"}}
{{button link=\"{{rootPage}}\" class=\"btn btn-info\" text=\"btn-info\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('MesContenus',  now(), '=====Mes contenus=====
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
Pour en savoir plus, voyez la documentation sur https://yeswiki.net/?DocumentationThemeMargot
*/

/* :root { */

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
('PageHeader',  now(), '{{section bgcolor=\"var(--neutral-color)\" class=\"white text-center cover\" file=\"bandeau.png\" }}

======Description de mon wiki======
Rendez-vous dans la roue crantée / gestion du site pour modifier ce bandeau

{{end elem=\"section\"}}
{#INFO CACHÉE pour vous aider : 
Pour changer l\'image du bandeau : renommer bandeau.png par le nom de votre nouvelle image (png, jpg). Sauver, puis charger votre image préalablement préparée
(cette image devra avoir comme taille 1920 X 300 et 90 dpi de résolution)

Aplat de couleur : supprimer file=\"bandeau.png\", cliquez sur section, cliquez sur le petit crayon dans la marge et laissez vous guider. vous pourrez 
 - changer la tonalité du texte
 - le caler à droite, le centrer
 - faire varier la hauteur du bandeau...#}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageLogin',  now(), '{{login}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageMenu',  now(), ' - [[{{rootPage}} Accueil]]
 - [[LookWiki Test du look]]

---- 

Double cliquer sur ce texte pour éditer cette colonne.











\"\"\"\"', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageMenuExemple',  now(), '  - [[TrombiAnnuaire Type annuaire]]
  - [[VueActivite Type agenda]]
  - [[FacetteRessource Type ressourcerie]]
  - [[VoirBlog Type blog]]', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageMenuHaut',  now(), ' - [[BacASable Bac à sable]]
 - Menu exemple
  - [[Pagetest1 Sous menu 1]]
  - [[PageTest2 Sous menu 2]]

{#INFO CACHÉE
Vous êtes dans la page qui se nomme PageMenuHaut qui sert à modifier le menu du haut. Pour faire évoluer le menu, inspirez vous du menu exemple.
#}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('{{rootPage}}',  now(), '======Félicitations, votre wiki est installé !======
{{include page=\"AccueilYeswiki\"}}

{#Et hop, effacez tout et belle aventure#}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageRapideHaut',  now(), '{{moteurrecherche template=\"moteurrecherche_button.tpl.html\"}}
{{buttondropdown icon=\"cog\" caret=\"0\"}}
 - {{login template=\"modal.tpl.html\" nobtn=\"1\"}}
 - ------
 - {{button nobtn=\"1\" icon=\"fa fa-question\" text=\"Documentation\" link=\"doc\"}}
 - {{button nobtn=\"1\" icon=\"fas fa-yin-yang\" text=\"Présentation YesWiki\" link=\"AccueilYeswiki\"}}
 - ------
 - {{button nobtn=\"1\" icon=\"fa fa-wrench\" text=\"Gestion du site\" link=\"GererSite\"}}
 - {{button nobtn=\"1\" icon=\"fa fa-tachometer-alt\" text=\"Tableau de bord\" link=\"TableauDeBord\"}}
 - {{button class=\"btn-primary\" icon=\"fas fa-user\" link=\"MesContenus\" nobtn=\"1\" text=\"Mes contenus\" }}
 - {{button nobtn=\"1\" icon=\"fa fa-briefcase\" text=\"Base de données\" link=\"BazaR\"}}
{{end elem=\"buttondropdown\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('Pagetest1',  now(), '======Sous menu 1======', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageTest2',  now(), '======Sous menu 2======', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageTitre',  now(), '{{configuration param=\"wakka_name\" }}

{#Astuce, vous pouvez remplacer le code précédent par ce que vous souhaitez afficher comme titre en haut à gauche #}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ParametresUtilisateur',  now(), '{{UserSettings}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('RechercheTexte',  now(), '{{newtextsearch}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ReglesDeFormatage',  now(), 'Cette page est modifiable en allant sur ReglesDeFormatage
{{grid}}
{{col size=\"6\"}}
===Obtenir des listes===
\"\"<pre> - Liste à puce niveau 1
 - Puce niveau 1
  - Puce niveau 2
  - Puce niveau 2
 - Puce niveau 1

 1) Liste énumérée
 1) Liste énumérée
 1) Liste énumérée</pre>\"\"
===Insérer un iframe===\"\"<a href=\"https://yeswiki.net/?DocumentationIntegrerDuHtml\" target=\"_blank\" class=\"btn btn-primary btn-xs\">Doc en ligne</a>\"\"
//Inclure un autre site, ou un pad, ou une vidéo youtube, etc...//
%%\"\"<iframe width=100% height=\"1250\" src=\"http://exemple.com\" frameborder=\"0\" allowfullscreen></iframe>\"\"%%
===Obtenir un tableau===
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
===Ecrire en html===
\"\"<pre>si vous déposez du html dans la page wiki, 
il faut l\'entourer de &quot;&quot; <bout de html> &quot;&quot; 
pour qu\'il soit interprété</pre>\"\"
===Eviter qu\'un mot avec deux majuscules ne soit reconnu comme lien vers une page wiki===
\"\"<pre>Il faut l\'entourer de &quot;&quot; &quot;&quot; (ex : &quot;&quot; MonMotAvecDeuxMajuscules &quot;&quot;) pour qu\'il ne soit pas interprété comme lien wiki</pre>\"\"
===Créer une ancre, un lien qui envoie sur une partie de votre page===
Le code suivant permet de créer le lien qui ira vers votre paragraphe
%%<a href=\"#ancre1\">Aller vers le paragraphe cible</a>%% 
Et cette partie de code sera à placer juste au dessus du paragraphe cible
%%<div id=\"ancre1\"></div>%% 
{{end elem=\"col\"}}
{{col size=\"6\"}}
===Mettre du texte en couleur===
%%\"\"<span style=\"color:#votrecodecouleur;\">votre texte à colorer</span>\"\"%%//Quelques codes couleur => mauve : #990066 / vert : #99cc33 / rouge : #cc3333 / orange : #ff9900 / bleu : #006699//
//Voir les codes hexa des couleurs : [[http://fr.wikipedia.org/wiki/Liste_de_couleurs http://fr.wikipedia.org/wiki/Liste_de_couleurs]]//
===Aligner du texte===
Placez votre texte dans une section (voir composant) et choisissez votre alignement (gauche, centré, droite, justifié)
===Utiliser des icônes Emoji===
Il est possible de copier des icônes dans des sites sources puis de les coller dans votre wiki. \"\"<a href=\"http://getemoji.com\" target=\"_blank\" class=\"btn btn-primary btn-xs\">Par exemple sur ce site</a>\"\"

===Mettre en page par colonne===\"\"<a href=\"https://yeswiki.net/?DemoGrid\" target=\"_blank\" class=\"btn btn-primary btn-xs\">Doc en ligne</a>\"\"
//le total des colonnes (size=) doit faire 12 (ou moins)//
%%{{grid}}
{{col size=\"4\"}}
===Titre de la colonne 1===
Texte colonne 1
{{end elem=\"col\"}}
{{col size=\"4\"}}
===Titre de la colonne 2===
Texte colonne 2
{{end elem=\"col\"}}
{{col size=\"4\"}}
===Titre de la colonne 3===
Texte colonne 3
{{end elem=\"col\"}}
{{end elem=\"grid\"}}%%
===Inclure une page d\'un autre yeswiki=== 
( Noter le pipe \"\"|\"\" après les premiers \"\"[[\"\" ) %%[[|http://lesite.org/nomduwiki PageAInclure]]%%
===Afficher une barre de progression===
&#x5B;10%] donne [10%]
&#x5B;40%] donne [40%]
&#x5B;80%] donne [80%]

===Vous trouverez beaucoup d\'autres astuces dans===
**la documentation**... Dans Roue crantée / Documentation
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
('TableauDeBord',  now(), '======Tableau de bord======
{{mailperiod}}
{{accordion }}

{{panel title=\"Dernières modifications sur le wiki\" type=\"collapsible\" }}
{{grid}}
{{col size=\"6\"}}
====12 Derniers comptes utilisateurs ====
{{Listusers/last=\"12\"}}
{{end elem=\"col\"}}
{{col size=\"6\"}}
====12 Dernières pages modifiées ====
{{recentchanges max=\"12\"}}
{{end elem=\"col\"}}
{{end elem=\"grid\"}}
{{end elem=\"panel\"}}
{{panel title=\"Index des pages et fiches du wiki\" type=\"collapsed\" }}
{{grid}}
{{col size=\"4\"}}
==== Pages orphelines ====
{{OrphanedPages}}
{{end elem=\"col\"}}
{{col size=\"4\"}}
==== Index des fiches bazar ====
{{bazarrecordsindex}}
{{end elem=\"col\"}}
{{col size=\"4\"}}
==== Index des pages seules ====
{{pageonlyindex}}
{{end elem=\"col\"}}
{{end elem=\"grid\"}}
{{end elem=\"panel\"}}
{{end elem=\"accordion\" }}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('TrombiAnnuaire',  now(), '{{nav links=\"TrombiAnnuaire, AnnuaireAlpha, CartoAnnuaire, SaisirAnnuaire\" titles=\"Trombinoscope, Annuaire alphabétique, Annuaire cartographique, S\'inscrire dans l\'annuaire\"}}

{{bazarliste id=\"1\" template=\"trombinoscope\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
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
('YesWiki',  now(), 'YesWiki', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererSauvegardes',  now(), '{{nav class=\"nav nav-tabs\" links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMotsClef, GererConfig, GererMisesAJour, GererSauvegardes\" titles=\"Gestion du site, Droits, Look, Utilisateurs, Mots clefs, Fichier de conf, MAJ / extensions, Sauvegardes\" }}

{{adminbackups}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', '');
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
('YoupiIciCEstLeTitre',  now(), '{\"bf_titre\":\"Youpi ici c\'est le titre\",\"bf_description\":\"Un \\u00e9v\\u00e9nement autour du vin, c\'est pour cela qu\'il est \\u00e0 Bordeaux...\",\"bf_date_debut_evenement\":\"2020-01-08\",\"bf_date_fin_evenement\":\"2020-01-10\",\"bf_site_internet\":\"\",\"bf_adresse\":\"\",\"bf_code_postal\":\"\",\"bf_ville\":\"Bordeaux\",\"bf_latitude\":\"44.841225\",\"bf_longitude\":\"-0.5800364\",\"id_typeannonce\":\"2\",\"id_fiche\":\"YoupiIciCEstLeTitre\",\"date_creation_fiche\":\"2020-01-24 09:42:52\",\"statut_fiche\":\"1\",\"imagebf_image\":null,\"fichierfichier\":\"\",\"geolocation\":{\"bf_latitude\":\"44.841225\",\"bf_longitude\":\"-0.5800364\"},\"date_maj_fiche\":\"2021-06-21 19:33:56\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('YeswikiLeSiteOfficiel',  now(), '{\"bf_titre\":\"Yeswiki : le site officiel\",\"bf_url\":\"https:\\/\\/yeswiki.net\",\"bf_description\":\"Tout ce qu\'il y a \\u00e0 savoir sur Yeswiki \",\"bf_auteur\":\"\",\"id_typeannonce\":\"4\",\"id_fiche\":\"YeswikiLeSiteOfficiel\",\"fichierfichier\":\"\",\"date_creation_fiche\":\"2020-02-12 11:10:01\",\"statut_fiche\":\"1\",\"checkboxListeType\":\"1\",\"imagebf_image\":null,\"date_maj_fiche\":\"2021-09-07 12:10:10\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('FramasofT',  now(), '{\"bf_titre\":\"Framasoft\",\"bf_url\":\"https:\\/\\/framasoft.org\\/fr\\/\",\"bf_description\":\"Framasoft, c\\u2019est une association d\\u2019\\u00e9ducation populaire, un groupe d\\u2019ami\\u00b7es convaincu\\u00b7es qu\\u2019un monde num\\u00e9rique \\u00e9mancipateur est possible, persuad\\u00e9\\u00b7es qu\\u2019il adviendra gr\\u00e2ce \\u00e0 des actions concr\\u00e8tes sur le terrain et en ligne avec vous et pour vous !\",\"bf_auteur\":\"\",\"id_typeannonce\":\"4\",\"id_fiche\":\"FramasofT\",\"fichierfichier\":\"\",\"date_creation_fiche\":\"2020-02-12 14:12:58\",\"statut_fiche\":\"1\",\"checkboxListeType\":\"3\",\"imagebf_image\":null,\"date_maj_fiche\":\"2021-09-07 12:07:38\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('YeswikidaY',  now(), '{\"bf_titre\":\"Yeswikiday\",\"bf_description\":\"Une journ\\u00e9e pour faire avancer le projet Yeswiki dans la bonne humeur\",\"bf_date_debut_evenement\":\"2020-04-30T09:00:00+02:00\",\"bf_date_fin_evenement\":\"2020-04-30T16:00:00+02:00\",\"bf_site_internet\":\"https:\\/\\/yeswiki.net\\/?DocumentatioN\",\"bf_adresse\":\"\",\"bf_code_postal\":\"7700\",\"bf_ville\":\"Mouscron\",\"bf_latitude\":\"50.7433351\",\"bf_longitude\":\"3.2139093\",\"id_typeannonce\":\"2\",\"id_fiche\":\"YeswikidaY\",\"imagebf_image\":\"YeswikidaY_yeswiki-logo.png\",\"fichierfichier\":\"\",\"geolocation\":{\"bf_latitude\":\"50.7433351\",\"bf_longitude\":\"3.2139093\"},\"date_creation_fiche\":\"2020-02-12 11:21:49\",\"statut_fiche\":\"1\",\"date_maj_fiche\":\"2021-08-06 10:34:29\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('UnBeauLogoPourYeswiki',  now(), '{\"bf_titre\":\"Un beau logo pour Yeswiki\",\"bf_chapeau\":\"Il fallait le rafraichir, nous l\'avons fait ! \",\"bf_description\":\"Apr\\u00e8s multiples discussions, tests et essais, un logo plus actuel a \\u00e9t\\u00e9 cr\\u00e9\\u00e9 pour Yeswiki\\r\\nNous esp\\u00e9rons que vous l\'aimerez ;-) \",\"id_typeannonce\":\"3\",\"id_fiche\":\"UnBeauLogoPourYeswiki\",\"date_creation_fiche\":\"2020-02-12 13:16:06\",\"statut_fiche\":\"1\",\"imagebf_image\":\"UnBeauLogoPourYeswiki_yeswiki-logo.png\",\"date_maj_fiche\":\"2021-09-05 13:23:52\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('UnNouveauThemePourYeswiki',  now(), '{\"bf_titre\":\"Un nouveau th\\u00e8me pour Yeswiki\",\"bf_chapeau\":\"Margot, voil\\u00e0 le nom du nouveau th\\u00e8me qui sera distribu\\u00e9 avec la prochaine version de Yeswiki\\t\",\"bf_description\":\"Plus moderne, mieux pens\\u00e9, plus graphiqu.\\r\\nMargot permettra d\'unifier les rendus graphiques des wikis.\",\"id_typeannonce\":\"3\",\"id_fiche\":\"UnNouveauThemePourYeswiki\",\"date_creation_fiche\":\"2020-02-12 12:17:49\",\"statut_fiche\":\"1\",\"imagebf_image\":\"UnNouveauThemePourYeswiki_capture-de\\u0301cran-2020-02-12-a\\u0300-13.16.33.png\",\"date_maj_fiche\":\"2020-02-12 12:17:50\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ElizabethJFeinler',  now(), '{\"bf_titre\":\"JFeinler Elizabeth\",\"bf_nom\":\"JFeinler\",\"bf_prenom\":\"Elizabeth\",\"bf_fonction\":\"informaticienne, pionni\\u00e8re de l\'internet\",\"bf_projet\":\"En 1974, j\'ai cr\\u00e9\\u00e9 le nouveau Network Information Center (NIC) de l\'ARPANET.  \",\"bf_mail\":\"info@cooptic.be\",\"bf_structure\":\"Stanford Research Institute et NASA \",\"bf_site_internet\":\"https:\\/\\/fr.wikipedia.org\\/wiki\\/Elizabeth_J._Feinler\",\"bf_adresse\":\"\",\"bf_code_postal\":\"\",\"bf_ville\":\"Paris\",\"bf_latitude\":\"48.8566969\",\"bf_longitude\":\"2.3514616\",\"id_typeannonce\":\"1\",\"id_fiche\":\"ElizabethJFeinler\",\"imagebf_image\":\"ElizabethJFeinler_elizabethfeinler-2011.jpg\",\"geolocation\":{\"bf_latitude\":\"48.8566969\",\"bf_longitude\":\"2.3514616\"},\"date_creation_fiche\":\"2021-05-24 22:07:17\",\"statut_fiche\":\"1\",\"date_maj_fiche\":\"2021-08-06 10:31:00\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('TesT2',  now(), '{\"bf_titre\":\"Sortie Culturelle\",\"bf_description\":\"La culture, moins on en a, plus on l\'\\u00e9tale!\",\"bf_date_debut_evenement\":\"2023-05-30T18:00:00+02:00\",\"bf_date_fin_evenement\":\"2021-05-02T20:00:00+02:00\",\"bf_site_internet\":\"https:\\/\\/www.yeswiki.net\",\"bf_adresse\":\"Avenue des Champs Elys\\u00e9es\",\"bf_code_postal\":\"75000\",\"bf_ville\":\"Paris\",\"bf_latitude\":\"48.865669\",\"bf_longitude\":\"2.3203067\",\"id_typeannonce\":\"2\",\"id_fiche\":\"TesT2\",\"imagebf_image\":\"TesT2_presence-photo.png\",\"fichierfichier\":\"\",\"date_creation_fiche\":\"2021-05-24 22:54:03\",\"statut_fiche\":\"1\",\"geolocation\":{\"bf_latitude\":\"48.865669\",\"bf_longitude\":\"2.3203067\"},\"date_maj_fiche\":\"2021-06-21 19:29:14\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('LovelaceAda',  now(), '{\"bf_titre\":\"Lovelace Ada\",\"bf_nom\":\"Lovelace\",\"bf_prenom\":\"Ada\",\"bf_fonction\":\"Pionni\\u00e8re de la science informatique \",\"bf_projet\":\"<p>J\'ai r\\u00e9alis\\u00e9 le premier v\\u00e9ritable programme informatique, lors de mon travail sur un anc\\u00eatre de l\'ordinateur : la machine analytique de Charles Babbage. <br><\\/p>\",\"bf_mail\":\"info@cooptic.be\",\"bf_structure\":\"Universit\\u00e9 de Cambridge \",\"bf_site_internet\":\"https:\\/\\/fr.wikipedia.org\\/wiki\\/Ada_Lovelace\",\"bf_adresse\":\"\",\"bf_code_postal\":\"\",\"bf_ville\":\"Londres \",\"bf_latitude\":\"51.5073219\",\"bf_longitude\":\"-0.1276474\",\"id_typeannonce\":\"1\",\"id_fiche\":\"LovelaceAda\",\"geolocation\":{\"bf_latitude\":\"51.5073219\",\"bf_longitude\":\"-0.1276474\"},\"date_creation_fiche\":\"2021-05-25 11:00:19\",\"statut_fiche\":\"1\",\"imagebf_image\":\"LovelaceAda_lovelace.png\",\"date_maj_fiche\":\"2021-05-25 11:01:13\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', '');
INSERT INTO `{{prefix}}triples` (`resource`, `property`, `value`) VALUES
('YoupiIciCEstLeTitre', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('YeswikiLeSiteOfficiel', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('FramasofT', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('YeswikidaY', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('UnBeauLogoPourYeswiki', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('UnNouveauThemePourYeswiki', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('ElizabethJFeinler', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('TesT2', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('LovelaceAda', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar');
# end Bazar entries

