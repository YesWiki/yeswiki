Structure d'un dépot YesWiki
============================

La racine
---------

La racine du dépot contient deux dossiers par branche de YesWiki supporté et un
pour la version en cours de developpement.
Les noms des branches sont écrit en minuscule.
Les branches *-test sont destinée à recevoir les pré-version des corrections de
bug/faille.

Des liens symboliques peuvent être ajouté. Par exemple :

* 'stable' pour toujours pointer vers la version actuelle
* 'nightly' pour pointer vers la version de développement

```text
/
├── cercopitheque
├── cercopitheque-test
├── doriphore
├── doriphore-test
├── nightly
└── stable
```

Les branches
------------

Chaque branche contient un fichier 'packages.json' qui contient le descriptif
des versions en cours de chaque paquet. Ainsi que les fichiers des paquets
disponibles et leur somme MD5.

```test
/
├── cercopitheque
│   ├── packages.json
│   ├── theme-bootstrap5-2016-01-01.zip
│   ├── theme-bootstrap5-2016-01-01.zip.md5
│   ├── yeswiki-2016-01-01.zip
│   ├── yeswiki-2016-01-01.zip.md5
│   ├── tool-attachng-2016-01-01.zip
│   └── tool-attachng-2016-01-01.zip.md5
```

Le fichier 'packages.json'
-----------------------

Fichier au format json qui représente l'index du dépot. Il contient une entrée
par paquet.

Chaque entrée doit préciser le numéro de version et le fichier correspondant au
paquet dans cette version.

```json
{
    "yeswiki": {
        "version": "2016.01.01",
        "file": "yeswiki-2016-01-01.zip",
    },
    "tool-truc": {
        "version": "2016.01.01",
        "file": "tool-truc-2016-01-01.zip",
    },
    "theme-truc": {
        "version": "2016.01.01",
        "file": "theme-truc-2016-01-01-zip",
}
```

Les paquets
-----------

Archive au format zip contenant un repertoire avec les fichiers mis à jour. Le
nom du ficher suit la nomenclature : \[tool-\|theme-\]nom-AAAA-MM-JJ-N.zip

* AAAA : représente l'année sur quatres chiffres.
* MM : représente le mois sur deux chiffres (avec les zéros)
* JJ : représente le jour sur deux chiffres (avec les zéros)

 Le nom doit être en camelCase (lowerCamelCase).

Il y a trois types de paquet :

* Le paquet pour un thème, préfixé par 'theme-' et dont les fichiers seront
placé dans le dossier /themes après avoir effacer le fichiers/dossiers présent
dans l'archive et dans le dossier themes.
* Le paquet pour un thème, préfixé par 'tool-' et dont les fichiers seront
placé dans le dossier /tools après avoir effacer le fichiers/dossiers présent
dans l'archive et dans le dossier tools.
* la paquet du coeur de YesWiki dont les fichiers sont placé à la racine du
wiki. il efface tous les fichiers/dossiers présent à la racine de l'archive et
a la racine du site (a l'exception des dossiers themes, cache et files qui ne
seront jamais supprimer/remplacé). Le dossier tools reçoit un traitement
particulier et seul les tools present dans l'archive sont effacer du wiki avant
d'etre remplacer par les nouvelles versions. (Mettre un dossier vide au nom du
tools permet de le supprimer).
u
Exemple pour tool-monTool-2016-01-01-1.zip :

 ```bash
 /
 ├── MonTool
 │   ├── actions
 │   │   └── montool.php
 ├── wiki.php
 └── desc.xml
 ```

Exemple pour yeswiki-2016-01-01-1.zip :

 ```bash
 /
 ├── actions
 ├── COPYING
 ├── formatters
 ├── handlers
 ├── includes
 ├── index.php
 ├── INSTALL
 ├── interwiki.conf
 ├── lang
 ├── LICENSE
 ├── README.md
 ├── robots.txt
 ├── setup
 ├── tools
 ├── tools.php
 ├── wakka.basic.css
 ├── wakka.config.php
 ├── wakka.css
 └── wakka.php
 ```
