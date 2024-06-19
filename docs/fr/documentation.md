# Contribuer à la documentation de YesWiki

Ceci est une page d'aide à destination de celleux qui souhaitent contribuer à une excellente documentation utilisateurs pour YesWiki.

## Contexte

Nous souhaitons inclure une documentation dans la version 4.3 de YesWiki, accessible aux utilisateurices de chaque YesWiki sur le même serveur que le wiki. Cette documentation est composée de page en format Markdown, dans le dossier `/docs` dans la base de code, et peut être facilement éditées par les contributeurices ou traducteurices de la documentation.

Cette documentation sera accessible avec le chemin `?doc` sur tous les wikis, exemple `https://yeswiki.net/?doc`

## Architecture

- **Installation** : Là on sort de l'interface web, avec des infos sur l'installation, les fichiers de configuration, la gestion des mails sortants, migration, herse, gestion spams par phpmyadmin et ce genre de choses.
- **Prise en main** : Toute la documentation pour aider l'utilisateur de base a créer un compte, récuperer son mot de passe, editer ou créer une page, comprendre la syntaxe wiki de base, etc...
- **Formulaires (Bazar)** : Toute la doc concernant bazar (création, rendu, templates...)
- **Administration** : Pour celleux qui poussent leur usage du wiki dans ses plus fines fonctionnalités: tout ce qui est dans la roue crantée (et singulièrement la partie dans la page gestion du site dont look du wiki), usage des actions, mise en page, etc, ...
- **Contribuer** : Toutes les possibilités de contribution [participer à la communauté](/docs/fr/communaute.md), [améliorer la documentation](/docs/fr/documentation.md), [développer le code](/docs/fr/dev.md), [adhérer / soutenir financièrement](/docs/fr/asso-finances.md)
- **Extensions** : Partie dynamique de la documentation, qui ajoutera automatiquement des documentations pour les extensions que vous rajouterez (si une documentation existe)
- **Autres espaces** : Liens utiles vers des espaces en ligne comme le forum, les vidéos, les tutoriels,...

## Usages et bonnes pratiques

### Captures d'écran

Il est toujours bienvenu d'avoir des captures d'écran, mais il faut garder à l'esprit que ces captures devront etre refaites pour chaque langue traduite. Il est donc judicieux d'en mettre, mais de conserver un équilibre sur leur quantité. Par ailleurs le widget preview devrait permettre de réduire le nombre de capture d'écrans dans certains cas.

Si cela est possible, il est préférable de directement inclure des rendus de code yeswiki, [voir plus bas](/docsREADME?id=afficher-du-code-yeswiki)

### Ecriture inclusive

Nous essayons d'utiliser une écriture incluse, en utilisant la jonction de termes : `celleux` ou `utilisateurice` par exemple.

Il est également préférable d'utiliser des mots épicènes lorsque cela est possible (exemple élève plutôt qu'étudiant.e)

