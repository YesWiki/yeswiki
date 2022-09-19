# Administrer son wiki

> TODO Petite description

## Suivre la vie de son wiki 

### Via la page tableau de bord

Une page TableauDeBord accessible dans le menu "roue crantée". Il permet d'accéder aux  

*   derniers comptes utilisateurs créés
*   dernières pages modifiées
*   dernières pages commentées
*   un index de toutes les pages du Wiki

### Via la page DerniersChangements
Sur cette page, vous verrez toutes les pages modifiées du wiki.

### Via les flux rss du wiki
Plusieurs flux RSS sortent du wiki : 
 - L'ensemble des changements du wiki
     - ce flux est accessible via la page DerniersChangementsRSS/xml de votre wiki
 - les modifications de chacun des formulaires
      - ces flux sont accessibles via la page "base de données" de la roue crantée  

## Lutter contre le spams 

Hélas comme la plupart des wikis ouverts (MediaWiki, DokuWiki), YesWiki n'échappe pas aux attaques de quelques ~~emmerdeurs~~ référenceurs soit-disant professionnels et autres robots de spam, qui polluent les contenus des pages.  
  

### Les 10 commandements du lutteur anti spam

* **1**. Je consulte régulièrement mon wiki  
* **2**. Je m'abonne à son flux RSS [voir plus bas / suivre la vie de mon wiki](#Suivre-la-vie-de-son-wiki)  
* **3**. Je consulte la page TableauDeBordDeCeWiki de mon wiki (accessible depuis la "roue crantée")  
* **4**. Je vérifie les dernières pages modifiées dans le TableauDeBordDeCeWiki ou sur la page DerniersChangements  
* **5**. Je vérifie les derniers comptes crées sur la page TableauDeBordDeCeWiki. (Action {{Listusers last="20"}} )  
* **6**. J'édite les pages en question et je supprime les parties indésirables, puis je sauve. (Cela prend moins d'une minute)  
* **7**. Je protège l'accès en écriture des pages spéciales du wiki (menu, roue crantée, footer...)  
* **8**. Je maintiens mon wiki à jour  
* **9**. Pour les plus endurcis, je fais le grand ménage avec l'outil despam (voir plus bas)  
* **10**. Je ne cède pas à la tentation de transformer mon espace collaboratif en bunker. Et je continue à mettre en balance les effets positifs de l'intelligence collective.  

  

### Les symptômes : comment identifier les spams ?

*   Vous pouvez découvrir sur une ou plusieurs pages des liens vers des sites externes qui semblent sans rapport avec l'objet du wiki _(qui vendent des robes de mariée, des sites indonésien sans rapport, des liens commerciaux vers la loi duflot, des textes en langue étrangère etc..)_
*   Il se peut aussi que de nouvelles pages soit créées, et dans certains cas de nouveaux utilisateurs wikis.

Dans tous les cas, il sera toujours possible de faire marche arrière, et les informations sensibles d'accès ftp ou mysql à votre serveur ne peuvent pas être trouvés comme cela.  


### Que faire si vous avez du spam ?

#### Réparer une page spéciale spammée

_Tiens, ce matin, en me baladant sur un de mes YesWiki j'ai découvert que j'avais été spammé avec un bel écran bizarre à la place de ma page d'accueil et impossible de pouvoir modifier quoique ce soit !_  
  
##### Si votre wiki est ouvert en écriture: 

*   1\. identifier la page spammée en ajoutant le handler /editiframe
    aux pages spéciales. Voici ci-dessous, la liste des pages spéciales concernées.
*   2\. Dès que le code malicieux est repéré, supprimer ce code et sauvegarder la page.
*   3\. Revenir sur la liste des versions de cette page pour éditer la version avant l'apparition du code malicieux et remettre en place le contenu précédent

##### Si votre wiki est fermé en écriture

Il peut être impossible de se connecter au wiki. Ceci peut contourner en utilisant ce lien qui permet de ne pas afficher les pages spéciales : https://www.example.com/?ParametresUtilisateur/iframe  

#### Utiliser les paramètres de contrôle d'accès via le wakka config ou la page ["Fichier de conf"](https://yeswiki.net/?GererConfig) XXXXXX à MODIFIER

Des nouveaux paramètres ont été ajoutés dans le wakkaconfig et permettent notamment  

*   d'ajouter un capcha en mode édition
*   d'ajouter un champ (mot de passe) en entrée du mode édition (+ un message informatif sur ce mot de passe)

