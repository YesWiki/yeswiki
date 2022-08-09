# Documentation webmaster
## Installation / mise à jour
### Installer par ftp
#### Pré-requis
*   Vous avez téléchargé la dernière version de YesWiki sur le site [yeswiki.net](http://yeswiki.net/wakka.php?wiki=TelechargemenT)
*   Vous disposez d'un espace d'hébergement avec PHP version >= 7.3) et MariaDB > 10 ou MYSQL >= 5.6 (⚠️ la version 5.5 ne supporte pas la recherche fulltext) et des droits d'accès à l'hébergement (codes FTP et MYSQL) > **Attention : [voir les instructions spécifiques pour l'installation sur les hébergements Free.fr](https://yeswiki.net/?DocumentationInstallationFree)**
*   Vous disposez d'un logiciel pour faire du FTP (le client FTP libre [FileZilla](http://filezilla-project.org/) par exemple)
*   _En cas de bug pour les mises à jour sur certains systèmes très légers, vérifiez la présence des librairies php-stype, php-curl, php-filter, php-gd, php-iconv, php-json, php-mbstring, php-mysqli, php-pcre et php-zip ; la liste à jour des extensions est décrite dans [le fichier composer.json](https://github.com/YesWiki/yeswiki/blob/doryphore/composer.json)._
*   _Les extensions peuvent nécessiter des librairies supplémentaires. Vérifiez le contenu du fichier README.md de chacune (ex.: [ferme](https://github.com/YesWiki/yeswiki-extension-ferme/blob/doryphore/README.md), [lms](https://github.com/YesWiki/yeswiki-extension-lms/blob/doryphore/README.md))._

#### Préparation
*   Décompressez le fichier téléchargé sur votre disque dur et renommez-le à votre convenance (par exemple monYeswiki)
*   Si elle n'existe pas déjà, créez une base de données vide sur votre espace d'hébergement (par exemple mabase).
*   Le plus souvent, il vous faudra créer un utilisateur pour cette base de donnée avec un identifiant et un mot de passe (par exemple identifiant = moi, mot de passe = oups)
*   Dans la mesure du possible évitez les identifiant avec "tiret" (genre moi-moi) car ça crée parfois une erreur lors de l'installation
*   Noter le nom de votre base de données et les identifiants et mot de passe d'accès à celle-ci.

#### Upload par FTP
*   Connectez-vous à votre espace personnel par FTP (filezilla par exemple)
*   Glissez et déposez votre dossier local (monYeswiki) sur votre espace personnel
*   Sur certains hébergements, il faut attribuer des droits d'accès en écriture au dossier principal du wiki (monYeswiki) : mettre les droit d'accès en écriture pour tous (chmod 777), en faisant clic droit sur le dossier puis "droits d'accès au dossier" dans filezilla)
    *   Il est bon aussi (pour éviter des erreurs futures lors de l'insertion d'images dans le wiki par exemple) de déjà attribuer au dossier "Cache" et "File" des droits d'accès en écriture pour tous (777)
*   Fermez le client Ftp. Nous allons pouvoir configurer le yeswiki.
<iframe sandbox="allow-same-origin allow-scripts" src="https://video.coop.tools/videos/embed/70cb02cf-606e-417d-8458-f1f265c0d3c8" allowfullscreen="" tc-textcontent="true" data-tc-id="w-0.010567584623263016" width="100%" height="315" frameborder="0"></iframe>

#### Paramétrage du YesWiki
*   Ouvrez votre navigateur et tapez l'url de votre site perso jusqu'au répertoire créé.
*   Une page de configuration s'ouvre.
*   Renseignez le nom de votre serveur MySQL (donné par votre hébergeur, en général c'est "localhost")
*   Renseignez le nom de votre base de données MySQL (dans notre exemple : mabase)
*   Renseignez le nom d'utilisateur et le mot de passe de votre base de données MySQL (dans notre exemple : moi et oups)
*   Renseignez le préfixe des tables : par défaut yeswiki\_ (vous pouvez laisser comme cela)
*   Modifiez le nom de votre YesWiki (cela deviendra le titre de votre wiki affiché en grand en haut du site... modifiable par après si nécessaire - ne pas mettre de caractères spéciaux html, typiquement "&Eacute", "&Egrave"; )
*   Renseignez la page d'accueil de votre YesWiki. Ceci doit être un [NomWiki](https://yeswiki.net/?NomWiki). : par défaut [PagePrincipale](https://yeswiki.net/?PagePrincipale) (vous pouvez laisser comme cela)
*   Renseignez les champs mots-clés et description si vous le souhaitez (si vous laissez vide cela ne posera pas de problème)
*   Renseignez les champs (Administrateur, Mot de passe, Confirmation du mot de passe et Adresse e-mail) afin de créer un compte administrateur pour gérer votre wiki
*   Les pages par défaut de YesWiki nécessitent du code html pour fonctionner **cocher la case "Autoriser l'insertion de HTML brut."**. Vous pourrez alors plus facilement intégrer des widgets HTML.
*   


Les tables MySQL sont automatiquement créées.  
Si tout a bien fonctionné vous en avez confirmation.  
Le fichier de configuration est écrit. C'est terminé.  

<iframe sandbox="allow-same-origin allow-scripts" src="https://video.coop.tools/videos/embed/30cc117a-db65-4539-8547-749f52008212" allowfullscreen="" tc-textcontent="true" data-tc-id="w-0.9930878636773818" width="100%" height="315" frameborder="0"></iframe>
  

### Paramétrer son wakka.config.php 
Une fois le YesWiki créé, on peut aller éditer le fichier **wakka.config.php**, se trouvant à la racine du dossier du YesWiki, accessible par FTP. Le fichier de configuration déclare un tableau avec des valeurs pour chaque élément de configuration.  
  
**Voici le contenu du fichier de configuration par défaut**, voir les commentaires en fin de ligne pour le détail de chaque élément de configuration :  
  

    <?php
    // wakka.config.php créée Fri Jun  8 20:58:37 2012
    // ne changez pas la wikini_version manuellement!
    
    $wakkaConfig = array ( // tableau de configuration
      'wakka_version' => '0.1.1', // Ne pas toucher, version originale du code de wakka, ancêtre de wikini
      'wikini_version' => '0.5.0', // Ne pas toucher, version originale du code de wikini, ancêtre de YesWiki
      'debug' => 'no', // active le mode de débogage si passé à la valeur 'yes' (infos sur le nombre de requêtes, le temps écoulé et force l'affichage des erreurs php pour les développeurs) astuce : on peut aussi passer &debug dans l'url pour debugguer
      'mysql_host' => 'localhost', 
      'mysql_database' => 'yeswiki',
      'mysql_user' => 'yeswiki',
      'mysql_password' => '#######',
      'table_prefix' => 'yeswiki_',
      'root_page' => 'PagePrincipale',
      'wakka_name' => 'YesWiki de présentation', // titre du YesWiki
      'base_url' => 'http://localhost/?', // url d'accès au site
      'rewrite_mode' => '0', 
      'meta_keywords' => 'yeswiki, wiki, gpl, php, super', // mot clé pour le référencement (séparés par des virgules, par plus de 20-30)
      'meta_description' => 'Un site de présentation de ce merveilleux outil qu\'est YesWiki', // description du site en une phrase, pour le référencement (Attention : ne pas mettre de "." (point) dans ces méta descriptions
      'action_path' => 'actions',
      'handler_path' => 'handlers',
      'header_action' => 'header',
      'footer_action' => 'footer',
      'navigation_links' => 'DerniersChangements :: DerniersCommentaires :: ParametresUtilisateur',
      'referrers_purge_time' => 24,
      'pages_purge_time' => 90, // nbr de jours après lesquels les révisions sont effacées
      'default_write_acl' => '*', // droits d'écriture par défaut des pages
      'default_read_acl' => '*', // droits de lecture par défaut des pages
      'default_comment_acl' => '*', // droits des commentaires
      'preview_before_save' => '0',
      'allow_raw_html' => '1', // autorise le html
    );
    
  

#### Les éléments que vous pouvez rajouter sur wakka.config.php

##### Changer les droits de lectures et d'écriture par défaut

Par défaut, les pages de votre wiki sont visible set éditables par tout visiteur. Pour limiter la lecture et l'écriture aux seuls administrateurs par exemple, il faut changer les lignes  

      'default_write_acl' => '*', // droits d'écriture par défaut des pages
      'default_read_acl' => '*', // droits de lecture par défaut des pages

  
en  

      'default_write_acl' => '@admins', // droits d'écriture par défaut des pages
      'default_read_acl' => '@admins', // droits de lecture par défaut des pages

##### Changer l'url de votre wiki

Mon wiki se trouve à l'adresse suivante [http://site-coop.net/Louise,](http://site-coop.net/Louise,) je souhaiterais qu'il se nomme maintenant [http://site-coop.net/Mathieu](http://site-coop.net/Mathieu)  
\- Via ftp, il faut changer le nom du dossier Louise en le nommant Mathieu  
\- dans le fichier wakka.config.php, il faut changer la ligne  

    'base_url' => 'http://site-coop.net/Louise/?" 

  
en  

    'base_url' => 'http://site-coop.net/Mathieu/?"

  
et tester si tout fonctionne dans votre navigateur (attention les majuscules et minuscules ont leur importance)  
  
Pour tous les détails sur les droits d'accès : [https://yeswiki.net/?DocumentationDroitsDAcces](https://yeswiki.net/?DocumentationDroitsDAcces)  

##### Envoyer un mail aux @admins à chaque nouvel encodage de fiche

    'BAZ_ENVOI_MAIL_ADMIN' => true

#### Lutter contre le spam


##### Changer le thème par défaut de votre wiki
    
      'favorite_theme' => 'yeswiki', // theme par défaut, présent dans /themes ou /tools/templates/themes
      'favorite_squelette' => 'responsive-1col.tpl.html', // squelette par défaut
      'favorite_style' => 'yellow.css', // style css par défaut
      'favorite_background_image' => 'graphy.png', // image de fond par défaut, située dans /files/backgrounds
      'hide_action_template' => '1', // Force le template par défaut et empêche la modification du thème lors de l'édition.
    
##### Cacher le bouton Thèmes de votre wiki en mode édition
    
      'hide_action_template' => '1', // Cache le bouton thème et empêche la modification du thème lors de l'édition.

##### Changer l'affichage par défaut des cartes

Par défaut les cartes sont centrées sur le centre de la France et affiche l'intégralité de la France. On peut forcer le centre ailleurs en configurant dans wakaconfig :  

    
      'baz_map_center_lat' => '50.725777', //permet de caler les cartes utilisées dans le wiki sur cette latitude
      'baz_map_center_lon' => '4.867795', //permet de caler les cartes utilisées dans le wiki sur cette longitude
      'baz_map_zoom' => '8', //permet de caler les cartes utilisées dans le wiki sur ce niveau de zoom

##### Empêcher la création de pages SAUF via les formulaires

Permet de saisir une fiche formulaire même si la création de page YesWiki est interdite  

    
      'default_write_acl' => '@admins', // ceci interdit la création de pages sauf pour les @admins
      'bazarIgnoreAcls' => true, // ceci permet de passer au-dessus de cette interdiction uniquement via les formulaires
    

##### Utiliser une base sql avec port privé...

si la base mysql utilise un autre port que 3306, vous pouvez spécifier le numéro du port privé  

     'mysql_port' => 'n° du port',
* * *

##### Facebook opengraph : choisir l'image utilisée quand on partage un wiki sur FB

pour ouvrir une image par défaut : il faut mettre un lien vers l'image dans le wakka.config.php  
idéalement, l'image doit faire 1200x630 selon les specs imposées par facedebouc  
par défaut il prend cette image, et si une image est présente dans la page (mise avec attach) ou une fiche bazar avec bf\_image , il remplace par cela  

    
    'opengraph_image' => 'https://domaine.ext/nomdelimage.jpg',
  
* * *

#### Réparer les wikis qui n'envoient pas les mails 
Concerne
*   Codes utiles /raw...
*   Hors yeswiki

La réponse sur certains hébergements, l'envoi de mail par défaut ne marche pas , il faut créer un compte smtp  
et donc rajouter dans le fichier wakka.config.php les parametres suivants  

      'contact\_mail\_func' => 'smtp',
      'contact\_smtp\_host' => 'ssl://<mon serveur smtp>:465',
      'contact\_smtp\_user' => 'user@mail.ext',
      'contact\_smtp\_pass' => '<monpassword>',

Attention, tous les serveur mail n'accepte pas de jouer ce jeu.  

#### avec sendinblue

créez-vous un compte puis allez dans les paramètres (via [ce lien](https://account.sendinblue.com/advanced/api)) chercher votre clé smtp (limitée à 300 mails par jour)  

    'contact\_mail\_func' => 'smtp',
    'contact\_smtp\_host' => 'smtp-relay.sendinblue.com:587',
    'contact\_smtp\_user' => 'monmail@pourmoncomptesendinblue.com',
    'contact\_smtp\_pass' => '<ma cle smtp>',

  
ou

    'contact\_mail\_func' => 'smtp',
    'contact\_smtp\_host' => 'smtp-relay.sendinblue.com',
    'contact\_smtp\_port' => '587',
    'contact\_smtp\_user' => 'monmail@pourmoncomptesendinblue.com',
    'contact\_smtp\_pass' => '<ma cle smtp>',

  

#### avec gmail

Gmail le fait mais avec une limite d'envoi journalière et souvent un blocage de scurité à lever via un paramètre : plus d'infos ici  
[https://support.google.com/accounts/answer/6010255](https://support.google.com/accounts/answer/6010255)  

    'contact\_smtp\_host' =>     'ssl://smtp.gmail.com:465',
  
Autre piste possible, acheter un nom de domaine chez gandi et utiliser le smtp lié

### Mettre à jour son wiki
La mise à jour du wiki s'effectue via la page gestion du site (onglet mise à jour / extensions) accessible via la roue crantée.
Il faut être connecté et membre du groupe admin pour pouvoir agir sur cette page. 
Sur celle-ci, vous trouvez : 
 * La version du Wiki : doryphore XXX
 * La version disponible sur le dépot : doryphore YYY
 * et un bouton permettant d'installer la nouvelle version si les deux versions diffèrent ou de réinstaller la version actuelle. 
 * Notes de versions : un lien qui vous emmène vers le changelog de la nouvelle version (présentation des ajouts de cette version)

Sur cette page, vous pouvez aussi installer, mettre à jour ou désinstaller les thèmes ou extensions de votre wiki. 
Un message d'alerte vous informe si une nouvelle version d'un thème ou d'une extension existe. 

## Activer les extensions 
Yeswiki peut s'enrichir en fonctionnalités en activant des extensions. 
Celles-ci s'activent via la page gestion du site (onglet mise à jour / extensions) accessible via la roue crantée.
Il faut être connecté et membre du groupe admin pour pouvoir agir sur cette page. 

Pour chaque extension, une documentation est proposée. 
Vous pouvez : 
 * l'installer
 * lamettre à jour si une nouvelle version est disponible
 * la désinstaller

## Lutter contre le spams 

Hélas comme la plupart des wikis ouverts (MediaWiki, DokuWiki), YesWiki n'échappe pas aux attaques de quelques ~~emmerdeurs~~ référenceurs soit-disant professionnels et autres robots de spam, qui polluent les contenus des pages.  
  

### Les 10 commandements du lutteur anti spam

* **1**. Je consulte régulièrement mon wiki  
* **2**. Je m'abonne à son flux RSS [voir plus bas / suivre la vie de mon wiki](#Suivre-la-vie-de-son-wiki)  
* **3**. Je consulte la page TableauDeBordDeCeWiki de mon wiki (accessible depuis la "roue crantée")  
* **4**. Je vérifie les dernières pages modifiées dans le TableauDeBordDeCeWiki ou sur la page DerniersChangements  
* **5**. Je vérifie les derniers comptes crées sur la page TableauDeBordDeCeWiki. (Action {{Listusers last="20"}} )  
* **6**. J'édite les pages en question et je supprime les parties indésirables, puis je sauve. (Cela prend moins d'une minute)  
* **7**. Je protège l'accès en écriture des pages spéciales du wiki (menu, roue crantée, footer...)  
* **8**. Je maintiens mon wiki à jour  
* **9**. Pour les plus endurcis, je fais le grand ménage avec l'outil despam (voir plus bas)  
* **10**. Je ne cède pas à la tentation de transformer mon espace collaboratif en bunker. Et je continue à mettre en balance les effets positifs de l'intelligence collective.  

  

### Les symptômes : comment identifier les spams ?

*   Vous pouvez découvrir sur une ou plusieurs pages des liens vers des sites externes qui semblent sans rapport avec l'objet du wiki _(qui vendent des robes de mariée, des sites indonésien sans rapport, des liens commerciaux vers la loi duflot, des textes en langue étrangère etc..)_
*   Il se peut aussi que de nouvelles pages soit créées, et dans certains cas de nouveaux utilisateurs wikis.

Dans tous les cas, il sera toujours possible de faire marche arrière, et les informations sensibles d'accès ftp ou mysql à votre serveur ne peuvent pas être trouvés comme cela.  


### Que faire si vous avez du spam ?

#### Réparer une page spéciale spammée

_Tiens, ce matin, en me baladant sur un de mes YesWiki j'ai découvert que j'avais été spammé avec un bel écran bizarre à la place de ma page d'accueil et impossible de pouvoir modifier quoique ce soit !_  
  
##### Si votre wiki est ouvert en écriture: 

*   1\. identifier la page spammée en ajoutant le handler /editiframe
    aux pages spéciales. Voici ci-dessous, la liste des pages spéciales concernées.
*   2\. Dès que le code malicieux est repéré, supprimer ce code et sauvegarder la page.
*   3\. Revenir sur la liste des versions de cette page pour éditer la version avant l'apparition du code malicieux et remettre en place le contenu précédent

##### Si votre wiki est fermé en écriture

Il peut être impossible de se connecter au wiki. Ceci peut contourner en utilisant ce lien qui permet de ne pas afficher les pages spéciales : https://www.example.com/?ParametresUtilisateur/iframe  

#### Utiliser les paramètres de contrôle d'accès via le wakka config ou la page ["Fichier de conf"](https://yeswiki.net/?GererConfig) XXXXXX à MODIFIER

Des nouveaux paramètres ont été ajoutés dans le wakkaconfig et permettent notamment  

*   d'ajouter un capcha en mode édition
*   d'ajouter un champ (mot de passe) en entrée du mode édition (+ un message informatif sur ce mot de passe)

Les paramètres ajoutables au wakkaconfig        

    'password\_for\_editing' => 'votremotdepasse',
    'password\_for\_editing\_message' => 'un message qui apparait au dessus du champ mot de passe',
    'use\_hashcash' => true, //ne pas toucher pour l'instant            
    'use\_nospam' => true, // ne pas toucher pour l'instant 'use\_alerte' => true,
    'use\_captcha' => true,

Ces paramètres sont aussi activables via la page de gestion du site (onglet fichier de conf)

#### Pour les ajouts dans une page isolée 

1.  Editer la page en question et supprimer la partie indésirable, puis sauver. (Cela prend moins d'une minute)

Astuce: veiller à plusieurs à partir du flux RSS qui sort de votre wiki est plus efficace  

#### Pour de nouvelles pages indésirables créées

##### Si vous pouvez vous connecter en tant que WikiAdmin :  

1.  s'identifier en tant qu'administrateur du wiki (WikiAdmin par défaut)
2.  éditer les permissions de la page pour mettre le compte [WikiAdmin](https://yeswiki.net/?WikiAdmin) propriétaire de la page
3.  supprimer la page à partir du lien sur la barre d'action en bas de page

##### Si vous ne pouvez pas vous connecter en tant que WikiAdmin :**  

1.  éditer la page et remplacer tout le texte de spam par un caractère (il faut au moins un contenu autre qu'un espace dans la page pour la sauver (pour ma part j'utilise ".")


#### Pour limiter la création de nouveaux comptes

Pour éviter que des inconnus puissent se créer des comptes, vous pouvez limiter l'action [UserSettings](https://yeswiki.net/?UserSettings) aux seuls administrateurs.  
Si cela a l'avantage de bloquer/réserver la création de nouveaux comptes aux seuls admin,cela limite vraiment l'autonomie de vos utilisateurs.  

#### Pour supprimer les commentaires indésirables

1.  Ajouter l'action {{erasespamedcomments}} dans la page de votre choix. (Elle n'est accessible qu'aux administrateurs)
2.  Ensuite cocher les commentaires indésirables et appuyer sur le bouton "Nettoyer"

  

#### Pour supprimer de nombreuses pages rapidement

SI vous êtes connecté-e en tant qu'admin, il vous suffit de coller ceci à la fin de l'url des pages à supprimer : /deletepage&confirme=oui  
Cela vous évite toutes les étapes de validation, qui deviennent très fastidieuses lorsqu'on a plusieurs pages à supprimer. Attention, ce "raccourci" supprime définitivement la page sans message de confirmation, ne vous trompez donc pas !  
  

#### Pour les attaques massives sur de nombreuses pages

**cette technique nécessite des informations sur les codes FTP et Mysql**  
  
Pour faire le grand ménage avec le tools despam :  

1.  aller sur la barre d'adresse url de votre navigateur et remplacer wakka.php (et ce qu'il y a derrière) par tools.php (pour avoir une url du type http://monadressedewiki/tools.php )
2.  identifiez-vous à l'aide des **identifiants de la base de données Mysql** plutôt que vos identifiants wiki
3.  la liste des extensions apparaît, cliquer sur "Nettoyage Spam"
4.  Sélectionner l'intervalle de temps à prendre en compte pour les dernière modifications
5.  cocher les choix adéquats, entre supprimer la page ou revenir à la version précédente
    *   **ATTENTION, il faut IMPÉRATIVEMENT vérifier les pages en question pour ne pas supprimer définitivement le contenu!!**
6.  cliquer sur "Nettoyer"

#### Pour supprimer les utilisateurs non désirables (utilisateurs avancés, non disponible par défaut)

Ajouter par FTP, dans le répertoire tools l'extension suivante : [http://yeswiki.net/downloads/actions.zip](http://yeswiki.net/downloads/actions.zip)  
  
Pour la mise en oeuvre, voir la documentation suivante:  
[Télécharger le fichier doc\_action\_delete.pdf (0.7MB)](https://yeswiki.net/?LutterContreLeSpam/download&file=doc_action_delete.pdf)[](https://yeswiki.net/?LutterContreLeSpam/upload&file=doc_action_delete.pdf "Mise à jour")  

#### Activer l'extension Ipblock
Cette extension permet de bloquer l'accès à votre wiki en fontion des adresses IP (et de leur provenance géographique). 
Elle s'active via l'onglet Mise à jour / extension de la page gestion du site de votre wiki.
Les paramètres sont alors visibles dans la partie "Blocage d'adresses IP"

## Empêcher l'indexation de son wiki
Il faut agir sur le fichier robot.txt qui se trouve à la racine de votre wiki.  
  
Editez ce fichier et remplacez  
`User-agent: \*`
par  
`User-Agent: \*    
Disallow: /`

ATTENTION Pour une efficacité réelle (étant donné que google ne respecte plus trop le robots.txt,, il convient de rajouter dans wakka.config.php, cette ligne  

`'meta' => array('robots' => 'noindex, nofollow'),`

Vous pouvez aussi réaliser cette opération via l'onglet fichier de conf de la page gestion du site de votre wiki (Balises meta pour l'indexation web).

## Mettre une herse sur son wiki 
Il est parfois nécessaire de protéger l'accès de tout un wiki (par exemple pour transformer tout un wiki en intranet).
En bref, lors de l'accès au wiki protégé, un popup s'ouvre et demande login et mot de passe. Une fois cette porte franchie, vous êtes sur un wiki que vous pouvez laisser en écriture ouverte à tous. Ce qui facilite pas mal la participation.

La procédure pour placer est une herse n'est pas propre à Yeswiki et dépend de votre serveur. Voici une ressource pour en savoir plus : https://ouvaton.coop/proteger-un-repertoire-par-htaccess-et-htpasswd/

## Migrer son wiki 
Il est possible de déplacer son wiki d'un serveur à un autre. 
Pour cela il vous faudra :  
*   obtenir des gestionnaires de votre wiki
    *   une copie des dossiers du wiki (qu'il faudra dézipper)
    *   une copie de la base de données (qu'il faudra dézipper pour avoir un fichier .sql)
*   avoir un espace d'hébergement chez un fournisseur 
    *   les codes d'accès pour vous connecter par FTP (afin de déposer les fichiers du wiki)
    *   le nom de votre base de données, l'utilisateur lié et le mot de passe d'accès.
*   un nom de domaine (c'est l'adresse url pour aller vers votre espace ex: www.monespace.be)


#### Importer la base de données
*   il faut trouver les accès à la base de données (voir les infos reçues par votre hébergeur)
    *   souvent on reçoit une adresse de type "phpmyadmin" qui, une fois cliquée, demande le nom de la db, l'utilisateur et le mot de passe
*   une fois connecté à votre base de données
    *   il faut chercher le bouton "importer" (dépend de chaque système)
    *   cliquer sur importer, aller chercher le fichier .sql reçu des gestionnaires de la ferme
    *   laisser la procédure suivre son cours et vous avertir que c'est ok
    *   quand c'est ok : 7 tables existent maintenant dans votre base de données (prefixe\_nature; prefixe\_pages,...)

#### Upload par FTP

*   Connectez-vous à votre espace personnel par FTP (filezilla par exemple)
*   Glissez et déposez vos fichiers wiki (reçu des gestionnaires) dans le dossier www ou web (le plus souvent) de votre hébergement

  
#### Mettre à jour le wakka config

*   une fois tous les fichiers et dossiers arrivés sur votre hébergement, cherchez le fichier nommé wakka config et ouvrez-le
*   il va falloir adapter quelques points et sauver ensuite
    *   l'adresse de la db (mysql\_host) : le plus souvent ça reste localhost mais parfois votre hébergeur vous donne une autre adresse
    *   le nom de votre db => mysql\_database
    *   le nom de l'utilisateur de la db => mysql\_user
    *   le mot de passe de la db => mysql\_password
    *   la base url => base\_url (laissez bien le /? à la fin ex : [https://www.monespace.be/?)](https://www.monespace.be/?))  

    `'mysql_host' => 'localhost', `
    `'mysql_database' => 'nomdevotredb',`
    `'mysql_user' => 'userdevotredb',`
    `'mysql_password' => 'motdepassedevotredb',`
    `'base_url' =>'mettreicivotrenomdedomaine/?',`
      
      
## Suivre la vie de son wiki 

### Via la page tableau de bord

Une page TableauDeBord accessible dans le menu "roue crantée". Il permet d'accéder aux  

*   derniers comptes utilisateurs créés
*   dernières pages modifiées
*   dernières pages commentées
*   un index de toutes les pages du Wiki

### Via la page DerniersChangements
Sur cette page, vous verrez toutes les pages modifiées du wiki.

### Via les flux rss du wiki
Plusieurs flux RSS sortent du wiki : 
 - L'ensemble des changements du wiki
     - ce flux est accessible via la page DerniersChangementsRSS/xml de votre wiki
 - les modifications de chacun des formulaires
      - ces flux sont accessibles via la page "base de données" de la roue crantée  

  
