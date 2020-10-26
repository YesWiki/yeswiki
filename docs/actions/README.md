# Actions Builder - Comment document une action

## Créer un nouveau groupe d'action

_Chaque groupe est visible dans le menu de l'éditeur. Un groupe peut contenir beaucoup d'action (comme le groupe bazar), ou une seule (comme le groupe bouton)_

**Créez un nouveau fichier** dans `docs/actions`. Si vous documentez une extension, alors créez le fichier `actions/documentation.yaml`

Voilà le contenu du fichier
```yaml
label: Ajouter un bouton # Nom affiché dans la barre d'action de l'éditeur
position: 3 # en 3ème position dans l liste des groupe d'actions disponibles
previewHeight: 350px # La hauteur de la zone d'aperçu
needFormField: false # Est ce qu'un formulaire doit être choisi en même temps que l'action ? (c'est le cas pour bazar)
actions:
  # La liste des actions de ce groupe
```

## Documenter une action

_Une action est une manière d'afficher des composants élaborés. Elle est déclarée avec un nom unique en minuscule, et une série de paramètres qui permettent de la configurer/personaliser. Elle s'écrit dans l'éditeur sous la forme {{nomaction param1="" param2="" ...}}_

Voilà la liste des champs possible pour documenter une action
```yaml
actions:
  myaction:
    label: Mon Action # Nom de l'action
    hint: Le champ XX doit être présent... # Information importante à savoir si on utilise cette action
    properties:
      # La liste des paramètres de l'action.
```

Vous pouvez ajouter autant de **paramètres** que vous voulez. Ils représente un champ que l'utilisateur peut remplir pour personnaliser l'action. La forme d'un paramètre est la suivante :

```yaml
label: Texte à Afficher # Nom du champ que l'utilisateur peut remplir
type: text # Type de champ
default:  # valeur par défault
required: true # true/false, est ce que ce champ doit absolument être configuré par l'utilisateur
advanced: true # sera masqué tant que l'utilisateur ne coche pas la case "paramètres avancés"
```

## Types de paramètres

Les type sont
  - text
  - number
  - url
  - color
  - icon
  - checkbox
  - list
  - form-field

#### checkbox
Paramètres optionels : `checkedvalue` `uncheckedvalue`. Par défault la valeur d'un paramètre checkbox est "true" ou "false", si par exemple vous voulez que lorsqu'on coche la case, la valeur soit "1", alors renseignez checkvalue: 1 et uncheckedvalue: 0
```yaml
modal:
  label: Affichage d'une fenêtre modale lors du clic
  type: checkbox
  default: 0
  checkedvalue: 1
  uncheckedvalue: 0
```

#### list
Vous devez renseigner les options possibles
```yaml
provider:
  label: Fond de carte
  type: list
  default: OpenStreetMap.Mapnik
  options:
    - OpenStreetMap.Mapnik
    - OpenStreetMap.BlackAndWhite
    - OpenStreetMap.DE
```

#### form-field
Permet de choisir un champ du formulaire préalablement sélectioné (voir `needFormField` dans la configuration du groupe d'action)

### type class
Le type class va concaténer la valeur de plusieurs champs et la mettre dans le paramètre class. On utilise `subproperties` pour déclarer les différents champs qui vont être concaténé
```yaml
class:
  type: class
  subproperties:
    color:
      label: Couleur
      type: list
      options:
        - btn-default->Default
        - btn-primary->Primaire
        - btn-info->Info
    size:
      label: Taille
      type: list
      options:
        - btn->Normal
        - btn-xs->Petit
```
Ainsi, plutôt que `{{button color="btn-default" size="btn-xs"}}` le résultat sera `{{button class="btn-default btn-xs"}}