Plus d'info sur l'écriture inclusive : [https://eninclusif.fr/](https://eninclusif.fr/)

### Ton et langage

Nous devrions dans la documentation adopter un style neutre et factuel. Ca ne veut pas dire qu'il faille etre trop formel, le ton peut etre détendu et parfois spirituel. Il faut juste garder a l'esprit, quand on emploie un style humouristique, que tout le monde n'a pas le meme humour et que l'objet de la doc est de s'adresser a tout le monde.

### Lisibilité

Essayons de rendre la documentation agréable à lire. En évitant les paragraphes trop long, les multiples sous-niveaux. On peut inclure quelques éléments graphiques pour aérer la page, comme les encadrés de message décrits plus bas

## Comment contribuer à écrire de la doc

### Markdown

Apprendre à utiliser ce language : https://www.markdown-cheatsheet.com/

### Modifier les fichiers directement sur github

Allez sur https://github.com/YesWiki/yeswiki/tree/doryphore-dev/docs/ puis le dossier de la langue de votre choix pour voir l'ensemble des fichiers. Sinon, en bas de chaque page de la doc il y a un lien pour éditer le fichier sur github.

- Assurrez vous d'être sur la branche `doryphore-dev`

![](https://md.yeswiki.net/uploads/ec48249b-7eb8-48c6-ba01-8e27243ed5e2.png)

- Une fois que vous avez un compte, vous pouvez cliquer sur l'icon crayon pour éditer

![](https://md.yeswiki.net/uploads/b7797fed-d8d2-4382-a7ed-aaa84496a5ad.png)

- Utiliser le mode `Preview` pour vérifier vos changements
- Allez en bas de la page et renseignez une description de ce que vous avez changé. Commencez votre commentaire par `doc(nom_du_fichier):` puis écrivez une description en Anglais si possible

![image](https://user-images.githubusercontent.com/17404254/191205273-03e18e6c-e22f-4376-b4ba-9c18aa51b1df.png)

> Il vaut mieux faire des petits changements à la fois, on peut ainsi plus facilement les comprendre et les annuler si besoin

### Voir la dernière version de la doc

Allez sur https://testing.yeswiki.net/doc/?doc

A chaque modification sur github, ce yeswiki se mettra automatiquement à jour.

> Si vous ne voyez pas vos changements, recharger la page en vidant le cache (`CTRL` `SHIFT` `R`)

### Historique

On peut visualiser l'historique en ouvrant le fichier sur github et en cliquant sur `History`

![](https://md.yeswiki.net/uploads/45d42c45-a1d8-454f-b9be-81c1d278a1d0.png)

### Uploader une image

Allez dans le répertoite `docs/fr/images` sur github, plus cliquez sur `Add File`

![](https://md.yeswiki.net/uploads/96f4883a-88f5-46ac-8a11-95256690e203.png)

## Petits plus à connaître

### Gestion du menu

Le menu à gauche est construit automatiquement via les `## Titres de niveau 2` et `### Titres de niveau 3` utilisés dans la page.

Si vous voulez empêcher un titre d'apparaître dans le menu, rajoutez `<!-- {docsify-ignore} -->`

`# Mon titre caché <!-- {docsify-ignore} -->`

### Afficher un message en encadré

`> Texte mis en valeur`

> Texte mis en valeur

`?> Texte mis en valeur`

?> Texte mis en valeur

`!> Texte mis en valeur`

!> Texte mis en valeur

### Afficher du code yeswiki

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

### Faire référence à une section dans une explication

Pour renvoyer vers une explication donnée dans un autre paragraphe, il faut cliquer sur le titre de la section concernée dans le site https://testing.yeswiki.net/doc et récupérer dans l'url ce qui est écrit à partir du /docs
Exemple : pour faire référence au paragraphe "la composition d'une page", copier
/docs/fr/prise-en-main?id=la-composition-d39une-page

**Rendu dans la doc**

Pour en savoir plus, aller voir la [composition d'une page](/docs/fr/prise-en-main?id=la-composition-d39une-page)

## Copyleft

Cette documentation est publiée sous licence Creative Commons CCbySA qui permet à qui le souhaite de :

- **Partager**: copier, distribuer et communiquer le matériel par tous moyens et sous tous formats
- **Adapter**: remixer, transformer et créer à partir du matériel pour toute utilisation, y compris commerciale.
- **Selon les conditions suivantes** :
- **Attribuer**: Vous devez créditer l'Œuvre -Collectif YesWiki-, intégrer un lien vers la licence et indiquer si des modifications ont été effectuées à l'Oeuvre.
- **Partager dans les Mêmes Conditions**: Dans le cas où vous effectuez un remix, que vous transformez, ou créez à partir du matériel composant l'Oeuvre originale, **vous devez diffuser l'Oeuvre modifiée dans les même conditions, c'est à dire avec la même licence avec laquelle l'Oeuvre originale a été diffusée - à savoir la licence CCbySA**

Tout ça, dans le but de créer des communs de la connaissance que nous améliorons collectivement.
Merci pour votre diligence :-)