Les paramètres ajoutables au wakkaconfig        

    'password\_for\_editing' => 'votremotdepasse',
    'password\_for\_editing\_message' => 'un message qui apparait au dessus du champ mot de passe',
    'use\_hashcash' => true, //ne pas toucher pour l'instant            
    'use\_nospam' => true, // ne pas toucher pour l'instant 'use\_alerte' => true,
    'use\_captcha' => true,

Ces paramètres sont aussi activables via la page de gestion du site (onglet fichier de conf)

#### Pour les ajouts dans une page isolée 

1.  Editer la page en question et supprimer la partie indésirable, puis sauver. (Cela prend moins d'une minute)

Astuce: veiller à plusieurs à partir du flux RSS qui sort de votre wiki est plus efficace  

#### Pour de nouvelles pages indésirables créées

##### Si vous pouvez vous connecter en tant que WikiAdmin :  

1.  s'identifier en tant qu'administrateur du wiki (WikiAdmin par défaut)
2.  éditer les permissions de la page pour mettre le compte [WikiAdmin](https://yeswiki.net/?WikiAdmin) propriétaire de la page
3.  supprimer la page à partir du lien sur la barre d'action en bas de page

##### Si vous ne pouvez pas vous connecter en tant que WikiAdmin :**  

1.  éditer la page et remplacer tout le texte de spam par un caractère (il faut au moins un contenu autre qu'un espace dans la page pour la sauver (pour ma part j'utilise ".")


#### Pour limiter la création de nouveaux comptes

Pour éviter que des inconnus puissent se créer des comptes, vous pouvez limiter l'action [UserSettings](https://yeswiki.net/?UserSettings) aux seuls administrateurs.  
Si cela a l'avantage de bloquer/réserver la création de nouveaux comptes aux seuls admin,cela limite vraiment l'autonomie de vos utilisateurs.  

#### Pour supprimer les commentaires indésirables

1.  Ajouter l'action {{erasespamedcomments}} dans la page de votre choix. (Elle n'est accessible qu'aux administrateurs)
2.  Ensuite cocher les commentaires indésirables et appuyer sur le bouton "Nettoyer"

  

#### Pour supprimer de nombreuses pages rapidement

SI vous êtes connecté-e en tant qu'admin, il vous suffit de coller ceci à la fin de l'url des pages à supprimer : /deletepage&confirme=oui  
Cela vous évite toutes les étapes de validation, qui deviennent très fastidieuses lorsqu'on a plusieurs pages à supprimer. Attention, ce "raccourci" supprime définitivement la page sans message de confirmation, ne vous trompez donc pas !  
  

#### Pour les attaques massives sur de nombreuses pages

**cette technique nécessite des informations sur les codes FTP et Mysql**  
  
Pour faire le grand ménage avec le tools despam :  

1.  aller sur la barre d'adresse url de votre navigateur et remplacer wakka.php (et ce qu'il y a derrière) par tools.php (pour avoir une url du type http://monadressedewiki/tools.php )
2.  identifiez-vous à l'aide des **identifiants de la base de données Mysql** plutôt que vos identifiants wiki
3.  la liste des extensions apparaît, cliquer sur "Nettoyage Spam"
4.  Sélectionner l'intervalle de temps à prendre en compte pour les dernière modifications
5.  cocher les choix adéquats, entre supprimer la page ou revenir à la version précédente
    *   **ATTENTION, il faut IMPÉRATIVEMENT vérifier les pages en question pour ne pas supprimer définitivement le contenu!!**
6.  cliquer sur "Nettoyer"

#### Pour supprimer les utilisateurs non désirables (utilisateurs avancés, non disponible par défaut)

Ajouter par FTP, dans le répertoire tools l'extension suivante : [http://yeswiki.net/downloads/actions.zip](http://yeswiki.net/downloads/actions.zip)  
  
Pour la mise en oeuvre, voir la documentation suivante:  
[Télécharger le fichier doc\_action\_delete.pdf (0.7MB)](https://yeswiki.net/?LutterContreLeSpam/download&file=doc_action_delete.pdf)[](https://yeswiki.net/?LutterContreLeSpam/upload&file=doc_action_delete.pdf "Mise à jour")  

#### Activer l'extension Ipblock
Cette extension permet de bloquer l'accès à votre wiki en fontion des adresses IP (et de leur provenance géographique). 
Elle s'active via l'onglet Mise à jour / extension de la page gestion du site de votre wiki.
Les paramètres sont alors visibles dans la partie "Blocage d'adresses IP"