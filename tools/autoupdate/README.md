# autoUpdate

Un tool pour automatiser la mise à jour de YesWiki.

L'action `{{update}}` permet d'afficher la version de YesWiki. Si
l'utilisateur connecté est un administrateur un bouton permet de lancer la mise
à jour du Wiki si une version plus récente est disponible.

Cette action accepte un paramètre `version` qui permet de spécifier lorsqu'on souhaite changer de version. Par exemple :

```
{{update version="doryphore"}}
```

Par défaut, le dépot est _<https://repository.yeswiki.net>_ il est possible d'en
définir un autre en spécifiant le paramètre **`yeswiki_repository`** dans le
fichier `wakka.config.php` du Wiki.
