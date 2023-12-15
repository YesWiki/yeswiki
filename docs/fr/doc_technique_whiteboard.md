# Documentation Technique 

## Description
Les éléments `WhiteBoardField` et `ExcalidrawField` sont des extensions de champ pour le système YesWiki Bazar, permettant l'intégration de tableaux blancs collaboratifs via des iframes.

## Utilisation
Ces éléments peuvent être utilisés dans les fiches Bazar pour afficher des tableaux blancs collaboratifs.


## Fonctionnalités Principales

### Whiteboard
- Attributs:
  - `whiteboardUrl`: URL du tableau blanc collaboratif.
  - `entryId`: ID de l'entrée associée au tableau Whiteboard.

### Excalidraw
- Attributs:
  - `excalidrawUrl`: URL du tableau Excalidraw.
  - `entryId`: ID de l'entrée associée au tableau Excalidraw.

### Fichiers Associés

#### `WhiteboardField.php`, `whiteboard.twig`, `whiteboard.js`, `whiteboard.yaml`
Fichiers associés à l'élément Whiteboard.

#### `ExcalidrawField.php`, `excalidraw.twig`, `excalidraw.js`, `excalidraw.yaml`
Fichiers associés à l'élément Excalidraw.

## Installation

Aucune installation n'est nécessaire pour ce projet, car il utilise le lien HTTPS des outils utilisés. 
Toutefois, vous pouvez importer le projet localement en utilisant les liens GitHub suivants :
- [Projet Github Whiteboard](https://github.com/lovasoa/whitebophir)
- [Projet Github Excalidraw](https://github.com/excalidraw/excalidraw)

## Créer un Tableau Privé / Création d'un Tableau Blanc Nommé

1. **Tableau Privé Aléatoire :**
   - Créez un tableau privé avec un nom généré de manière aléatoire.

2. **Tableau Privé Nommé :**
   - Créez un tableau privé avec un nom spécifique pour une URL personnalisée.

### URLs Whiteboard et Excalidraw

Lors de la création d'un tableau blanc avec `WhiteBoardField` ou `ExcalidrawField`, l'Entry ID de la fiche est utilisé pour générer l'URL du tableau.
Par exemple, si l'Entry ID est "mon_tableau_confidentiel":
- URL Whiteboard : [URL exemple](https://wbo.ophir.dev/boards/mon_tableau_confidentiel)
- URL Excalidraw : [URL exemple](https://excalidraw.com/mon_tableau_confidentiel)
