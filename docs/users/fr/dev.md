# Personnalisation avancées

?> Si vous cherchez la **documentation du code** de YesWiki, [c'est par ici](/docs/code/README.md)

!> Des bases de programmation vous seront nécessaire pour suivre ces étapes

## Installer yeswiki dans un environnement de développement local
### Sur des machines linux base Debian
### Par docker

## Utilisation du dossier `custom`

[filename](../en/custom-folder.md ':include')

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

_[Documentation originelle sur le site yeswiki.net](https://yeswiki.net/?RendreYeswikiSemantique "Tutoriel - Rendre votre wiki sémantique")_

## Créer un widget bazar

_[Documentation originelle sur le site yeswiki.net](https://yeswiki.net/?BazarWidget "Tutoriel - Créer widget bazar")_

## Créer un environnement de travail en local

_[Documentation originelle sur le site yeswiki.net](https://yeswiki.net/?PageConfiglocal "Tutoriel - Créer un environnement de dév en local")_

## Créer une extension YesWiki

Le code créé pour de nouvelles fonctionnalités peut être proposé à la communauté de deux manières.

  1. en faisant une Pull-Request (PR) dans le projet du cœur de YesWiki : https://github.com/YesWiki/yeswiki/pulls
     - ceci est réservé pour les fonctionnalités qui ont été validées par la communauté pour leur intérêt à intégrer le cœur.
  2. ou en créant une extension dédié qui peut être ajouter ou retirer facilement. L'ajout d'une extension est plus souple et peut-être faire par les développeurs principaux de YesWiki, avec une réserve a posteriori pour demander le renommage ou le retrait de l'extension s'il y avait des problèmes.

### Créer une extension YesWiki

 1. en ayant les droits pour créer un dossier au sein de l'organisation https://github.com/YesWiki, créer un `repository` qui doit s'appeler `yeswiki-extension-nomextension`, où `nomextension` est remplacé par le nom de l'extension en minuscule, sans caractères spéciaux.
 2. déposer vos fichiers dans ce dépôt
 3. pensez à y inclure un fichier `LICENE` (normalement AGPL 3.0 vu que `YesWiki` suit cette licence) et un fichier `README.md`
 4. modifier le fichier de description de la configuration du dépôt : https://github.com/YesWiki/yeswiki-build-repo/blob/master/repo.config.json
    - ajouter la nouvelle extension dans chaque version `YesWiki` qui peut la supporter en s'inspirant du modèle des autres extensions
    - bien penser à mettre à jour le lien de la documentation vers le fichier `README.md` de l'extension ou sinon une page d'internet de documentation
 5. se rendre dans le dossier `repository-api` de `YesWiki` avec sa clé `SSH` (clé fournie uniquement aux développeurs autorisés)
 6. mettre à jour le fichier local `repo.config.json` avec normalement la commande `git pull` (attention, la commande n'est ici qu'une indication)
 7. lancer la mise à jour du dépôt
 8. revenir dans le dossier `https://github.com/YesWiki/yeswiki-extension-nomextension`
 9. cliquer sur `Settings`
 10. dans la barre latérale gauche, cliquer sur `Webhooks`, puis `Add webhook` (le lien ressemble à `https://github.com/YesWiki/yeswiki-extension-nomextension/settings/hooks/new`)
 11. compléter le formulaire de cette manière
     - **Payload URL** : url de `repository-api`
     - **Content type** : application/json
     - **Secret** : le mot de passe secret GitHub stocké dans le fichier de config du serveur `repository-api`
     - choisir **just the `push` event**
 12. prévenir la communauté des développeurs de la création de cette extension sur le canal Framateam : https://framateam.org/yeswiki/channels/developpement
     - la communauté `YesWiki` peut alors demander un renommage de l'extension, il faudra alors :
       - créer une nouvelle extension avec le nouveau nom
       - recopier le code
       - supprimer l'extension actuelle

### Supprimer une extension

 1. supprimer le dossier concerné de `GitHub` (**action définitive**)
 2. se rendre sur https://github.com/YesWiki/yeswiki-build-repo/blob/master/repo.config.json et retirer les références à cette extension
 3. se rendre avec sa clé `SSH` sur `repository-api` pour mettre à jour le fichier `repo.config.json` grâce à `git pull`
 4. se rendre dans le sous-dossier de `repository.yeswiki.net` pour supprimer les fichiers `.zip` de l'extension et ainsi retirer les archives accessibles depuis internet
 5. pour chaque version de `YesWiki`, se rendre dans le sous-dossier concerné pour retirer les références à cette extension du fichier `package.json`
 6. prévenir la communauté sur le canal Framateam : https://framateam.org/yeswiki/channels/developpement
