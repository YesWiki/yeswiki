## Personnalisation avancées

!> Des bases de programmation vous seront nécessaire pour suivre ces étapes

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