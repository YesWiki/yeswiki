# YesWiki pages
INSERT INTO `{{prefix}}__pages` (`tag`, `time`, `body`, `body_r`, `owner`, `user`, `latest`, `handler`, `comment_on`) VALUES
('AidE',  now(), '=====Les pages d\'aide=====
Vous disposez de deux niveaux d\'aide.
 **ReglesDeFormatage <=** Résumé des syntaxes couramment utilisées. Le contenu de cette page est affiché en cliquant sur le bouton \"?\" visible lorsque vous éditez une page.
Et \"\"<iframe id=\"yeswiki-doc\" width=\"100%\" height=\"1000\" frameborder=\"0\" class=\"auto-resize\" src=\"https://yeswiki.net/?DocumentatioN/iframe\"></iframe>\"\"', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('BacASable',  now(), 'Si vous cliquez sur \"éditer cette page\" ou double-cliquez simplement sur la page,
 - vous pourrez écrire dans cette page comme bon vous semble,
 - puis en cliquant sur \"sauver\" vous pourrez enregistrer vos modifications.
Une aide simple est aisément accessible en cliquant sur le bouton \"?\".', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('BazaR',  now(), '{{bazar showexportbuttons=\"1\"}}
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('CoursUtilisationYesWiki',  now(), '======Cours sur l\'utilisation de YesWiki======
====Le principe \"Wiki\"====
Wiki Wiki signifie rapide, en Hawaéen.
==N\'importe qui peut modifier la page==

**Les Wiki sont des dispositifs permettant la modification de pages Web de faéon simple, rapide et interactive.**
YesWiki fait partie de la famille des wiki. Il a la particularité d\'étre trés facile é installer.

=====Mettre du contenu=====
====écrire ou coller du texte====
 - Dans chaque page du site, un double clic sur la page ou un clic sur le lien \"éditer cette page\" en bas de page permet de passer en mode \"édition\".
 - On peut alors écrire ou coller du texte
 - On peut voir un aperéu des modifications ou sauver directement la page modifiée en cliquant sur les boutons en bas de page.

====écrire un commentaire (optionnel)====
Si la configuration de la page permet d\'ajouter des commentaires, on peut cliquer sur : Afficher commentaires/formulaire en bas de chaque page.
Un formulaire apparaitra et vous permettra de rajouter votre commentaire.


=====Mise en forme : Titres et traits=====
--> Voir la page ReglesDeFormatage

====Faire un titre====
======Trés gros titre======
s\'écrit en syntaxe wiki : \"\"======Trés gros titre======\"\"


==Petit titre==
s\'écrit en syntaxe wiki : \"\"==Petit titre==\"\"


//On peut mettre entre 2 et 6 = de chaque coté du titre pour qu\'il soit plus petit ou plus grand//

====Faire un trait de séparation====
Pour faire apparaitre un trait de séparation
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
La caractéristique qui permet de reconnaitre un lien dans un wiki : son nom avec un mot contenant au moins deux majuscules non consécutives (un \"\"ChatMot\"\", un mot avec deux bosses).

==== Lien interne====
 - On écrit le \"\"ChatMot\"\" de la page YesWiki vers laquelle on veut pointer.
  - Si la page existe, un lien est automatiquement créé
  - Si la page n\'existe pas, apparait un lien avec crayon. En cliquant dessus on arrive vers la nouvelle page en mode \"édition\".

=====Les liens : personnaliser le texte=====
====Personnaliser le texte du lien internet====
entre double crochets : \"\"[[AccueiL aller é la page d\'accueil]]\"\", apparaitra ainsi : [[AccueiL aller é la page d\'accueil]].

====Liens vers d\'autres sites Internet====
entre double crochets : \"\"[[http://outils-reseaux.org aller sur le site d\'Outils-Réseaux]]\"\", apparaitra ainsi : [[http://outils-reseaux.org aller sur le site d\'Outils-Réseaux]].


=====Télécharger une image, un document=====
====On dispose d\'un lien vers l\'image ou le fichier====
entre double crochets :
 - \"\"[[http://mondomaine.ext/image.jpg texte de remplacement de l\'image]]\"\" pour les images.
 - \"\"[[http://mondomaine.ext/document.pdf texte du lien vers le téléchargement]]\"\" pour les documents.

====L\'action \"attach\"====
En cliquant sur le pictogramme représentant une image dans la barre d\'édition, on voit apparaétre la ligne de code suivante :
\"\"{{attach file=\" \" desc=\" \" class=\"left\" }} \"\"

Entre les premiéres guillemets, on indique le nom du document (ne pas oublier son extension (.jpg, .pdf, .zip).
Entre les secondes, on donne quelques éléments de description qui deviendront le texte du lien vers le document
Les troisiémes guillemets, permettent, pour les images, de positionner l\'image é gauche (left), ou é droite (right) ou au centre (center)
\"\"{{attach file=\"nom-document.doc\" desc=\"mon document\" class=\"left\" }} \"\"

Quand on sauve la page, un lien en point d\'interrogation apparait. En cliquant dessus, on arrive sur une page avec un systéme pour aller chercher le document sur sa machine (bouton \"parcourir\"), le sélectionner et le télécharger.

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

**les actions é ajouter dans la barre d\'adresse:**
rajouter dans la barre d\'adresse :
/edit : pour passer en mode Edition
/slide_show : pour transformer la texte en diaporama

===La barre du bas de page permet d\'effectuer diverses action sur la page===
 - voir l\'historique
 - partager sur les réseaux sociaux
...

=====Suivre la vie du site=====
 - Dans chaque page, en cliquant sur la date en bas de page on accéde é **l\'historique** et on peut comparer les différentes versions de la page.

 - **Le TableauDeBordDeCeWiki : ** pointe vers toutes les pages utiles é l\'analyse et é l\'animation du site.

 - **La page DerniersChangements** permet de visualiser les modifications qui ont été apportées sur l\'ensemble du site, et voir les versions antérieures. Pour l\'avoir en flux RSS DerniersChangementsRSS

 - **Les lecteurs de flux RSS** :  offrent une faéon simple, de produire et lire, de faéon standardisée (via des fichiers XML), des fils d\'actualité sur internet. On récupére les derniéres informations publiées. On peut ainsi s\'abonner é différents fils pour mener une veille technologique par exemple.
[[http://www.wikini.net/wakka.php?wiki=LecteursDeFilsRSS Différents lecteurs de flux RSS]]



=====L\'identification=====
====Premiére identification = création d\'un compte YesWiki====
    - aller sur la page spéciale ParametresUtilisateur,
    - choisir un nom YesWiki qui comprend 2 majuscules. //Exemple// : JamesBond
    - choisir un mot de passe et donner un mail
    - cliquer sur s\'inscrire

====Identifications suivantes====
    - aller sur ParametresUtilisateur,
    - remplir le formulaire avec son nom YesWiki et son mot de passe
    - cliquer sur \"connexion\"



=====Gérer les droits d\'accés aux pages=====
 - **Chaque page posséde trois niveaux de contréle d\'accés :**
     - lecture de la page
     - écriture/modification de la page
     - commentaire de la page

 - **Les contréles d\'accés ne peuvent étre modifiés que par le propriétaire de la page**
On est propriétaire des pages que l\'ont créent en étant identifié. Pour devenir \"propriétaire\" d\'une page, il faut cliquer sur Appropriation.

 - Le propriétaire d\'une page voit apparaétre, dans la page dont il est propriétaire, l\'option \"**éditer permissions**\" : cette option lui permet de **modifier les contréles d\'accés**.
Ces contréles sont matérialisés par des colonnes oé le propriétaire va ajouter ou supprimer des informations.
Le propriétaire peut compléter ces colonnes par les informations suivantes, séparées par des espaces :
     - le nom d\'un ou plusieurs utilisateurs : par exemple \"\"JamesBond\"\"
     - le caractére ***** désignant tous les utilisateurs
     - le caractére **+** désignant les utilisateurs enregistrés
     - le caractére **!** signifiant la négation : par exemple !\"\"JamesBond\"\" signifie que \"\"JamesBond\"\" **ne doit pas** avoir accés é cette page

 - **Droits d\'accés par défaut** : pour toute nouvelle page créée, YesWiki applique des droits d\'accés par défaut : sur ce YesWiki, les droits en lecture et écriture sont ouverts é tout internaute.

=====Supprimer une page=====

 - **2 conditions :**
    - **on doit étre propriétaire** de la page et **identifié** (voir plus haut),
    - **la page doit étre \"orpheline\"**, c\'est-é-dire qu\'aucune page ne pointe vers elle (pas de lien vers cette page sur le YesWiki), on peut voir toutes les pages orphelines en visitant la page : PagesOrphelines

 - **On peut alors cliquer sur l\'\'option \"Supprimer\"** en bas de page.



=====Changer le look et la disposition=====
En mode édition, si on est propriétaire de la page, ou que les droits sont ouverts, on peut changer la structure et la présentation du site, en jouant avec les listes déroulantes en bas de page : Théme, Squelette, Style.
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('DerniersChangementsRSS',  now(), '{{recentchangesrss}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererDroits',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMisesAJour\" titles=\"Gestion du site, Droits d\'accès aux pages, Thèmes graphiques, Utilisateurs et groupes, Mises à jour / extensions\"}}

===Gérer les droits des pages===
{{gererdroits}}
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererMisesAJour',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMisesAJour\" titles=\"Gestion du site, Droits d\'accès aux pages, Thèmes graphiques, Utilisateurs et groupes, Mises à jour / extensions\"}}

===Mises à jour / extensions===
{{update}}
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererSite',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMisesAJour\" titles=\"Gestion du site, Droits d\'accès aux pages, Thèmes graphiques, Utilisateurs et groupes, Mises à jour / extensions\"}}

===Gérer les menus et pages spéciales de ce wiki===
 - [[PageMenuHaut Éditer menu horizontal d\'en haut]]
 - [[PageTitre Éditer le titre]]
 - [[PageRapideHaut Éditer le menu roue crantée]]
 - [[PageHeader Éditer le bandeau]]
 - [[PageFooter Éditer le footer]]
------
 - [[PageMenu Éditer le menu vertical (apparaissant sur les thèmes 2 colonnes ou plus)]]
 - [[PageColonneDroite Éditer la colonne de droite (apparaissant sur les thèmes 3 colonnes)]]
------
  - [[ReglesDeFormatage Éditer le mémo de formatage (bouton \"?\" dans la barre d\'édition )]]
------
===Gestion des mots clés ===
{{admintag}}
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererThemes',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMisesAJour\" titles=\"Gestion du site, Droits d\'accès aux pages, Thèmes graphiques, Utilisateurs et groupes, Mises à jour / extensions\"}}

===Gérer les thèmes des pages===
{{gererthemes}}
-----
===Gérer le thème par défaut du wiki===
{{setwikidefaulttheme}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('GererUtilisateurs',  now(), '{{nav links=\"GererSite, GererDroits, GererThemes, GererUtilisateurs, GererMisesAJour\" titles=\"Gestion du site, Droits d\'accès aux pages, Thèmes graphiques, Utilisateurs et groupes, Mises à jour / extensions\"}}

===Gérer les groupes d\'utilisateurs===
{{editgroups}}

===Liste des utilisateurs===
{{userstable}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('LookWiki',  now(), '======Tester les thèmes \"\"YesWiki\"\"======
{{grid}}
{{col size=\"4\"}}
======titre 1======
Bla blabla blablabla bla [[AccueiL Retour a la page d\'accueil]].

Etiam a sagittis justo. Aliquam vel egestas eros. Quisque eget dolor ornare, accumsan sem et, rhoncus diam. Morbi sodales neque vitae lorem ultrices, sit amet sollicitudin lectus tempor.** Donec quis mauris quis sem blandit faucibus** ut elementum lacus. //Orci varius natoque// penatibus et __magnis dis parturient__ montes, nascetur ridiculus mus. Interdum et malesuada @@fames ac ante ipsum primis @@in faucibus. Suspendisse vitae egestas nisi. **//__Pellentesque faucibus a elit vitae luctus__//**. Mauris condimentum vitae diam ut egestas. Etiam sed dui et lorem luctus pulvinar vel nec diam. 

{{end elem=\"col\"}}
{{col size=\"4\"}}
=====titre 2=====
Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec congue magna at dapibus facilisis. Suspendisse nisi ante, vehicula vel dolor non, laoreet eleifend ante. Aenean augue elit, cursus nec urna et, tincidunt commodo augue. Maecenas sed ex rhoncus, vehicula mauris sit amet, laoreet libero. Aliquam egestas ac risus sit amet cursus. Pellentesque vestibulum elit in dolor aliquam, quis molestie risus fermentum. Etiam non sem accumsan, faucibus est in, hendrerit nisi. Vivamus auctor in dui et egestas. Duis non ante sit amet risus euismod pulvinar. Suspendisse potenti. Duis sit amet malesuada lectus. 
{{end elem=\"col\"}}
{{col size=\"4\"}}
\"\"<div class=\"well\">\"\"====Choisir le thème, styles et squelettes associés====

{{themeselector}}\"\"</div>\"\"
{{end elem=\"col\"}}
{{end elem=\"grid\"}}

{{section class=\"full-width white\" bgcolor=\"var(--secondary-color-1)\" height=\"400\"}}
======Titre de test======
dsfds fdsf dsf dsf ds 

{{end elem=\"section\"}}

{{grid}}
{{col size=\"4\" class=\"text-justify\"}}
====titre 3====
{{attach file=\"bretagne.jpg\" desc=\"image bretagne.jpg (13.3MB)\" size=\"big\" class=\"\"}}
Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Proin volutpat vitae dolor ac gravida. Sed nulla nisl, tempor a pretium in, luctus sit amet nibh. Sed turpis augue, ultricies ac ante quis, luctus lacinia diam. Aliquam laoreet ante ac lacus sagittis condimentum. Vestibulum magna eros, imperdiet a magna vitae, pulvinar gravida lectus. Nulla at dui dui.
{{end elem=\"col\"}}
{{col size=\"4\" class=\"text-justify\"}}
===titre 4===
Phasellus accumsan velit nisi, id volutpat nulla malesuada at. Pellentesque nec eros a felis cursus interdum et vel nisl. Nulla ornare mollis malesuada. Donec felis neque, iaculis lobortis congue nec, iaculis ultricies sapien. Vestibulum et sagittis massa. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse potenti. Pellentesque quis dolor a libero vestibulum ornare. Donec tincidunt ante non maximus consequat. Praesent imperdiet pretium elit at rhoncus. Nullam elit sem, vehicula eget turpis eget, sagittis vulputate arcu. Proin non ligula at turpis consequat tempus vitae et ligula. Pellentesque ac sem nulla. Vestibulum pellentesque urna libero, eget consequat ex pellentesque a. Morbi non consectetur odio. Aliquam eget ornare lectus.
{{end elem=\"col\"}}
{{col size=\"4\" class=\"text-justify\"}}
==titre 5==
Proin viverra semper commodo. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Integer nunc nunc, maximus id pharetra eget, vulputate non neque. Donec condimentum sodales risus vel sodales. Maecenas placerat ac nulla id molestie. Ut vel aliquet sapien. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Sed erat purus, vulputate maximus tristique ac, varius eget sem. Etiam vehicula tellus efficitur diam molestie, non commodo tellus commodo. Maecenas ut posuere erat. Aliquam et ultrices augue. Aenean a quam lacinia, auctor mi id, rutrum ipsum. Sed massa nisi, fringilla sed odio accumsan, mattis scelerisque lectus. Mauris et consectetur turpis, sed tempus est. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus.
{{end elem=\"col\"}}
{{end elem=\"grid\"}}

====Labels====
\"\"<span class=\"label label-default\">label-default</span> <span class=\"label label-primary\">label-primary</span>  <span class=\"label label-secondary-1\">label-secondary-1</span> <span class=\"label label-secondary-2\">label-secondary-2</span>\"\"

====Alert====
{{section class=\"alert alert-default\" nocontainer=\"1\"}}
Attention ! Voici votre message. alert-default
{{end elem=\"section\"}}
{{section class=\"alert alert-primary\" nocontainer=\"1\"}}
Attention ! Voici votre message. alert-primary
{{end elem=\"section\"}}
{{section class=\"alert alert-secondary-1\" nocontainer=\"1\"}}
Attention ! Voici votre message. alert-secondary-1
{{end elem=\"section\"}}
{{section class=\"alert alert-secondary-2\" nocontainer=\"1\"}}
Attention ! Voici votre message. alert-secondary-2
{{end elem=\"section\"}}

====Panel====
\"\"<div class=\"panel panel-default\"><div class=\"panel-heading\"><h3 class=\"panel-title\">Titre default</h3></div><div class=\"panel-body\">Contenu panel-default</div></div>
<div class=\"panel panel-primary\"><div class=\"panel-heading\"><h3 class=\"panel-title\">Titre primary</h3></div><div class=\"panel-body\">Contenu panel-primary</div></div>
<div class=\"panel panel-secondary-1\"><div class=\"panel-heading\"><h3 class=\"panel-title\">Titre secondary-1</h3></div><div class=\"panel-body\">Contenu panel-secondary-1</div></div>
<div class=\"panel panel-secondary-2\"><div class=\"panel-heading\"><h3 class=\"panel-title\">Titre secondary-2</h3></div><div class=\"panel-body\">Contenu panel-secondary-2</div></div>\"\"


====Bouton====
{{button link=\"PagePrincipale\" class=\"btn btn-lg btn-default\" text=\"btn-default\"}}
{{button link=\"PagePrincipale\" class=\"btn btn-lg btn-primary\" text=\"btn-primary\"}}
{{button link=\"PagePrincipale\" class=\"btn btn-lg btn-secondary-1\" text=\"btn-secondary-1\"}}
{{button link=\"PagePrincipale\" class=\"btn btn-lg btn-secondary-2\" text=\"btn-secondary-2\"}}
----
{{button link=\"PagePrincipale\" class=\"btn btn-lg btn-success\" text=\"success\"}}
{{button link=\"PagePrincipale\" class=\"btn btn-lg btn-info\" text=\"info\"}}
{{button link=\"PagePrincipale\" class=\"btn btn-lg btn-warning\" text=\"warning\"}}
{{button link=\"PagePrincipale\" class=\"btn btn-lg btn-danger\" text=\"danger\"}}
{{button link=\"PagePrincipale\" class=\"btn btn-lg btn-link\" text=\"link\"}}

====Navs====
{{nav class=\"nav nav-pills\" links=\"LookWiki, GererDroits, GererThemes, GererUtilisateurs, GererMisesAJour\" titles=\"Gestion du site, Droits d\'accès aux pages, Thèmes graphiques, Utilisateurs et groupes, Mises à jour / extensions\"}}
-----
{{nav class=\"nav nav-tabs\" links=\"LookWiki, GererDroits, GererThemes, GererUtilisateurs, GererMisesAJour\" titles=\"Gestion du site, Droits d\'accès aux pages, Thèmes graphiques, Utilisateurs et groupes, Mises à jour / extensions\"}}


====List groups====
\"\"  <ul class=\"list-group\">
			<li class=\"list-group-item\">Cras justo odio</li>
			<li class=\"list-group-item\">Dapibus ac facilisis in</li>
			<li class=\"list-group-item\">Morbi leo risus</li>
			<li class=\"list-group-item\">Porta ac consectetur ac</li>
			<li class=\"list-group-item\">Vestibulum at eros</li>
		  </ul>\"\"
----
\"\" <div class=\"list-group\">
			<a href=\"#\" class=\"list-group-item active\">
			  Cras justo odio
			</a>
			<a href=\"#\" class=\"list-group-item\">Dapibus ac facilisis in</a>
			<a href=\"#\" class=\"list-group-item\">Morbi leo risus</a>
			<a href=\"#\" class=\"list-group-item\">Porta ac consectetur ac</a>
			<a href=\"#\" class=\"list-group-item\">Vestibulum at eros</a>
		  </div>\"\"
----
\"\" <div class=\"list-group\">
			<a href=\"#\" class=\"list-group-item active\">
			  <h4 class=\"list-group-item-heading\">List group item heading</h4>
			  <p class=\"list-group-item-text\">Donec id elit non mi porta gravida at eget metus. Maecenas sed diam eget risus varius blandit.</p>
			</a>
			<a href=\"#\" class=\"list-group-item\">
			  <h4 class=\"list-group-item-heading\">List group item heading</h4>
			  <p class=\"list-group-item-text\">Donec id elit non mi porta gravida at eget metus. Maecenas sed diam eget risus varius blandit.</p>
			</a>
			<a href=\"#\" class=\"list-group-item\">
			  <h4 class=\"list-group-item-heading\">List group item heading</h4>
			  <p class=\"list-group-item-text\">Donec id elit non mi porta gravida at eget metus. Maecenas sed diam eget risus varius blandit.</p>
			</a>
		  </div>

<span class=\"label label-success\">label-success</span> <span class=\"label label-info\">label-info</span> <span class=\"label label-warning\">label-warning</span> <span class=\"label label-danger\">label-danger</span>
<hr>
<div class=\"alert alert-success\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>Attention ! Voici votre message. Success</div>
<div class=\"alert alert-info\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>Attention ! Voici votre message. Info</div>
<div class=\"alert alert-warning\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>Attention ! Voici votre message. Warning</div>
<div class=\"alert alert-danger\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>Attention ! Voici votre message. Danger</div>
<hr>
<div class=\"panel panel-success\"><div class=\"panel-heading\"><h3 class=\"panel-title\">Titre success</h3></div><div class=\"panel-body\">Contenu success</div></div>
<div class=\"panel panel-info\"><div class=\"panel-heading\"><h3 class=\"panel-title\">Titre info</h3></div><div class=\"panel-body\">Contenu info</div></div>
<div class=\"panel panel-warning\"><div class=\"panel-heading\"><h3 class=\"panel-title\">Titre warning</h3></div><div class=\"panel-body\">Contenu warning</div></div>
<div class=\"panel panel-danger\"><div class=\"panel-heading\"><h3 class=\"panel-title\">Titre danger</h3></div><div class=\"panel-body\">Contenu danger</div></div>
<hr>
\"\"

=====Listes Bazar=====
====Accordéon====
{{bazar id=\"28\" nb=\"5\" vue=\"consulter\" voirmenu=\"0\" groups=\"checkboxListeMaListe\"  titles=\"Type d\'évènements\" filterposition=\"left\"}}

====Damier====
{{bazarliste nb=\"6\" template=\"damier.tpl.html\" valeurexergue=\"1\"}}

====Agenda====
{{calendrier}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('MotDePassePerdu',  now(), '{{lostpassword}}
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageColonneDroite',  now(), 'Double cliquer sur ce texte pour éditer cette colonne.











\"\"\"\"
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageFooter',  now(), '{{section height=\"60\" bgcolor=\"transparent\" class=\"text-center\"}}
(>^_^)> Galope sous [[https://www.yeswiki.net YesWiki]] <(^_^<)
{{end elem=\"section\"}}
\"\"<style>
.yw-headerpage h1 {margin-bottom:0;}
</style>\"\"', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageHeader',  now(), '{{section bgcolor=\"var(--neutral-soft-color)\" class=\"text-right\"}}

======Description de mon wiki======
Double cliquer ici pour changer le texte.

{{end elem=\"section\"}}


\"\"<!-- INFO CACHÉE pour vous aider
Pour modifier l\'image du bandeau, changez simplement le 1 de \"bandeau1\" par 2 par ex. quand vous sauverez yeswiki vous proposera de charger une nouvelle image
 - cette image s\'affichera d\'autant mieux qu\'elle sera préparée en amont (taille 1920 X 300, 90 dpi de résolution)

Si vous souhaitez que le texte soit noir plutôt que blanc par rapport à la couleur ou image de fond, enlevez le white dans class=\"white text-right cover\" (au passage, vous pourrez aussi caler le texte en left ou center)

Si vous souhaitez plutôt avoir un aplat de couleur plutôt qu\'une image, supprimez file=\"bandeau1.jpeg\" et à la place de bgcolor=\"var(--neutral-soft-color)\" remplacer par un code couleur => mauve : #990066 / vert : #99cc33 / rouge : #cc3333 / orange : #ff9900 / bleu : #006699 Voir les codes hexa des couleurs : http://fr.wikipedia.org/wiki/Liste_de_couleurs 
 -->\"\"', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageMenu',  now(), 'Double cliquer sur ce texte pour éditer cette colonne.











\"\"\"\"
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageMenuHaut',  now(), ' - [[BacASable Bac à sable]]
 - [[AidE Aide]]
\"\"<!-- ---------------------------------------------------------------------------------------------
INFO CACHÉE pour vous aider
Comme vous le voyez le menu est une simple page.
 - Cette page contient une liste dont chaque item correspond à une entrée de menu.
 - Et pour renvoyer vers une page, on utilise de simples liens.
Pour savoir comment faire une liste à puce, cliquez sur le bouton \"?\" ci-dessus. 
vous en saurez plus sur : https://yeswiki.net/?EditerMenu
-------------------------------------------------------------------------------------------------->\"\"', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PagePrincipale',  now(), '======Félicitations, votre wiki est installé ! ======{{grid}}
{{col size=\"6\"}}
===\"\"YesWiki\"\" : un outil convivial potentiellement collaboratif===
Voici quelques éléments afin de bien démarrer de vous approprier ce nouvel outil.
 - Le double-clic est votre ami ! Si vous voulez modifier une page de votre Yeswiki, double-cliquez simplement dessus ou cliquez sur \"éditer la page\" en bas à gauche
  - Si vous voulez vous exercer sereinement, vous pouvez essayer de modifier la page [[BacASable bac à sable]]. 
  - Vous pouvez également essayer de modifier de la même manière la page sur laquelle vous êtes actuellement. 
  - Vous souhaitez modifier le menu horizontal général ? Double-cliquez gauche sur ce menu (en dehors du texte), et vous aurez accès à l\'édition de ce menu. Utilisez les tirets (\"-\") pour créer de nouvelles entrées.
  - Et il y a une page de la doc \"\"YesWiki\"\" à lire absolument, celle qui vous permet de [[https://yeswiki.net/?HistoriqueRevisions restaurer une page modifiée en cas d\'erreur ou de problème]]. Comme ça, aucun risques !!!
 - Le menu d\'administration en haut à droite, accessible depuis la roue crantée (clic gauche) vous permettra :
  - de [[WikiAdmin gérer le site (pages importantes, comptes et groupes utilisateurs, etc.)]],
  - d’administrer la [[BazaR base de données Bazar]],
  - de consulter les [[TableauDeBord dernières modifications sur le wiki]].

{{end elem=\"col\"}}
{{col size=\"6\"}}
===\"\"YesWiki\"\" : une communauté===
En plus d\'être un logiciel de création de wikis, \"\"YesWiki\"\" est aujourd\'hui maintenu et amélioré par une communauté de professionnels et d\'utilisateurs issus d\'horizons différents qui prend du plaisir à partager ses rêves, ses créations et ses développements. Nous serons ravi·e·s de vous accueillir !

Pour nous rejoindre ou avoir une vision sur les chantiers actuellement en cours, voici notre [[https://yeswiki.net/?LaGareCentrale espace central]].

Si vous souhaitez simplement être tenu·e informé·e des nouveautés de l\'outil et de ses améliorations, abonnez-vous à notre newsletter {{abonnement mail=\"infos-yeswiki@framalistes.org\" mailinglist=\"sympa\"}}

{{end elem=\"col\"}}
{{end elem=\"grid\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageRapideHaut',  now(), '{{moteurrecherche template=\"moteurrecherche_button.tpl.html\"}}
{{buttondropdown icon=\"fas fa-cog\" caret=\"0\"}}
 - {{login template=\"modal.tpl.html\" nobtn=\"1\"}}
 - ------
 - {{button nobtn=\"1\" icon=\"fa fa-question\" text=\"Aide\" link=\"AidE\"}}
 - ------
 - {{button nobtn=\"1\" icon=\"fa fa-wrench\" text=\"Gestion du site\" link=\"GererSite\"}}
 - {{button nobtn=\"1\" icon=\"fa fa-tachometer-alt\" text=\"Tableau de bord\" link=\"TableauDeBord\"}}
 - {{button nobtn=\"1\" icon=\"fa fa-briefcase\" text=\"Base de données\" link=\"BazaR\"}}
{{end elem=\"buttondropdown\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('PageTitre',  now(), '{{configuration param=\"wakka_name\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ParametresUtilisateur',  now(), '{{UserSettings}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('RechercheTexte',  now(), '{{newtextsearch}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('ReglesDeFormatage',  now(), '{{grid}}
{{col size=\"6\"}}
=====Règles de formatage=====
===Accentuation===
\"\"<pre>\"\"**\"\"**Gras**\"\"**
//\"\"//Italique//\"\"//
__\"\"__Souligné__\"\"__
@@\"\"@@Barré@@\"\"@@\"\"</pre>\"\"
===Titres===
\"\"<pre>\"\"======\"\"======Titre 1======\"\"======
=====\"\"=====Titre 2=====\"\"=====
====\"\"====Titre 3====\"\"====
===\"\"===Titre 4===\"\"===
==\"\"==Titre 5==\"\"==\"\"</pre>\"\"
===Listes===
\"\"<pre> - Liste à puce niveau 1
 - Liste à puce niveau 1
  - Liste à puce niveau 2
  - Liste à puce niveau 2
 - Liste à puce niveau 1

 1. Liste énumérée
 2. Liste énumérée
 3. Liste énumérée</pre>\"\"
===Liens===
\"\"<pre>[[http://www.exemple.com Texte qui s\'affichera pour le lien externe]]\"\"
\"\"[[PageDeCeWiki Texte qui s\'affichera pour le lien interne]]</pre>\"\"
===Lien qui force l\'ouverture vers une page extérieure===
%%\"\"<a href=\"http://exemple.com\" target=\"_blank\">ton texte</a>\"\"%%
===Images===\"\"<a href=\"https://yeswiki.net/?DemoAttach\" target=\"_blank\" class=\"btn btn-primary btn-xs\">Doc en ligne</a>\"\"
//\"\"<pre>Pour télécharger une image, utiliser le bouton Joindre/insérer un fichier</pre>\"\"//
===Tableaux===
\"\"<pre>
[|
| Colonne 1 | Colonne 2 | Colonne 3 |
| John     | Doe      | Male     |
| Mary     | Smith    | Female   |
|]
</pre>\"\"
===Boutons wiki=== \"\"<a href=\"https://yeswiki.net/?DemoButton\" target=\"_blank\" class=\"btn btn-primary btn-xs\">Doc en ligne</a>\"\"
\"\"<pre>{{button class=\"btn btn-danger\" link=\"lienverspage\" icon=\"plus icon-white\" text=\"votre texte\"}}</pre>\"\"
===Créer un bouton qui ouvre son contenu dans un nouvel onglet===
%%\"\"<a href=\"votrelien\" target=\"_blank\" class=\"btn btn-primary btn-xs\">votre texte</a>\"\"%%
===Ecrire en html===
\"\"<pre>si vous déposez du html dans la page wiki, 
il faut l\'entourer de &quot;&quot; <bout de html> &quot;&quot; 
pour qu\'il soit interprété</pre>\"\"
===Placer du code en commentaire sur la page===
%%\"\"<!-- en utilisant ce code on peut mettre du texte qui n’apparaît pas sur la page... ce qui permet de laisser des explications par exemple ou même d\'écrire du texte en prépa d\'une publication future -->\"\"%%
{{end elem=\"col\"}}
{{col size=\"6\"}}
=====Code exemples=====
===Insérer un iframe===\"\"<a href=\"https://yeswiki.net/?DocumentationIntegrerDuHtml\" target=\"_blank\" class=\"btn btn-primary btn-xs\">Doc en ligne</a>\"\"
//Inclure un autre site, ou un pad, ou une vidéo youtube, etc...//
%%\"\"<iframe width=100% height=\"1250\" src=\"http://exemple.com\" frameborder=\"0\" allowfullscreen></iframe>\"\"%%
===Texte en couleur===
%%\"\"<span style=\"color:#votrecodecouleur;\">votre texte à colorer</span>\"\"%%//Quelques codes couleur => mauve : 990066 / vert : 99cc33 / rouge : cc3333 / orange : ff9900 / bleu : 006699////Voir les codes hexa des couleurs : [[http://fr.wikipedia.org/wiki/Liste_de_couleurs http://fr.wikipedia.org/wiki/Liste_de_couleurs]]//
===Message d\'alerte===
//Avec une croix pour le fermer.//
%%\"\"<div class=\"alert\">
<button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>
Attention ! Voici votre message.
</div>\"\"%%
===Label \"important\" ou \"info\"===
\"\"<span class=\"label label-danger\">Important</span>\"\" et \"\"<span class=\"label label-info\">Info</span>\"\"
%%\"\"<span class=\"label label-danger\">Important</span>\"\" et \"\"<span class=\"label label-info\">Info</span>\"\"%%
===Mise en page par colonne===\"\"<a href=\"https://yeswiki.net/?DemoGrid\" target=\"_blank\" class=\"btn btn-primary btn-xs\">Doc en ligne</a>\"\"
//le total des colonnes doit faire 12 (ou moins)//
%%{{grid}}
{{col size=\"6\"}}
===Titre de la colonne 1===
Texte colonne 1
{{end elem=\"col\"}}
{{col size=\"6\"}}
===Titre de la colonne 2===
Texte colonne 2
{{end elem=\"col\"}}
{{end elem=\"grid\"}}%%
===Créer des onglets dans une page===\"\"<a href=\"https://yeswiki.net/?DocumentationMiseEnPageOnglet\" target=\"_blank\" class=\"btn btn-primary btn-xs\">Doc en ligne</a>\"\"
Il est possible de créer des onglets au sein d\'une page wiki en utilisant l\'action {{nav}}. La syntaxe est (elle est à répéter sur toutes les pages concernée par la barre d\'onglet)
\"\"<pre>{{nav links=\"NomPage1, NomPage2, NomPage3Personne\" titles=\"TitreOnglet1, TitreOnglet2, TitreOnglet3\"}}</pre>\"\"
===Formulaires de contact===\"\"<a href=\"https://yeswiki.net/?DocumentationMiseEnPageOnglet\" target=\"_blank\" class=\"btn btn-primary btn-xs\">Doc en ligne</a>\"\"
\"\"<pre>{{contact mail=\"adresse.mail@exemple.com\" entete=\"ce qui sera dans l\'objet du mail que vous recevrez\"}}</pre>\"\"
===Inclure une page dans une autre===
%%{{include page=\"NomPageAInclure\"}} %%
Pour inclure une page d\'un autre yeswiki : ( Noter le pipe \"\"|\"\" après les premiers \"\"[[\"\" ) %%[[|http://lesite.org/nomduwiki PageAInclure]]%%
===Image de fond avec du texte par dessus===\"\"<a href=\"https://yeswiki.net/?BackgroundimagE\" target=\"_blank\" class=\"btn btn-primary btn-xs\">Doc en ligne</a>\"\"
//Avec possibilité de mettre du texte par dessus//
%%{{backgroundimage height=\"150\" file=\"monbandeau.jpg\" class=\"white text-center doubletitlesize\"}}
=====Texte du titre=====
description
{{endbackgroundimage}}%%
===Couleur de fond avec du texte par dessus===
//Avec possibilité de mettre du texte par dessus//
%%{{backgroundimage height=\"150\" bgcolor=\"#2BB34A\" class=\"white text-center doubletitlesize\"}}
=====Texte du titre=====
description
{{endbackgroundimage}}%%
{{end elem=\"col\"}}
{{end elem=\"grid\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('TableauDeBord',  now(), '======Tableau de bord======
{{mailperiod}}
{{grid}}
{{col size=\"4\"}}
====Derniers comptes utilisateurs ====
{{Listusers}}
------
====Dernières pages modifiées ====
{{recentchanges}}
------
==== Pages orphelines ====
{{OrphanedPages}}
------
{{end elem=\"col\"}}
{{col size=\"4\"}}
==== Index des fiches bazar ====
{{bazarrecordsindex}}
{{end elem=\"col\"}}
{{col size=\"4\"}}
==== Index des pages seules ====
{{pageonlyindex}}
------
{{end elem=\"col\"}}
{{end elem=\"grid\"}}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('WikiAdmin',  now(), '{{redirect page=\"GererSite\"}}
', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', '');
# end YesWiki pages

# Bazar forms
INSERT INTO `{{prefix}}__nature` (`bn_id_nature`, `bn_label_nature`, `bn_description`, `bn_condition`, `bn_sem_context`, `bn_sem_type`, `bn_sem_use_template`, `bn_template`, `bn_ce_i18n`) VALUES
('28', 'Evénement', 'Formulaire basique pour lister des événements et éventuellement les afficher dans un calendrier.', '', '', '', '', 'texte***bf_titre***Nom de l\'événement***60***255*** *** *** ***1***0***
textelong***bf_description***Description***40***10*** *** *** *** 
checkbox***ListeMaListe***Type d\'évènement*** ***1*** *** *** ***1***1***
texte***bf_exergue***Mettre en avant *** ***20*** *** *** ***1***1***
jour***bf_date_debut_evenement***Début de l\'événement***1*** *** *** *** ***1***0
jour***bf_date_fin_evenement***Fin de l\'événement***1*** ***  *** ***  ***1***0
lien_internet***bf_site_internet***Site Internet***40***255***http://***http://*** ***0***0', 'fr-FR');
# end Bazar forms

# Bazar lists
INSERT INTO `{{prefix}}__pages` (`tag`, `time`, `body`, `body_r`, `owner`, `user`, `latest`, `handler`, `comment_on`) VALUES
('ListeMaliste',  now(), '{\"label\":{\"opt1\":\"Choix 1 \",\"opt2\":\"Choix 2\",\"opt3\":\"Choix 3\"},\"titre_liste\":\"MaListe\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', '');
INSERT INTO `{{prefix}}__triples` (`resource`, `property`, `value`) VALUES
('ListeMaliste', 'http://outils-reseaux.org/_vocabulary/type', 'liste');
# end Bazar lists

# Bazar entries
INSERT INTO `{{prefix}}__pages` (`tag`, `time`, `body`, `body_r`, `owner`, `user`, `latest`, `handler`, `comment_on`) VALUES
('TestDate',  now(), '{\"bf_titre\":\"Test date\",\"bf_description\":\"https:\\/\\/yeswiki.net\",\"checkboxListeMaListe\":\"opt1\",\"bf_exergue\":\"1\",\"bf_date_debut_evenement\":\"2019-10-29\",\"bf_date_debut_evenement_allday\":\"1\",\"bf_date_debut_evenement_hour\":\"00\",\"bf_date_debut_evenement_minutes\":\"00\",\"bf_date_fin_evenement\":\"2019-10-31\",\"bf_date_fin_evenement_allday\":\"1\",\"bf_date_fin_evenement_hour\":\"00\",\"bf_date_fin_evenement_minutes\":\"00\",\"bf_site_internet\":\"https:\\/\\/yeswiki.net\",\"id_typeannonce\":\"28\",\"id_fiche\":\"TestDate\",\"createur\":\"WikiAdmin\",\"date_creation_fiche\":\"2019-10-23 15:11:59\",\"statut_fiche\":\"1\",\"date_maj_fiche\":\"2019-10-25 07:31:49\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', ''),
('YoupiIciCEstLeTitre',  now(), '{\"bf_titre\":\"Youpi ici c\'est le titre\",\"bf_description\":\"il faut que l\'on descrive des trucs un peu plus long pour voir si cela rentre bien\",\"checkboxListeMaListe\":\"opt3\",\"bf_exergue\":\"N\",\"bf_date_debut_evenement\":\"2019-10-10\",\"bf_date_debut_evenement_allday\":\"1\",\"bf_date_debut_evenement_hour\":\"00\",\"bf_date_debut_evenement_minutes\":\"00\",\"bf_date_fin_evenement\":\"2019-10-17\",\"bf_date_fin_evenement_allday\":\"1\",\"bf_date_fin_evenement_hour\":\"00\",\"bf_date_fin_evenement_minutes\":\"00\",\"bf_site_internet\":\"\",\"id_typeannonce\":\"28\",\"id_fiche\":\"YoupiIciCEstLeTitre\",\"createur\":\"WikiAdmin\",\"date_creation_fiche\":\"2019-10-23 15:27:17\",\"statut_fiche\":\"1\",\"date_maj_fiche\":\"2019-10-25 07:32:11\"}', '', '{{WikiName}}', '{{WikiName}}', 'Y', 'page', '');
INSERT INTO `{{prefix}}__triples` (`resource`, `property`, `value`) VALUES
('TestDate', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar'),
('YoupiIciCEstLeTitre', 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar');
# end Bazar entries

