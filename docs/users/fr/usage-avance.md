## Comment faire pour
### Utiliser l'images insérée dans une page dans une autre page ?

## Les Handlers 

### Définition 

Un handler est une commande qui permet de modifier la façon d'afficher une page. On l'active en ajoutant à la fin de l'adresse URL, le signe **/** suivi du nom du handler.  
  
**Exemples**  
  
* Ajouter **/raw** a la fin d'une adresse URL de YesWiki (https://yeswiki.net/?AccueiL/raw) (dans la barre d'URL de votre navigateur), permet d'obtenir le contenu de la page sans interprétation, en syntaxe wiki.  

  
* Ajouter **/edit** a la fin d'une adresse url de YesWiki (https://yeswiki.net/?AccueiL/edit) (dans la barre d'url de votre navigateur), permet d'afficher la page en mode édition  

### Liste des handlers (à vérifier)
https://yeswiki.net/?DocumentationHandlers

**Liste des handlers disponibles par défaut**
    
Rappel : Rajouter dans la barre d'adresse, à la fin de l'URL (www.monsiteyeswiki/handler)

* **/edit** : pour passer en mode Édition
* **/revisions**  : pour voir les versions de l'historique 
* **/slide_show**  : pour transformer le texte en diaporama 
* **/diaporama**  : idem slide_show en un peu différent
* **/mail**  : envoie la page en mailing
* **/raw** : affiche le code wiki non formaté de la page
* **/deletepage**  : si vous êtes propriétaire de la page, vous pouvez la supprimer
* **/claim**  : si la page n'a pas de propriétaire, vous pouvez vous l'approprier
* **/acls**  : si vous êtes propriétaire de la page, vous pouvez gérer les droits 
* **/share**  : pour afficher des possibilités de partage sur les réseaux sociaux, et pour générer un code embed (iframe) qui permettra d'afficher la page sur un site externe. 
* **/dbutf8**  : s'utilise en tant qu'admin pour passer les wikis antérieur à 2018 en utf8 
* **/update**  : permet lors du passage de cercopithèque à doryphore, de mettre à jour plein de trucs nécessaires à son bon fonctionnement 
* **&amp;debug** : permet d'afficher en bas de page toutes les actions effectuées au niveau informatique, permet de repérer les bugs, causes de plantage... 
* **/editiframe**  : permet d'ouvrir la page en mode édition mais en cachant les autres pages du squelette (utile quand une image ou un spam sur le bandeau empêche de voir le contenu de la page à modifier ou dans le cas d'un wiki intégré en iframe) 

## Page "Lexique" :

définition des quelques mots 
    liste, fiche, formulaire, page, composant...

### Les actions 

Une action exécute une série d'opérations et affiche le résultat obtenu à l'endroit où elle est décrite dans la page.  
Les actions sont utilisées par exemple pour afficher un bouton, la liste des utilisateurs, un nuage de mots-clés.  
Il est possible de spécifier des paramètres afin de personnaliser ce résultat (ordre du tri, nombre d'entrées, taille...).  
  
Certains paramètres peuvent être obligatoires.  
  
**Syntaxe**  
Une action s'écrit avec 2 accolades ouvrantes, suivi du nom de l'action, suivi par des éventuels paramètres, puis fermée par 2 accolades fermantes :  

{{nomaction param1="valeur1" param2="valeur2" ... paramN="valeurN"}}

**Qu'est-ce que'une action dépréciée ?**  
Une action qui fonctionne encore mais qui a été remplacée ou intégrée dans une nouvelle action et qui sera supprimée dans la prochaine version de YesWiki.

### La majorité des actions est écrite via les composants

Les composants permettent aujourd’hui d'éditer quasiment toutes  les actions et de les personnaliser.
