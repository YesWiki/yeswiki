# Personnalisation avancées

?> Si vous cherchez la **documentation du code** de YesWiki, [c'est par ici](/docs/code/README.md)

!> Des bases de programmation vous seront nécessaire pour suivre ces étapes

## Installer yeswiki dans un environnement de développement local

### Sur des machines linux base Debian

### Par docker

Les informations détaillées sont disponibles dans le [README.md du dossier docker](../../docker/README.md).

En résumé depuis la racine du code source de YesWiki :
`cd docker && UID=$(id -u) && GID=$(id -g) && docker compose build && docker compose up`

Si tout s'est bien passé vous devriez pouvoir accèder a la post-installation de YesWiki sur <http://localhost:8085>

## Utilisation du dossier `custom`

<!-- ### Créer un champ bazar custom

_[Documentation originelle sur le site yeswiki.net](https://yeswiki.net/?TutorielCreerUnChampBazarCustom "Tutoriel - Créer un champ bazar custom")_

 1. choisir dans le dossier `tools/bazar/fields`, un champ modèle qui ressemble au champ personnalisé (par exemple `DateField.php`)
 2. Le copier dans le dossier `custom/fields`
    - conserver son nom si on veut remplacer le champ d'origine
    - le renommer si on ne veut pas remplacer le champ d'origine (veuillez à renommer le nom de la classe dans le fichier en remplaçant `class DateField ` par `class NomFichier`)
 3. remplacer `namespace YesWiki\Bazar\Field;` dans le fichier par `namespace YesWiki\Custom\Field;`
 4. paramétrer l'héritage :
    - si on garde `extends BazarField`, bien s'assurer qu'il y a `use YesWiki\Bazar\Field\BazarField;` dans le fichier
    - si on veut hériter d'une autre classe écrire `extends OtherField` et bien s'assurer que le fichier possède ceci : `use YesWiki\Bazar\Field\OtherField;`
    - enfin, lors du remplacement d'un champ du cœur, il est conseillé de faire un héritage à partir du champ d'origine. Dans notre exemple ça donnerait :


    use YesWiki\Bazar\Field\DateField as CoreDateField;

    class DateField extends CoreDateField
    {
        // ....
 5. mettre à jour la déclaration du nom de champ associé dans l'en-tête `@Field({"nompossible1", "nompossible2", "nompossible3"})`
 6. une fois le code prêt, il est alors possible de définir un champ custom dans le constructeur graphique de formulaire.

### Ajouter un nouveau composant à `actions-builder`

 1. choisir un composant dans le dossier `tools/aceditor/presentation/javascripts/components` qui ressemble au composant personnalisé recherché (par exemple `InputGeo.js`)
 2. Le copier dans le dossier `custom/javascripts/components/actions-builder`
    - conserver son nom si on veut remplacer le champ d'origine
    - le renommer si on ne veut pas remplacer le champ d'origine
 3. faire attention à mettre à jour les chemins relatifs pour les imports (exemple : `import InputMultiInput from '../../../../tools/aceditor/presentation/javascripts/components/InputMultiInput.js'`)
 4. pour l'utiliser, définir dans le fichier `.yaml` associé à actions builder pour la partie voulue le type qui va bien.
   - si le fichier s'appelle `InputNomInput.js`, alors il faut taper dans le fichier `.yaml` : `type: nom-input`
   - si le fichier s'appelle `InputGeo.js`, il faut taper `type: geo` -->

## Rendre votre wiki sémantique

Une documentation détaillée en Français est disponible sur cette page [docs/fr/semantic](semantic)

## Créer un widget bazar

_[Documentation originelle sur le site yeswiki.net](https://yeswiki.net/?BazarWidget 'Tutoriel - Créer widget bazar')_

## Créer un environnement de travail en local

_[Documentation originelle sur le site yeswiki.net](https://yeswiki.net/?PageConfiglocal 'Tutoriel - Créer un environnement de dév en local')_

## Astuces pour faire de belles fusions de branches `git`

### Contexte

Les sources du logiciel YesWiki sont actuellement disponibles sur GitHub : https://github.com/YesWiki/yeswiki/. Lors de la fusion de la validation d'une [`pull-request`](https://github.com/YesWiki/yeswiki/pulls), le logiciel en ligne `github` propose trois modes de fusions : `rebase and merge`, `squash and merge` et `create a merge commit`. Aucun de ces trois modes ne permet d'obtenir un bel arbre des commits.

En effet,

- `rebase and merge` va réécrire les commits de la branche concernée à la fin de la branche cible, ce qui peut faire disparaître l'historique des fusions des sous-branches ;
- `squash and merge` va fusionner tous les commits en un seul ne laissant pas de trace de l'historique des commits ;
- `create a merge commit` va créer un nouveau `commit` sans `rebase` ce qui peut créer des entremelements de branches qui rendent leur inspection plus compliquée.

La solution qui correspond aux usages de la communauté des développeurs YesWiki doit se faire en local.

### Procédure

1.  avoir une copie en local du dépôt `git` (cf. [Créer un environnement de travail en local](#cr%c3%a9er-un-environnement-de-travail-en-local))
2.  attendre la validation de la `pull-request` concernée
3.  identifier la branche source ; exemple : `fix/new-fix`
4.  identifier la branche cible ; exemple : `doryphore-dev`
5.  importer en local toutes les modifications depuis le dépôt [`git fetch --all`](https://git-scm.com/docs/git-fetch)
6.  vérifier qu'il n'y a pas de modifications en cours à l'aide de [`git status`](https://git-scm.com/docs/git-status)
    - s'il y a des modifications en cours, il est possible de les mettre en attente avec [`git stash`](https://git-scm.com/docs/git-stash)
    - ou de les sauvegarder dans un nouveau commit avec [`git commit ...`](https://git-scm.com/docs/git-commit)
7.  une fois qu'il n'y a plus de modifications en cours, basculer sur la branche cible [`git checkout doryphore-dev`](https://www.git-scm.com/docs/git-checkout)
8.  vérifier l'état de la branche cible (`doryphore-dev`) en local `git status`
    - si la branche a des commits en retard et aucun commit en avance : [`git pull`](https://www.git-scm.com/docs/git-pull)
    - s'il y a des commits en avance et qu'il faut les ajouter au dépôt : `git pull -r` suivi de [`git push`](https://www.git-scm.com/docs/git-push)
    - si les commits en avance doivent être jetés : [`git reset --hard origin/doryphore-dev`](https://www.git-scm.com/docs/git-reset)
9.  une fois que la branche cible est à jour en local, on bascule sur la branche source en local : [`git checkout fix/new-fix`](https://www.git-scm.com/docs/git-checkout)
10. vérifier l'état de la branche source (`fix/new-fix`) en local `git status`

    - si la branche a des commits en retard et aucun commit en avance : [`git pull`](https://www.git-scm.com/docs/git-pull)
    - s'il y a des commits en avance et qu'il faut les ajouter à la PR : `git pull -r` suivi de [`git push`](https://www.git-scm.com/docs/git-push), puis attendre la validation de ces commits dans la PR et recommencer la procédure
    - si les commits en avance doivent être jetés : [`git reset --hard origin/fix/new-fix`](https://www.git-scm.com/docs/git-reset)

11. une fois que la branche source est à jour en local, on va la réécrire à la fin de la branche cible en gardant l'historique des fusions des sous-branches : [`git rebase -r doryphore-dev`](https://www.git-scm.com/docs/git-rebase) (le `-r` est important pour garder l'historique des fusions des sous-branches)

- il se peut qu'il y ait des conflits de fusion qu'il faut résoudre à la main

12. une fois le `rebase` terminé, il faut mettre à jour le dépôt distant pour que la `pull-request` puisse suivre les modifications : [`git push --force-with-lease`](https://www.git-scm.com/docs/git-push)
13. s'il n'y a pas eu d'erreur, alors on revient sur la branche cible en local `git checkout doryphore-dev`
14. ensuite, on réalise la fusion [`git merge --no-ff`](https://www.git-scm.com/docs/git-merge)

- `--no-ff` est important pour correspondre à la volonté de la communauté de créer une "branche" le long de la branche principale pour identifier plus facilement à quelle fonctionnalité appartient chaque commit.

15. une fois la fusion réalisée correctement en local, il suffit d'envoyer les modifications vers le dépôt distant `git push` (normalement, il ne faut pas faire de `--force` ou `--force-with-lease` dans ce cas)
16. vérifier que la `pull-request` est automatiquement fermée et marquée comme fusionnée sur [`github`](https://github.com/YesWiki/yeswiki/pulls)
17. faire un nouveau `git fetch --all` pour mettre à jour les modifications en local. Normalement, la branche source distante devrait maintenant être supprimée. il suffit alors de supprimer la dite branche en local `git branch -D fix/new-fix`

**Il est possible de configurer son [EDI](https://fr.wikipedia.org/wiki/Environnement_de_d%C3%A9veloppement) préféré pour réaliser toutes ces opérations de façon automatique.**

Pour fusionner la branche `doryphore-dev` dans la branche `doryphore`, il suffit d'appliquer la même procédure sans adaptation mais en considérant cette fois-ci que la branche source est `doryphore-dev` et la branche cible `doryphore`.

## Créer une extension YesWiki

Le code créé pour de nouvelles fonctionnalités peut être proposé à la communauté de deux manières.

1. en faisant une Pull-Request (PR) dans le projet du cœur de YesWiki : https://github.com/YesWiki/yeswiki/pulls
   - ceci est réservé pour les fonctionnalités qui ont été validées par la communauté pour leur intérêt à intégrer le cœur.
2. ou en créant une extension dédié qui peut être ajouter ou retirer facilement. L'ajout d'une extension est plus souple et peut-être faire par les développeurs principaux de YesWiki, avec une réserve a posteriori pour demander le renommage ou le retrait de l'extension s'il y avait des problèmes.

### Créer une extension YesWiki

1.  en ayant les droits pour créer un dossier au sein de l'organisation https://github.com/YesWiki, créer un `repository` qui doit s'appeler `yeswiki-extension-nomextension`, où `nomextension` est remplacé par le nom de l'extension en minuscule, sans caractères spéciaux.
2.  déposer vos fichiers dans ce dépôt
3.  pensez à y inclure un fichier `LICENE` (normalement AGPL 3.0 vu que `YesWiki` suit cette licence) et un fichier `README.md`
4.  modifier le fichier de description de la configuration du dépôt : https://github.com/YesWiki/yeswiki-build-repo/blob/master/repo.config.json
    - ajouter la nouvelle extension dans chaque version `YesWiki` qui peut la supporter en s'inspirant du modèle des autres extensions
    - bien penser à mettre à jour le lien de la documentation vers le fichier `README.md` de l'extension ou sinon une page d'internet de documentation
5.  se rendre dans le dossier `repository-api` de `YesWiki` avec sa clé `SSH` (clé fournie uniquement aux développeurs autorisés)
6.  mettre à jour le fichier local `repo.config.json` avec normalement la commande `git pull` (attention, la commande n'est ici qu'une indication)
7.  lancer la mise à jour du dépôt
8.  prévenir la communauté des développeurs de la création de cette extension sur le canal Framateam : https://framateam.org/yeswiki/channels/developpement
    - la communauté `YesWiki` peut alors demander un renommage de l'extension, il faudra alors :
      - créer une nouvelle extension avec le nouveau nom
      - recopier le code
      - supprimer l'extension actuelle

#### Ajouter un `webhook` pour les dossiers qui ne sont pas dans `https://github.com/YesWiki`

_En effet, les dossiers qui sont dans https://github.com/YesWiki ont un `webhook` automatique parce que le dossier est dans cette organisation._

1.  revenir dans le dossier `https://github.com/YesWiki/yeswiki-extension-nomextension` (adresse exacte à mettre à jour)
2.  cliquer sur `Settings`
3.  dans la barre latérale gauche, cliquer sur `Webhooks`, puis `Add webhook` (le lien ressemble à `https://github.com/YesWiki/yeswiki-extension-nomextension/settings/hooks/new`)
4.  compléter le formulaire de cette manière
    - **Payload URL** : url de `repository-api`
    - **Content type** : application/json
    - **Secret** : le mot de passe secret GitHub stocké dans le fichier de config du serveur `repository-api`
    - choisir **just the `push` event**

### Supprimer une extension

1.  supprimer le dossier concerné de `GitHub` (**action définitive**)
2.  se rendre sur https://github.com/YesWiki/yeswiki-build-repo/blob/master/repo.config.json et retirer les références à cette extension
3.  se rendre avec sa clé `SSH` sur `repository-api` pour mettre à jour le fichier `repo.config.json` grâce à `git pull`
4.  se rendre dans le sous-dossier de `repository.yeswiki.net` pour supprimer les fichiers `.zip` de l'extension et ainsi retirer les archives accessibles depuis internet
5.  pour chaque version de `YesWiki`, se rendre dans le sous-dossier concerné pour retirer les références à cette extension du fichier `package.json`
6.  prévenir la communauté sur le canal Framateam : https://framateam.org/yeswiki/channels/developpement
    tps://framateam.org/yeswiki/channels/developpement

### Créer une route d'API custom

[Documentation sur le forum yeswiki.net](https://forum.yeswiki.net/t/fonctionnement-des-api/116/3?u=agate)

### Restreinte l'accès à une API de base ou custom

Pré-requis : Authoriser les headers d'authentification dans [ngninx](https://stackoverflow.com/a/65308098) ou via un [.htaccess](https://stackoverflow.com/a/26791450) si vous utilisez un [`reverse-proxy`](https://fr.wikipedia.org/wiki/Proxy_inverse)

Pour se connecter à une route api avec un `bearer`, il faut:

1.  ajouter le paramètre suivant dans le fichier wakka.config.php

```php
   'api_allowed_keys' => [
      'UserName1' => 'a-complex-token-1',
      'UserName2' => 'a-complex-token-2',
   ],
```

2.  placer les utilisateurs `UserName1` et `UserName2` dans des groupes avec les accès souhaités
3.  faire un appel sur la route api avec l’en-tête HTTP `Authorization: Bearer a-complex-token-1`  
    Ceci connectera automatiquement l’utilisateur concerné et permettra l'accès au données de l'api si l'utilisater en question y a accès.

Une autre méthode est d’appeler la route concernée avec les bons cookies. Par exemple,

1.  se connecter via une requête POST sur une page de connexion [`/?ParametresUtilisateur`](?ParametresUtilisateur ':ignore') avec `name=UserName&password=real-password&action=login` en requête `POST`
2.  puis faire une requête api dans le même contexte (les cookies devraient être envoyés automatiquement permettant de maintenir la connexion).

!> **attention** : cette méthode d'authentification à l'api par les cookies risques de ne pas être stable au fil des versions et une nouvelle méthode d'authentification pourrait être mise en place.

Une documentation technique sur l'api en Anglais est disponible dans ce fichier : [docs/code/api.md](/docs/code/api.md)
