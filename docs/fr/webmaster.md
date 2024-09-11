# Installer un YesWiki

?> **Si vous avez déjà un yeswiki en ligne**, passez directement à la section [prise en main](/docs/fr/prise-en-main.md)

?> **Si vous n'avez pas d'hébergement web**, ni les connaissances techniques pour installer vous même YesWiki, vous pouvez aussi héberger votre wiki chez un hébergeur libre de confiance, comme [la ferme de l'association YesWiki](https://ferme.yeswiki.net), ou un collectif membre des [](https://chatons.org).

## Installation

### Installer son wiki par FTP

#### Pré-requis

- Vous avez téléchargé la dernière version de YesWiki sur le site [yeswiki.net](https://yeswiki.net/?TelechargemenT)
- Vous disposez d'un espace d'hébergement :
  - avec une version de `PHP` supérieure à 7.3 (8.2 recommandée)
  - une base de données SQL `MariaDB` > 10 ou `MYSQL` >= 5.6 (⚠️ la version 5.5 ne supporte pas la recherche fulltext)
  - des droits d'accès à l'hébergement (codes FTP et MYSQL)
  - un logiciel sur votre ordinateur pour faire du FTP (le client FTP libre [FileZilla](https://filezilla-project.org/) par exemple)

**Suppléments d'information :**

- [Voir les instructions spécifiques pour l'installation sur les hébergements Free.fr](https://yeswiki.net/?DocumentationInstallationFree)
- En cas de bug pour les mises à jour sur certains systèmes très légers, vérifiez la présence des librairies `php-curl`, `php-filter`, `php-gd`, `php-iconv`, `php-json`, `php-mbstring`, `php-mysqli`, `php-pcre` et `php-zip` ; la liste à jour des extensions est décrite dans [le fichier composer.json](https://github.com/YesWiki/yeswiki/blob/doryphore/composer.json).
- Les extensions peuvent nécessiter des librairies supplémentaires. Vérifiez le contenu du fichier README.md de chacune (ex.: [ferme](https://github.com/YesWiki/yeswiki-extension-ferme/blob/doryphore/README.md), [lms](https://github.com/YesWiki/yeswiki-extension-lms/blob/doryphore/README.md)).

#### Préparation

- Décompressez le fichier téléchargé sur votre disque dur et renommez-le à votre convenance (par exemple monYeswiki)
- Si elle n'existe pas déjà, créez une base de données vide sur votre espace d'hébergement (par exemple mabase).
- Le plus souvent, il vous faudra créer un utilisateur pour cette base de donnée avec un identifiant et un mot de passe (par exemple identifiant = moi, mot de passe = oups)
- Dans la mesure du possible, évitez les identifiants avec "tiret" (genre moi-moi) car cela crée parfois une erreur lors de l'installation
- Notez le nom de votre base de données et les identifiants et mot de passe d'accès à celle-ci.

#### Téléversement par FTP

- Connectez-vous à votre espace personnel par FTP (filezilla par exemple)
- Glissez et déposez votre dossier local (monYeswiki) sur votre espace personnel
- Sur certains hébergements, il faut attribuer des droits d'accès en écriture au dossier principal du wiki (monYeswiki) : mettre les droits d'accès en écriture pour tous (chmod 777), en faisant clic droit sur le dossier puis "droits d'accès au dossier" dans filezilla)
- Pour éviter des erreurs futures lors de l'insertion d'images dans le wiki par exemple, il peut être judicieux d'attribuer dores et déjà au dossier "Cache" et "File" des droits d'accès en écriture pour tous (777)
- Fermez le client Ftp. Nous allons pouvoir configurer le yeswiki.
<iframe sandbox="allow-same-origin allow-scripts" src="https://video.coop.tools/videos/embed/70cb02cf-606e-417d-8458-f1f265c0d3c8" allowfullscreen="" tc-textcontent="true" data-tc-id="w-0.010567584623263016" width="100%" height="315" frameborder="0"></iframe>

#### Paramétrage du YesWiki

- Ouvrez votre navigateur et tapez l'url de votre site perso jusqu'au répertoire créé.
- Une page de configuration s'ouvre.
- Renseignez le nom de votre serveur MySQL (donné par votre hébergeur, en général c'est "localhost")
- Renseignez le nom de votre base de données MySQL (dans notre exemple : mabase)
- Renseignez le nom d'utilisateur et le mot de passe de votre base de données MySQL (dans notre exemple : moi et oups)
- Renseignez le préfixe des tables : par défaut yeswiki\_ (vous pouvez laisser comme cela)
- Modifiez le nom de votre YesWiki (cela deviendra le titre de votre wiki affiché en grand en haut du site... modifiable par après si nécessaire - ne pas mettre de caractères spéciaux html, typiquement "&Eacute", "&Egrave"; )
- Renseignez la page d'accueil de votre YesWiki. Ceci doit être un [NomWiki](https://yeswiki.net/?NomWiki). : par défaut [PagePrincipale](https://yeswiki.net/?PagePrincipale) (vous pouvez laisser comme cela)
- Renseignez les champs mots-clés et description si vous le souhaitez (si vous laissez vide, cela ne posera pas de problème)
- Renseignez les champs (Administrateur, Mot de passe, Confirmation du mot de passe et Adresse e-mail) afin de créer un compte administrateur pour gérer votre wiki
- Les pages par défaut de YesWiki nécessitent du code html pour fonctionner **cocher la case "Autoriser l'insertion de HTML brut."**. Vous pourrez alors plus facilement intégrer des widgets HTML.

Les tables MySQL sont automatiquement créées.  
Si tout a bien fonctionné, vous en avez confirmation.  
Le fichier de configuration est écrit. C'est terminé.

<iframe sandbox="allow-same-origin allow-scripts" src="https://video.coop.tools/videos/embed/30cc117a-db65-4539-8547-749f52008212" allowfullscreen="" tc-textcontent="true" data-tc-id="w-0.9930878636773818" width="100%" height="315" frameborder="0"></iframe>

### Autres méthodes d'installation

Si vous souhaitez installer avec Docker ou avoir des informations plus techniques, rendez-vous dans la [rubrique Développement](/docs/fr/dev.md#installer-yeswiki-dans-un-environnement-de-développement-local).

## Sécuriser YesWiki

### Empêcher l'indexation de son wiki

Il faut agir sur le fichier robot.txt qui se trouve à la racine de votre wiki.

Editez ce fichier et remplacez  
`User-agent: \*`
par  
`User-Agent: \*    
Disallow: /`

ATTENTION - Pour une efficacité réelle (étant donné que google ne respecte plus trop le robot.txt, il convient de rajouter dans wakka.config.php, cette ligne :

`'meta' => array('robots' => 'noindex, nofollow'),`

Vous pouvez aussi réaliser cette opération via l'onglet fichier de conf de la page gestion du site de votre wiki (Balises meta pour l'indexation web).

### Protéger le dossier private

Il existe un dossier `private` pouvant contenir des backups et **des fichiers ne devant pas être accessible depuis une adresse url publique**.  
Par défaut, si votre hébergement ou serveur web utilise Apache et autorise les fichiers `.htaccess`, ce dossier est déjà protégé.  
Si vous utilisez Apache mais que votre dossier `private` est tout de même accessible, il vous faudrait rajouter la directive `AllowOverride All` dans votre configuration par default d'Apache.

Pour des serveurs nginx, il va falloir rajouter une ligne dans la configuration de votre site:

```nginx
location ~* /(.*/)?private/ {
    deny all;
    return 403;
}
```

!> Attention : tous les dossiers `private` n'importe où dans l'arborescence seront inaccessibles publiquement.

### Mettre une herse sur son wiki

Il est parfois nécessaire de protéger l'accès de tout un wiki (par exemple pour transformer tout un wiki en intranet).
En bref, lors de l'accès au wiki protégé, un pop-up s'ouvre et demande les login et mot de passe. Une fois cette porte franchie, vous êtes sur un wiki que vous pouvez laisser en écriture ouverte à tous. Ce qui facilite considérablement la contribution de chacun.

La procédure pour placer une herse n'est pas propre à Yeswiki et dépend de votre serveur. Voici une ressource pour en savoir plus : https://ouvaton.coop/proteger-un-repertoire-par-htaccess-et-htpasswd/

## Personnaliser YesWiki

### Ajouter les extensions

YesWiki peut s'enrichir en fonctionnalités en activant des extensions. Pour chaque extension, une documentation est proposée.
Les extensions s'activent via la page gestion du site (onglet mise à jour / extensions) accessible via la roue crantée.
Il faut être connecté et membre du groupe admin pour pouvoir agir sur cette page.

Cette interface d'administration permet :

- d'installer des extensions
- de mettre à jour des extensions si une nouvelle version est disponible
- de désinstaller des extensions

### Le fichier de configuration

Une fois le YesWiki créé / installé, on peut aller éditer le fichier **wakka.config.php**, se trouvant à la racine du dossier du YesWiki, accessible par FTP. Le fichier de configuration déclare un tableau avec des valeurs pour chaque élément de configuration.

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

#### Changer les droits de lecture et d'écriture par défaut

Par défaut, les pages de votre wiki sont visibles et éditables par tout visiteur. Pour limiter la lecture et l'écriture aux seuls administrateurices par exemple, il faut changer les lignes

      'default_write_acl' => '*', // droits d'écriture par défaut des pages
      'default_read_acl' => '*', // droits de lecture par défaut des pages

en

      'default_write_acl' => '@admins', // droits d'écriture par défaut des pages
      'default_read_acl' => '@admins', // droits de lecture par défaut des pages

#### Changer l'url de votre wiki

Mon wiki se trouve à l'adresse suivante [http://site-coop.net/Louise,](http://site-coop.net/Louise,) je souhaiterais qu'il se nomme maintenant [http://site-coop.net/Mathieu](http://site-coop.net/Mathieu)  
\- Via ftp, il faut changer le nom du dossier Louise en le nommant Mathieu  
\- dans le fichier wakka.config.php, il faut changer la ligne

    'base_url' => 'http://site-coop.net/Louise/?"

en

    'base_url' => 'http://site-coop.net/Mathieu/?"

et tester si tout fonctionne dans votre navigateur (attention les majuscules et minuscules ont leur importance)

Pour tous les détails sur les droits d'accès : [https://yeswiki.net/?DocumentationDroitsDAcces](https://yeswiki.net/?DocumentationDroitsDAcces)

##### Envoyer un mail aux @admins à chaque nouvel ajout de fiche

    'BAZ_ENVOI_MAIL_ADMIN' => true

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

---

##### Facebook opengraph : choisir l'image utilisée quand on partage un wiki sur FB

Pour ouvrir une image par défaut : il faut mettre un lien vers l'image dans le wakka.config.php  
Idéalement, l'image doit faire 1200x630 selon les specs imposées par facedebouc  
Par défaut il prend cette image, et si une image est présente dans la page (mise avec attach) ou une fiche bazar avec bf_image , il remplace par cela

    'opengraph_image' => 'files/nomdelimage.jpg',

##### Flux Rss : modifier le nombre d'item
Par défaut les flux RSS sont limités aux 20 dernieres pages, cette valeur peut être modifiée :

    'BAZ_NB_ENTREES_FLUX_RSS' => 30,
    
## Maintenance

### Mettre à jour YesWiki

La mise à jour du wiki s'effectue via la page gestion du site (onglet mise à jour / extensions) accessible via la roue crantée.
Il faut être connecté et membre du groupe admin pour pouvoir agir sur cette page.
Sur celle-ci, vous trouvez :

- La version du Wiki : doryphore XXX
- La version disponible sur le dépot : doryphore YYY
- et un bouton permettant d'installer la nouvelle version si les deux versions diffèrent ou de réinstaller la version actuelle.
- Notes de versions : un lien qui vous emmène vers le changelog de la nouvelle version (présentation des ajouts de cette version)

Sur cette page, vous pouvez aussi installer, mettre à jour ou désinstaller les thèmes ou extensions de votre wiki.
Un message d'alerte vous informe si une nouvelle version d'un thème ou d'une extension existe.

### Lutter contre le spam

[voir la rubrique dédiée](/docs/fr/admin?id=lutter-contre-le-spams)

### Réparer les wikis qui n'envoient pas les mails

Sur certains hébergements, l'envoi de mail par défaut ne marche pas , il faut créer un compte smtp  
et donc rajouter dans le fichier `wakka.config.php` les paramètres suivants :

```php
'contact_mail_func' => 'smtp',
'contact_smtp_host' => 'ssl://<mon serveur smtp>:465',
'contact_smtp_user' => 'user@mail.ext',
'contact_smtp_pass' => '<monpassword>',
```

Attention, tous les serveurs mail n'acceptent pas de jouer ce jeu.

#### avec sendinblue

Créez-vous un compte puis allez dans les paramètres (via [ce lien](https://account.sendinblue.com/advanced/api)) chercher votre clé smtp (limitée à 300 mails par jour)

```php
'contact_mail_func' => 'smtp',
'contact_smtp_host' => 'smtp-relay.sendinblue.com:587',
'contact_smtp_user' => 'monmail@pourmoncomptesendinblue.com',
'contact_smtp_pass' => '<ma cle smtp>',
```

ou

```php
'contact_mail_func' => 'smtp',
'contact_smtp_host' => 'smtp-relay.sendinblue.com',
'contact_smtp_port' => '587',
'contact_smtp_user' => 'monmail@pourmoncomptesendinblue.com',
'contact_smtp_pass' => '<ma cle smtp>',
```

#### avec gmail

Gmail le fait mais avec une limite d'envoi journalière et souvent un blocage de sécurité à lever via un paramètre : plus d'infos ici  
[https://support.google.com/accounts/answer/6010255](https://support.google.com/accounts/answer/6010255)

```php
'contact_smtp_host' =>     'ssl://smtp.gmail.com:465',
```

Autre piste possible, achetez un nom de domaine chez gandi et utilisez le smtp lié.

#### en cas de soucis avec votre fournisseur SMTP de mail

Il est parfois nécessaire de contacter l'organisation qui vous fournit l'accès mail pour demander comment remplir cette configuration. Sinon, aller voir du coté de la base d'erreur de la librairie utilisée : [la doc de phpMailer](https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting).

### Migrer son wiki

Il est possible de déplacer son wiki d'un serveur à un autre.
Pour cela, il vous faudra :

- obtenir des gestionnaires de votre wiki
  - une copie des dossiers du wiki (qu'il faudra dézipper)
  - une copie de la base de données (qu'il faudra dézipper pour avoir un fichier .sql)
- avoir un espace d'hébergement chez un fournisseur
  - les codes d'accès pour vous connecter par FTP (afin de déposer les fichiers du wiki)
  - le nom de votre base de données, l'utilisateur lié et le mot de passe d'accès.
- un nom de domaine (c'est l'adresse url pour aller vers votre espace ex: www.monespace.be)

#### Importer la base de données

- il faut trouver les accès à la base de données (se référer aux infos reçues de votre hébergeur)
  - souvent, on reçoit une adresse de type "phpmyadmin" qui, une fois cliquée, demande le nom de la db (data base), l'utilisateur et le mot de passe
- une fois connecté à votre base de données
  - il faut chercher le bouton "importer" (dépend de chaque système)
  - cliquer sur importer, aller chercher le fichier .sql reçu de votre hébergeur
  - laisser la procédure suivre son cours et vous avertir que c'est ok
  - quand c'est ok : 7 tables existent maintenant dans votre base de données (prefixe_nature; prefixe_pages,...)

#### Upload par FTP

Connectez-vous à votre espace personnel par FTP (filezilla par exemple)

Glissez et déposez vos fichiers wiki (reçu des gestionnaires) dans le dossier www ou web (le plus souvent) de votre hébergement

#### Mettre à jour le wakka config

Une fois tous les fichiers et dossiers arrivés sur votre hébergement, cherchez le fichier nommé wakka config et ouvrez-le.

Il va falloir adapter quelques points puis sauver

- l'adresse de la db (mysql_host) : le plus souvent ça reste localhost mais parfois votre hébergeur vous donne une autre adresse
- le nom de votre db => mysql_database
- le nom de l'utilisateur de la db => mysql_user
- le mot de passe de la db => mysql_password
- la base url => base_url (laissez bien le /? à la fin ex : [https://www.monespace.be/?)](https://www.monespace.be/?))

```
'mysql_host' => 'localhost',
'mysql_database' => 'nomdevotredb',
'mysql_user' => 'userdevotredb',
'mysql_password' => 'motdepassedevotredb',
'base_url' =>'mettreicivotrenomdedomaine/?',
```

### Réparer la structure de vos bases de données

_Lorsque la structure de vos bases de données n'est pas correcte, des soucis peuvent survenir en particulier lors de la création ou la modification des listes._

1.  tenter de forcer la finalisation d'une mise à jour avec le handler `/update` (accessible avec [ce lien](?GererMisesAJour/update 'Forcer la finalisation de la mise à jour :ignore'))
2.  si ça ne fonctionne pas:
    1. se rendre dans l'interface de gestion de base de données du serveur concerné (`phpmyadmin`)
    2. ouvrir en même temps le fichier `setup/sql/create-tables.sql` depuis votre wiki ([fichier à télécharger](setup/sql/create-tables.sql ':ignore'))
    3. vérifier dans la structure de chaque table de votre serveur (`phpmyadmin`) que chaque colonne est correctement définie.
    4. puis, dans cette ordre, modifier la colonne qui doit être en `AUTOINCREMENT` pour avoir `A.I.` cochée. Normalement, ceci corrige les soucis d'index pour la table concernée et définie la colonne comme primaire.
    5. puis, pour les index (`KEY` dans le fichier `create-tables.sql`), définir manuellement chaque index pour la table concernée
    6. vérifier que l'affichage et la modification des fiches fonctionnes à nouveau

**Important** : il est vivement conseillé de faire une sauvegarde de votre base de données avant de faire les manipulations.
