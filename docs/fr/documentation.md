# Contribuer à la documentation de YesWiki

Ceci est une page d'aide à destination de celleux qui souhaitent contribuer à une excellente documentation utilisateurices pour YesWiki.

Cette documentation sera accessible avec le chemin `?doc` sur tous les wikis, exemple `https://yeswiki.net/?doc`


## 0. Introduction

Nous souhaitons inclure une documentation de YesWiki, accessible aux utilisateurices de chaque YesWiki sur le même serveur que le wiki. 

**De cette manière tout wiki sera toujours lié à une doc compatible avec sa version.**

### 0.1 Quel genre de documentator êtes-vous ? 
Afin d'éviter à toute personne souhaitant contribuer à la documentation de yeswiki de devoir utiliser github, nous proposons deux niveaux de ce : "comment documenter ?" 

**Identifiez votre niveau et référez-vous à la partie de cette documentation correspondante :**


?> **😍 Documentator de Niveau 1**
Vous souhaitez contribuer à la doc officielle, mais github vous effraie ?
?> Un grand merci à vous votre contribution est nécessaire. On a une solution pour vous, rendez-vous en partie **Niveau 1**. 

!> **💪 Documentator de Niveau 2**
Vous souhaitez aider à alimenter la documentation officielle et manipuler github de temps en temps c’est ok pour vous.
On a besoin de vous pour mettre à jour la doc officielle avec les super contributions des documentators de **niveau 2**.



## 1. Documenter 😍 Niv 1

### 1.1. Accéder en mode édition

Pour ce premier level, nous n'allons pas vous guider vers github mais sur une copie de fichiers en version édition. 

