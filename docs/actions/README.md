# Actions Builder - Comment documenter une action

## Créer un nouveau groupe d'action

_Chaque groupe est visible dans le menu de l'éditeur. Un groupe peut contenir beaucoup d'action (comme le groupe bazar), ou une seule (comme le groupe bouton)_

**Créez un nouveau fichier** dans `docs/actions`. Si vous documentez une extension, alors créez le fichier `actions/documentation.yaml`

Voilà le contenu du fichier

```yaml
label: Ajouter un bouton # Nom affiché dans la barre d'action de l'éditeur
position: 3 # en 3ème position dans la liste des groupe d'actions disponibles
previewHeight: 350px # La hauteur de la zone d'aperçu
needFormField: false # Est ce qu'un formulaire doit être choisi en même temps que l'action ? (c'est le cas pour bazar)
onlyForAdmins: true # Est ce que lcontenu de ce fichier ne doit être affiché que pour les admins ?
actions:
  # La liste des actions de ce groupe
```

## Internationalisation

Afin de rendre le fichier traductible, il est préférable de fournir une clé de traduction

```yaml
label: _t(AB_mongroupe_label) # AB = ActionsBuilder
actions:
  myaction:
    label: _t(AB_mongroupe_myaction_label)
```

```php
// docs/actions/lang/actionsbuilde_fr.inc.php
  'AB_mongroupe_label' => "Ajouter un boutton",
  'AB_mongroupe_myaction_label' => "Mon Action"
```

Pour faciliter la maintenance, on peut essayer de construire le nom de la clé de traduction en respectant l'arborescence du YAML

```yaml
label: _t(AB_mongroupe_label) # AB = ActionsBuilder
actions:
  myaction:
    label: _t(AB_mongroupe_myaction_label)
    properties:
      latitude:
        label: _t(AB_mongroupe_myaction_latitude_label)
```

## Documenter une action

_Une action est une manière d'afficher des composants élaborés. Elle est déclarée avec un nom unique en minuscule, et une série de paramètres qui permettent de la configurer/personaliser. Elle s'écrit dans l'éditeur sous la forme {{nomaction param1="" param2="" ...}}_

Voilà la liste des champs possible pour documenter une action

```yaml
actions:
  myaction:
    label: Mon Action # Nom de l'action
    description: Une description courte
    hint: Le champ XX doit être présent... # Information importante à savoir si on utilise cette action
    isWrapper: true # rajouter cette ligne pour les actions qui doivent se fermer avec un {{end elem="action"}}
    wrappedContentExample: 'Teeest' # si l'action est un wrapper, le texte à inclure dans l'action à titre d'exemple
    properties:
      # La liste des paramètres de l'action.
```

Vous pouvez ajouter autant de **paramètres** que vous voulez. Ils représentent un champ que l'utilisateur peut remplir pour personnaliser l'action. La forme d'un paramètre est la suivante :

```yaml
label: Texte à Afficher # Nom du champ que l'utilisateur peut remplir
type: text # Type de champ
default:  # valeur par défault. Si le parametre est égal à cette valeur par default, il n'est pas inclus dans le code wiki généré
value: # valeur lors de l'initialisation
required: true # true/false, est ce que ce champ doit absolument être configuré par l'utilisateur
advanced: true # sera masqué tant que l'utilisateur ne coche pas la case "paramètres avancés"
hint: Mon Texte # Indications
icon: leaf # nom d'une icone fantwesome
doclink: https://... # Lien vers une documentation en ligne
showif: colorfield # Ce paramètre sera visible uniquement lorsque le paramètre colorfield n'est pas vide
showif:
  format: portrait # Uniquement visible quand le paramètre "format" est égal à "portait"
  type: notNull # et quand le paramètre "type" n'est pas vide
  filename: .*(png|jpg) # support regular expression
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
- page-list
- divider

#### checkbox

Paramètres optionels : `checkedvalue` `uncheckedvalue`. Par défault la valeur d'un paramètre checkbox est "true" ou "false", si par exemple vous voulez que lorsqu'on coche la case, la valeur soit "1", alors renseignez checkvalue: 1 et uncheckedvalue: 0

```yaml
modal:
  label: Affichage d'une fenêtre modale lors du clic
  type: checkbox
  checkedvalue: 1
  uncheckedvalue: 0
```

#### list

Vous devez renseigner les options possibles

```yaml
color:
  label: Couleur
  type: list
  default: btn-primary
  options:
    btn-default: Default
    btn-primary: Primaire
    btn-info: Info
    btn-warning: Attention
    btn-danger: Danger
```

Si les options ont la même valeur que leur clé, on peut donner un tableau pour simplifier

```yaml
options:
  - OpenStreetMap.Mapnik
  - OpenStreetMap.BlackAndWhite
  - OpenStreetMap.DE
```

au lieu de

```yaml
options:
  OpenStreetMap.Mapnik: OpenStreetMap.Mapnik
  OpenStreetMap.BlackAndWhite: OpenStreetMap.BlackAndWhite
  OpenStreetMap.DE: OpenStreetMap.DE
```

#### form-field

Permet de choisir un champ du formulaire préalablement sélectioné (voir `needFormField` dans la configuration du groupe d'action)

### type class

Le type class va concaténer la valeur de plusieurs champs et la mettre dans le paramètre class. On utilise `subproperties` pour déclarer les différents champs qui vont être concaténés

```yaml
class:
  type: class
  subproperties:
    color:
      label: Couleur
      type: list
      options:
        btn-default: Default
        btn-primary: Primaire
        btn-info: Info
    size:
      label: Taille
      type: list
      options:
        btn: Normal
        btn-xs: Petit
```

Ainsi, plutôt que `{{button color="btn-default" size="btn-xs"}}` le résultat sera `{{button class="btn-default btn-xs"}}`

### type divider

Il permet d'incruster un titre au milieu des autres champs, pour créer des sous sections par exemple

```
mydivider:
  label: Un titre
  type: divider
```

### default vs value

Ils vont tous les deux servir à initialiser une valeur.
La différence étant que si on utilise `default`, alors si la valeur est égale à `default` le paramètre
sera masqué dans le wiki code généré
Généralement on utilisera donc `default` afin d'éviter les code wiki à rallonge

```
modal:
  label: Affichage d'une fenêtre modale lors du clic
  type: checkbox
  default: true
```

-> checkbox cochée par default, et le code généré ne contiendra pas le param modal si il est égal à true, mais contiendra `modal="false"` si on décoche

```
modal:
  label: Affichage d'une fenêtre modale lors du clic
  type: checkbox
  value: true
```

-> checkbox cochée par default, et le code généré contiendra quoiqu'il arrive soit `modal="true"` soit `modal="false"`
