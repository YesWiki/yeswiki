# Rendre les données sémantiques dans votre YesWiki

## Configurer un formulaire pour exporter les données en `json-ld` (sémantique)

Le format `json` est un format standard pour l'échange de données. Il est déjà utilisé dans `YesWiki` pour exporter des données comme celles des fiches d'un formulaire ([exemple](?api/forms/1/entries ':ignore')).

Toutefois, le format `json` n'est pas suffisant pour savoir comment les données y sont rangées. Pour celà, il faut donner du sens aux données, les rendre [**sémantiques**](https://fr.wikipedia.org/wiki/S%C3%A9mantique).

Il existe donc le format [`json-ld`](https://fr.wikipedia.org/wiki/JSON-LD) qui est une couche supplémentaire de normalisation pour expliquer comment les données y sont rangées. Le site officiel concernant ce format est : https://json-ld.org/.

## Concept d'ontolgie

Le concept d'[**ontologie**](<https://fr.wikipedia.org/wiki/Ontologie_(informatique)>) est à la base du web des données sémantiques.

Une ontologie est un modèle de données qui explique comment les données sont structurées entre elles.

Deux ontologies très connues sont :

- https://schema.org/
- https://www.w3.org/TR/activitystreams-core/ ([article Wikipedia](<https://fr.wikipedia.org/wiki/Activity_Streams_(format)>))

Les données sémantiques indiquent toujours à quelle ontologie elles font référence pour que le destinataire puisse s'y retrouver automatiquement.

Une ontologie peut être décrite via la norme [`OWL`](https://fr.wikipedia.org/wiki/Web_Ontology_Language) ou par le format [`RDFS`](https://en.wikipedia.org/wiki/RDF_Schema)

## Configuration dans YesWiki

Il est possible d'exporter les fiches d'un formulaire au format `json-ld` en utilisant une url de ce type [`?api/forms/{formId}/entries/json-ld`](?api/forms/1/entries/json-ld ':ignore').

Si le formulaire en question n'a pas été correctement configuré, les données ne s'afficheront pas comme il faut.

La configuration se fait en deux étapes:

1.  on associe le formulaire à une classe de l'ontologie (ou modèle de données)
2.  on associe chaque champ du formulaire aux attributs de l'ontologie

?> Une page de documentation existe depuis un moment. Il peut arriver qu'elle ne soit plus à jour : <https://yeswiki.net/?RendreYeswikiSemantique>, **les informations sont donc complétées ici**.

### 1. Activer le contexte sémantique

- se rendre sur la page d'édition du formulaire concerné [?BazaR&vue=formulaire&action=modif&idformulaire={formId}](?BazaR&vue=formulaire&action=modif&idformulaire=1 ':ignore')
- tout en bas, déplier la partie "Configuration avancée"
- compléter la partie `contexte sémantique`
  - si une seule ontologie utilisée, vous pouvez mettre l'url de l'ontologie
    - exemple 1 : `https://www.w3.org/ns/activitystreams`
    - exemple 2 : `https://schema.org/`
  - si plusieurs ontologies seront utilisées, vous pouvez utiliser le format `json`
    ```
    [
        "https://www.w3.org/ns/activitystreams",
        {
        "schema": "https://schema.org/"
        }
    ]
    ```
- à ce stade, le formulaire ne sera pas exporté en `json-ld` : il vous faut définir le type qui correspond aux fiches de ce formulaire et l'indiquer **dans la partie `type sémantique`** en bas du formulaire (partie paramètres avancées)
  - exemple si une seule ontologie `https://www.w3.org/ns/activitystreams`
    - pour une personne : `Person`
    - pour un évènement : `Event`
    - pour un lieu : `Place`
    - pour un article de blog : `Article`
    - pour un autre type de données : s'aider de cette page : https://www.w3.org/ns/activitystreams#class-definitions
  - exemple si une seule ontologie `https://schema.org/`
    - pour une personne : `Person`
    - pour un évènement : `Event`
    - pour un lieu : `Place`
    - pour un article de blog : `Article`
    - pour un autre type de données : s'aider de cette page : https://schema.org/docs/schemas.html
  - exemple si deux ontologies (_exemple précédent_)
    - pour une personne : `Person, schema:Person`
    - pour un évènement : `Event, schema:Event`
    - pour un lieu : `Place, schema:Place`
    - pour un article de blog : `Article, schema:Article`
    - le types peuvent parfois porter des noms différents selon les ontologies
- à ce stade, les données seront bien formatées en `json-ld` mais elles contiendront peut d'information

?> _Info_ : dans les paramètres avancées, il existe une case à cochée `Utiliser un template sémantique s'il est disponible pour ce type d'objet`. Son état n'a pour le moment pas d'importance car il n'est pas pris en compte. C'est une case dans l'idée de détecter automatiquement le type sémantique de chaque champ à partir de ce qu'il est (par exemple, un champ `url` pourrait être automatiquement considéré comme le type sémantique `url`). Donc, ne pas prendre en compte cette case, cochée ou non.

### 2. Configurer le contexte sémantique pour chaque champ du formulaire

En effet, pour que les données soient affichées, il faut que les champs qui les concernent soient reliés à un type de l'ontologie; **sinon les données de ce champ ne sont pas diffusées**

- Se rendre dans le constructeur graphique de formulaire pour modifier le formulaire concerné
- Choisir un champ (exemple, le champ `bf_name` s'il existe)
- Éditer le champ et déplier la partie **paramètres avancées**
- Dans la partie `Type sémantique du champ`, ajouter le type sématique en respectant le formalisme précédent
  - exemple si une seule ontologie `https://www.w3.org/ns/activitystreams`
    - nom pour une `Person` ou un `Event` : mettre `name`
    - email pour une `Person`: non défini, il n'y a pas d'attribut pour ce champ, il ne sera pas diffusé
    - date de début pour un `Event` : mettre `startTime`
    - date de fin pour un `Event` : mettre `endTime`
    - beaucoup de ces propriétés sont héritées de https://www.w3.org/TR/activitystreams-vocabulary/#dfn-object
  - exemple si une seule ontologie `https://schema.org/`
    - nom de famille pour une `Person` : mettre `familyName`
    - email pour une `Person` : mettre `email`
    - nom pour un `Event` ou une personne `Person` : mettre `name`
    - date de début pour un `Event` : mettre `startDate`
    - date de fin pour un `Event` : mettre `endDate`
  - exemple si deux ontologies (_exemple précédent_)
    - nom pour une `Person` ou un `Event` : mettre `name, schema:name`
    - email pour une `Person` : mettre `schema:email`
    - date de début pour un `Event` : mettre `startTime,schema:startDate`
    - date de fin pour un `Event` : mettre `endTime,schema:endDate`

?> **Astuce**: beaucoup des attributs sont hérités de la classe parente (`Extends`). Il est donc possible d'utiliser les attributs de la classe `Object` par exemple, même s'ils n'ont pas été indiqués dans la classe fille (ex.: `Person`)

!> **Important**: il n'est pas nécessaire de définir un _type sémantique_ du champ pour chaque champ. Dans le doute, il vaut mieux le laisser vide. Dans ce cas, le contenu du champ ne sera pas fourni dans le `json-ld`.
Pour afficher un champ, il reste important de lui attribuer un type qui correspond. Ainsi, seul le champ titre ou nom devrait avoir le type `name`. Pour les autres champs, il faudra utiliser un autre type en respectant l'ontologie concernée.