**Pour y accéder suivre les étapes suivantes :**
1. [Accéder au framateam en suivant les instructions ici.](https://yeswiki.net/?Framateam)
2. Se présenter et intégrer le canal "documentation"
4. En entête de ce canal vous trouverez un lien nommé "🕸️ Les HedgeDoc", accéder à cette page. Cette page contient une série de liens qui mènent à un utilitaire "hedgedoc" qui permet d'éditer collaborativement des fichiers markdown. 
5. Cliquer sur le fichier que vous souhaitez modifier pour accéder au HedgeDoc correspondant. 
6. Editer le fichier. 
7. Une fois le fichier édité, indiquer le dans le canal "documentation" du Framateam pour qu'un **documentator de Niveau 2** puisse mettre à jour la documentation officielle avec vos contributions.


### 1.2. Bonnes pratiques
Avant de vous lancer dans la rédaction, nous vous invitons à lire les paragraphes suivants.

#### 1.2.1 Captures d'écran

Il est toujours bienvenu d'avoir des captures d'écran pour faciliter la compréhension. Mais, il faut garder à l'esprit que ces captures devront être modifiées selon les changements de versions et pour chaque langue traduite. Il est donc judicieux d'en mettre, mais de conserver un équilibre sur leur quantité. Par ailleurs, le widget preview (qui permet d'avoir un aperçu sur les composants, par exemple) devrait permet de réduire le nombre de captures d'écran.

Si cela est possible, il est préférable d'inclure directement des rendus de code yeswiki (en grisé par exemple), [voir plus bas](/docsREADME?id=afficher-du-code-yeswiki)

#### 1.2.2 Ecriture inclusive

Nous essayons d'utiliser une écriture inclusive, en utilisant la jonction de termes : `celleux` ou `utilisateurice` par exemple.

Il est également préférable d'utiliser des mots épicènes lorsque cela est possible (exemple élève plutôt qu'étudiant.e)

Plus d'info sur l'écriture inclusive : [https://eninclusif.fr/](https://eninclusif.fr/)

#### 1.2.3 Ton et langage

Nous devrions dans la documentation adopter un style neutre et factuel. Ca ne veut pas dire qu'il faille être trop formel, le ton peut être détendu et parfois spirituel. Il faut juste garder à l'esprit, quand on emploie un style humouristique, que tout le monde n'a pas le même humour et que l'objet de la doc est de s'adresser à tout le monde.

#### 1.2.4 Lisibilité

Essayons de rendre la documentation agréable à lire. En évitant les paragraphes trop longs, les multiples sous-niveaux. On peut inclure quelques éléments graphiques pour aérer la page, comme les encadrés de messages décrits plus bas.

#### 1.2.5 Markdown
L'ensemble de la documentation est écrite en markdown, un langage simple que yeswiki utilise aussi. 
Apprendre à utiliser ce langage : https://www.markdown-cheatsheet.com/

## 1.3. Architecture de la doc
La documentation officielle de YesWiki est organisée dans un menu. Chacune des pages accessibles via ce menu correspond à un fichier Markdown. 

?> **Exemple :** Pour modifier la page "installation" il conviendra de modifier le fichier "webmaster.md".

| Page | Fichier | Description |
| -------- | -------- | -------- |
| **Installation** | `webmaster.md` | Là on sort de l'interface web, avec des infos sur l'installation, les fichiers de configuration, la gestion des mails sortants, migration, la herse, la gestion spams par phpmyadmin et ce genre de choses. |
| **Prise en main** | `prise-en-main.md` | Toute la documentation pour aider l'utilisateurice de base à créer un compte, récuperer son mot de passe, éditer ou créer une page, comprendre la syntaxe wiki de base, etc... |
| **Formulaires** | `bazar.md` | Toute la doc concernant bazar (création, rendu, templates...) |
| **Administration** | `admin.md` | Pour celleux qui poussent leur usage du wiki dans ses plus fines fonctionnalités : tout ce qui est dans la roue crantée (et singulièrement la partie dans la page gestion du site dont look du wiki), usage des actions, mise en page, etc, ... |
| **Participer à la communauté** | `communaute.md` | Tout savoir quand on souhaite s'impliquer d'une manière ou d'une autre dans la communauté. |
| **Améliorer la documentation** | `documentation.md` | C'est le fichier que vous êtes en train de lire. La documentation de la documentation. Tout savoir pour contribuer à la présente documentation. |
| **Développer le code** | `dev.md` | Tout savoir pour contribuer au code de yeswiki. |
| **Adhérer / Soutenir financièrement** | `asso-finances.md` | Comment adhérer ou soutenir financièrement l'association YesWiki. |
| **Extensions** | Aucun | En fonction des extensions installés sur votre wiki des onglets s'ajouteront ici. La documentation pour chacune des extensions est à éditer directement sur le dépôt github de l'extension. |
| **Autres espaces** | Aucun | Liens utiles vers des espaces en ligne comme le forum, les vidéos, les tutoriels,... |

## 2. 💪Documenter de Niv 2
### 2.1. Votre rôle
En tant que Documentator de Niveau 2 vous êtes en charge de : 
* Vous assurer que les Documentator de Niv 1 parviennent facilement à contribuer. 
* Déposer régulièrement ou quand c'est nécessaire les contenues édités en collectif sur le github yeswiki.

### 2.2. Accès à github
#### 2.2.1 Généralités
YesWiki comme tout logiciel est constamment en évolution. Ce qui veut dire que chacun des YesWiki est d'une version spécifique. Cette version est écrite sous trois chiffres séparés de points. 

Nous travaillons **toujours** à développer le code ou la documentation sur une version que l'on appelle de "développement" qui est différente de celle utilisée par les utilisateurices au quotidien.

Le "dépôt", c'est-à-dire là où est stocké le code de Yeswiki est sur le site [github.com](https://github.com/). 

👉 [Le dépôt github de Yeswiki est ici.](https://github.com/YesWiki/yeswiki)

?> Pour poursuivre, il vous faudra : 
* Vous créer un compte sur github.com
* Demander à un admin du dépôt github de Yeswiki de vous nommer contributeurice (passer par framateam).

#### 2.2.2 La bonne branche
!> **Attention !**
Un dépôt git est composé de plusieurs branches. 
* La branche principale, où le logiciel distribué et donc utilisé porte le nom des versions majeures de yeswiki (Anacoluthe, Bachibouzouk, Cercophithèque, Dorpyphore,...)
* Une branche de développement qui porte le nom de la version majeure suivie de "dev".
Il faut **toujours** modifier la branche de développement et jamais directement la branche principale.


Pour changer de branche, il convient de passer par le menu déroulant présent en haut à gauche.
![](https://md.yeswiki.net/uploads/ec48249b-7eb8-48c6-ba01-8e27243ed5e2.png)

?>**Exemple**
A l'heure de la rédaction de ce document, nous utilisons la version majeure "Doryphore", ainsi nous travaillerons sur la branche "Doryphore-dev"

L'ensemble des fichiers de documentation se trouvent dans le dossier `/docs` du dépôt.
On y retrouve la série de fichiers en format Markdown (.md) détaillés plus haut.

### 2.3. Comment procéder ?
Deux solutions existent pour modifier la documentation sur le dépôt Github. Le résultat sera le même. A vous de voir laquelle vous conviendra le mieux. 
* **Modifier les fichiers directement sur github** : il est possible directement sur le site de github.com de modifier les fichiers en question. 
* **Modifier les fichiers en local** : Il est possible de copier le dépôt entier en local (sur son ordinateur), de modifier ces documents puis d'envoyer les modifications sur le dépôt en ligne.

#### 2.3.1 Modifier les fichiers directement sur github
##### 2.3.1.1. Les fichiers de docs (Marckdown)
Dans le dossier `/docs` du dépôt github de Yeswiki. 
👉 [Accessible aussi directement ici](https://github.com/YesWiki/yeswiki/tree/doryphore-dev/docs/) 

Accéder au dossier de la langue de votre choix pour voir l'ensemble des fichiers. 

!>**Autre solution :**
On retrouvera en bas de chaque page de doc, un lien pour éditer le fichier sur github.



- Assurez-vous d'être sur la branche `doryphore-dev`

- Une fois que vous avez un compte, vous pouvez cliquer sur l'icone crayon pour éditer un fichier.
![](https://md.yeswiki.net/uploads/b7797fed-d8d2-4382-a7ed-aaa84496a5ad.png)
- Copier le texte édité par les contributeurices sur le HedgeDoc correspondant et le coller dans le fichier.
- Utiliser le mode `Preview` pour vérifier vos changements.
- En bas de la page, renseigner une description de ce qui aura été modifié. Commencer le commentaire par `doc(nom_du_fichier):` puis renseigner une description en anglais si possible.
![image](https://user-images.githubusercontent.com/17404254/191205273-03e18e6c-e22f-4376-b4ba-9c18aa51b1df.png)

?> Il vaut mieux faire des petits changements à la fois, on peut ainsi plus facilement les comprendre et les annuler si besoin

?> On peut visualiser l'historique d'un fichier sur github en cliquant sur `History`
![](https://md.yeswiki.net/uploads/45d42c45-a1d8-454f-b9be-81c1d278a1d0.png)

##### 2.3.2.2. Uploader une image

Si on souhaite ajouter des images dans la documentation, il faut faire 2 choses : 
* Importer l'image dans le répertoire `docs/fr/images` sur github, en cliquant sur `Add File` 
![](https://md.yeswiki.net/uploads/96f4883a-88f5-46ac-8a11-95256690e203.png)
* Ajouter, dans le fichier markdown à l'emplacement où l'on souhaite afficher l'image, le code suivant (remplacer les contenus en fonction de l'image importée): 
`![Texte alternatif](/images/Nom_image.jpg)`



##### 2.3.2.2. Petits plus à connaître

**Gestion du menu**

Le menu à gauche est construit automatiquement via les `## Titres de niveau 2` et `### Titres de niveau 3` utilisés dans la page.

Si vous voulez empêcher un titre d'apparaître dans le menu, rajoutez 
```
<!-- {docsify-ignore} -->
# Mon titre caché <!-- {docsify-ignore} -->
```


**Afficher du code yeswiki**

Utilisez trois balises de code markdown (touche `Alt Gr` + `7`) suivi de "yeswiki"

````
```yeswiki
{{button}}
``'
````

Vous pouvez aussi afficher le rendu de code yeswiki en ajoutant le mot clé "preview". Le chiffre après preview= est la hauteur de la section de rendu

**Exemple de Code Markdown**

````
```yeswiki preview=120
====titre===
{{button link="test" text="Click me" class="btn-primary"}}
``'
````

**Rendu dans la doc**

```yeswiki preview=150
====titre===
{{button link="test" text="Click me" class="btn-primary"}}
```

**Faire référence à une section dans une explication**

Pour renvoyer vers une explication donnée dans un autre paragraphe, il faut cliquer sur le titre de la section concernée dans le site https://testing.yeswiki.net/doc et récupérer dans l'url ce qui est écrit à partir du /docs
Exemple : pour faire référence au paragraphe "la composition d'une page", copier
/docs/fr/prise-en-main?id=la-composition-d39une-page

**Rendu dans la doc**

Pour en savoir plus, aller voir la [composition d'une page](/docs/fr/prise-en-main?id=la-composition-d39une-page)

## 3. Copyleft

Cette documentation est publiée sous licence Creative Commons CCbySA qui permet à qui le souhaite de :

- **Partager**: copier, distribuer et communiquer le matériel par tous moyens et sous tous formats
- **Adapter**: remixer, transformer et créer à partir du matériel pour toute utilisation, y compris commerciale.
- **Selon les conditions suivantes** :
- **Attribuer**: Vous devez créditer l'Œuvre -Collectif YesWiki-, intégrer un lien vers la licence et indiquer si des modifications ont été effectuées à l'Oeuvre.
- **Partager dans les Mêmes Conditions**: Dans le cas où vous effectuez un remix, que vous transformez, ou créez à partir du matériel composant l'Oeuvre originale, **vous devez diffuser l'Oeuvre modifiée dans les même conditions, c'est à dire avec la même licence avec laquelle l'Oeuvre originale a été diffusée - à savoir la licence CCbySA**

Tout ça, dans le but de créer des communs de la connaissance que nous améliorons collectivement.
Merci pour votre diligence :-)
