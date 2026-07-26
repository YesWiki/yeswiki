# Bazar : des bases de données coopératives dans YesWiki

Bazar permet **la création et la gestion de bases de données** pour structurer
des contenus et faciliter leur manipulation par les usagers. La page "bases de
données" (BazaR) est accessible via la roue crantée en haut à droite du menu.

## 0. Introduction - Principe de fonctionnement

Bazar utilise des formulaires qui permettent deux choses :

- faciliter la **saisie** en offrant un cadre structuré de collecte
  d'informations,
- **visualiser** tout ou partie des informations saisies sous une forme qui vous
  semble pertinente (une carte, un trombinoscope, une liste, etc.).

### 0.1. Exemples d'utilisation

A l'installation de votre wiki, quelques formulaires sont présents dans votre
[base de données](BazaR) à titre d'exemple : Agenda, Annuaire, Blog-Actu,
Ressources. Vous pouvez les modifier pour qu'ils correspondent à vos besoins ou
en créer de nouveaux.

### 0.2. Les trois phases de fabrication d'un formulaire

1. **Concevoir** le formulaire,
2. Mettre à disposition une page pour la **saisie** des fiches,
3. Mettre en œuvre une page d'**affichage** des résultats du formulaire.

### 0.3 Présentation de l'interface Bazar

?> Notez qu'il faut être connecté.e avec un compte administrateur du wiki pour
pouvoir utiliser certaines fonctionnalités _Bazar_.

L'écran qui se présente ressemble à ceci (voir ci dessous).  
![image bazar.png (0.1MB)](images/DocBazarFormulaireGestion_formulaire_gestion_20220204173223_20220204163232.png)

L'onglet **Formulaires** se présente sous la forme d'un tableau dans lequel
chaque formulaire présent sur le wiki occupe une ligne.  
Pour chaque ligne, et donc chaque formulaire, on a donc les informations
suivantes (les nombres en rouge sur la capture d'écran correspondent aux numéros
dans la liste ci-après).

**1 –** Le nom du formulaire. C'est le nom sous lequel ce formulaire apparaîtra
pour vous.  
Parfois ce nom est suivi de quelques mots de description (dans l'exemple
ci-contre c'est le cas des formulaires Convive et Ressources)  
**2 –** Ce petit bouton en forme de loupe vous permet d'accéder à la recherche
parmi les fiches de ce formulaire. Le comportement est alors similaire à celui
qu'on aurait avec l'onglet « Rechercher » en haut de page.  
**3 –** Ce petit bouton en forme de « + » vous permet d'accéder à la saisie de
fiches pour ce formulaire. Le comportement est alors similaire à celui qu'on
aurait avec l'onglet « Saisir » en haut de page.  
**4 –** Chacune des icônes ou libellés dans ce groupe permet de déclencher
l'export, la diffusion ou la publication selon le format indiqué.  
**5 –** Il s'agit de l'identifiant (ou nom) du formulaire pour YesWiki. Vous
n'aurez _a priori_ pas à utiliser ce nom.  
**6 –** Ce petit bouton permet de dupliquer un formulaire afin de s'en inspirer
pour en construire un autre en partie similaire sans avoir à tout refaire.  
**7 –** Ce petit bouton en forme de crayon permet d'accéder à la modification du
formulaire.  
**8 –** Cette petite gomme permet de supprimer toutes les fiches du formulaire.
Attention, il n'y a pas de moyen de récupérer des fiches supprimées.  
**9 –** Cette petite poubelle permet de supprimer le formulaire. Attention, il
n'y a pas de moyen de récupérer un formulaire supprimé.  
**10 –** Ce bouton permet de créer un nouveau formulaire.  
**11 –** Vous pouvez, si vous avez repéré sur un autre YesWiki un formulaire qui
vous conviendrait, utiliser ce champ pour saisir l'adresse du wiki en question.
Vous serez ensuite guidés pour récupérer le ou les formulaires qui vous
intéressent sur ce wiki.

**Création et modification d'un formulaire _Bazar_** Pour modifier un formulaire
on utilisera donc le petit bouton en forme de crayon (cf. 7 précédent).  
Et, pour créer un nouveau formulaire, on utilisera le bouton « Saisir un nouveau
formulaire ».  
Les deux boutons envoient sur un écran similaire, vide dans le cas de la
création et présentant les questions du formulaire dans le cas d'une
modification.

## 1. Concevoir le formulaire

Concevoir le formulaire revient à choisir et agencer les différentes questions
que vous allez poser.

?> Si vous partez de zéro, cela peut être une excellente occasion de mettre en
œuvre de la coopération en co-élaborant votre formulaire.

Les questions sont appelés "champs".

Lors de la **conception** vous travaillerez dans la page _Base de données_ de
votre wiki accessible _via_ le menu roue crantée en haut à droite du wiki.

Cette partie explique d'abord comment créer le formulaire (1.1), puis détaille
les types de champs possibles (1.2)

## 1.1. Création du formulaire

### 1.1.1. Nom du formulaire

Ce nom peut être composé de plusieurs mots, comportant éventuellement des
caractères accentués. Il pourra être modifié par la suite.

### 1.1.2. Description du formulaire

Cette zone permet de saisir des explications pour comprendre l'objectif du
formulaire depuis l'écran de gestion des formulaires.

### 1.1.3. Ajout, suppression et réorganisation des champs du formulaire

**Remarque concernant le vocabulaire –** Nous appellerons **« champ »** la mise
en œuvre technique d'une question.  
![image formulaire_constructeur.png (66.3kB)](images/DocBazarFormulaireModification_formulaire_constructeur_vignette_780_544_20220204190135_20220204180220.png)

Ce constructeur graphique se présente en deux parties. Les numéros sur l'image
renvoient aux explications ci-dessous.  
**Dans la partie gauche de l'écran**, sont montrés les champs (ou questions)
déjà présents avec :

- leur libellé (**1**),
- une représentation de leur aspect dans le formulaire final (**2**),
- un petit astérisque rouge si le champ est obligatoire (**3**).

Lorsqu'on déplace le pointeur de la souris au dessus de la zone correspondant au
champ, celui-ci devient une poignée (**4**) qui permet de déplacer le champ pour
le positionner à un autre endroit dans le formulaire.  
Apparaissent également au survol de la souris,

- un bouton de suppression du champ (**5**),
- un bouton de modification du champ (**6**),
- un bouton de duplication du champ (**7**).

**Dans la partie de droite** se trouvent les différents types de champs
possibles (**8**).  
En saisissant, dans la partie droite, l'icône d'un type de champs et en la
glissant dans la partie gauche, on va ajouter un champ de ce type au formulaire.

![image formulaire_constructeur_grab.png (20.0kB)](images/DocBazarFormulaireModification_formulaire_constructeur_grab_20220204191328_20220204181700.png)

Une zone noire apparaît alors à l'endroit où le champ sera inséré. Dans
l'exemple montré, cette zone est placée en dessous du champ préexistant.

![image formulaire_constructeur_drop_field.png (25.3kB)](images/DocBazarFormulaireModification_formulaire_constructeur_drop_field_20220204191854_20220204182701.png)

En relâchant le bouton de la souris, le champ se crée.

![image formulaire_constructeur_champ_creation.png (25.2kB)](images/DocBazarFormulaireModification_formulaire_constructeur_champ_creation_20220204193028_20220204183846.png)

### 1.1.4. Modification d'un champ de formulaire

En cliquant sur le petit crayon correspondant à un champ, on peut modifier ses
différents paramètres.

### 1.1.5. Enregistrer

Lorsque vous avez fini de modifier votre formulaire, vous devez valider au moyen
du bouton du même nom en bas de page.

## 1.2. Paramétrer les champs (questions)

_Bazar_ propose de nombreux types de champs. Voici ceux auxquels vous aurez le
plus souvent recours.

?> **Onglet : code wiki** Lorsque vous modifiez un formulaire, un onglet permet
de consulter le **code wiki** qui a été généré. Il peut être utile de le
consulter lorsque vous avez un problème d'affichage de votre formulaire. Souvent
le problème vient d'un caractère invisible issu d'un copié collé sur les
intitulés des champs. Afficher le code wiki permet de déceler ces caractères
html.

### 1.2.1. Paramètres génériques

Certains paramètres sont génériques à tous ou pratiquement tous les types de
champs. Ils sont repris ici.

- **Obligatoire** : Ce paramètre permet d'indiquer si répondre à cette question
  sera obligatoire. Le champ est obligatoire lorsque la case est cochée.
- **Identifiant unique** : Ce paramètre permet de définir le nom du champ pour
  YesWiki. Ce nom sera utilisé par YesWiki pour identifier le champ et doit donc
  impérativement être unique. Si un autre champ avait le même identifiant dans
  votre formulaire, vous observeriez des dysfonctionnements.  
  Vous n'avez à intervenir sur ce paramètre que dans les rares cas où la
  documentation le spécifie.
- **Intitulé** : Il s'agit du texte de votre question. YesWiki préremplit ce
  paramètre avec le type de champ, charge à vous de remplacer cela par un
  libellé pertinent.
- **Texte d'aide** : Ce paramètre vous permet de saisir un texte d'aide afin
  d'aiguiller l'utilisateur si vous pensez qu'il peut en avoir besoin pour cette
  question.
- **Peut être lu par** : Par défaut, chaque champ peut être lu par toute
  personne ayant le droit de visualiser une fiche du formulaire. Ce paramètre
  permet de modifier ce comportement pour le champ en question. On peut ainsi
  masquer un champ à certains utilisateurs.
- **Peut être saisi par** : Par défaut, chaque champ peut être saisi par toute
  personne ayant le droit de saisir une fiche du formulaire. Ce paramètre permet
  de modifier ce comportement pour le champ en question. On peut ainsi masquer
  un champ à certains utilisateurs.

### 1.2.2. Le seul champ indispensable : le titre

- Il a l'aspect d'un titre (en haut, plus gros, en couleur). Mais on s'y
  attendait.
- Dans une liste de fiches par exemple, seul le titre sera visible pour toutes
  les fiches non « dépliées ».
- Le titre est le seul champ qui soit présenté sans rappel de la question posée
  lors de sa saisie. Par exemple, dans un formulaire dans lequel on utilise le
  prénom comme titre, on aura « Nadine » et non pas « Prénom : Nadine ».

#### Particularités indispensables

Ce champ est un champ de type texte court. Cependant, il a trois particularités
indispensables.  
**1.** Il doit être présent dans tout formulaire.  
**2.** Son paramètre identifiant unique doit nécessairement être « bf_titre ».  
**3.** Il doit nécessairement être obligatoire.  
Vous êtes libres de définir le libellé qui vous convient.

#### Qu'advient-il de ce titre ?

**Lors de la création d'une fiche** par un·e utilisateurice, YesWiki fabrique
une page à partir de cette fiche.  
L'adresse (_url_) de cette page est déterminée automatiquement à partir du titre
de la fiche. Une fiche dont le titre serait « Le titre de ma fiche » donnerait
la page _LeTitreDeMaFiche_. À l'usage, il peut arriver que deux fiches soient
créées avec le même titre. _Bazar_ évite alors les doublons en ajoutant un
nombre à la fin du titre qu'il génère (ici, _LeTitreDeMaFiche1_).

#### Fabriquer un titre à partir de 2 champs

Il est possible d'utiliser un titre combiné : par exemple : "champ prénom +
champ nom". Pour cela utiliser le champ de type
**[Titre automatique](/docs/fr/bazar?id=titre-automatique)**

### 1.2.3. Texte court

Un champs de texte qui permet la saisie de quelques mots.

#### Paramètres spécifiques au type de champs « texte court »

- **Valeur** Ce paramètre permet de pré-remplir le champ. C'est utile lorsque on
  connait la réponse la plus courante (si, par exemple, on demande le pays).
- **Nombre de caractères visibles** : Ce paramètre permet de préciser combien de
  caractères seront visibles à l'écran.
- **Longueur max** : Ce paramètre permet de limiter la longueur de la réponse
  que les utilisateurices peuvent saisir. **NB.** ce champ est par défaut de
  type texte, les autres types disponibles sont :
  - nombre
  - slider <= propose un curseur coulissant entre une valeur minil=male et
    maximale
  - adresse url
  - mot de passe
  - couleur <= propose de choisir une couleur

### 1.2.4. Texte long

Une zone de texte permet la saisie d'un texte relativement long et pouvant
courrir sur plusieurs lignes.

#### Paramètres spécifiques au type de champs « zone de texte »

- **Valeur** : Ce paramètre permet de pré-remplir le champ. C'est utile lorsque
  on connaît la réponse la plus courante (si, par exemple, on demande le pays).
- **Format d'écriture** : Ce paramètre permet de paramétrer les fonctionnalités
  d'écriture dont disposeront les utilisateurices. Trois valeurs sont possibles.
  - **Wiki –** C'est la valeur par défaut. Elle offre pour la saisie de ce champ
    tous les outils disponibles lorsqu'on édite une page YesWiki (liens,
    fichiers joints, composants ...).
  - **Éditeur wysiwyg –** Ce paramétrage offre à l'utilisateurice plus de
    facilité de saisie. Son usage est toutefois à limiter pour des raisons
    d'ergonomie. La barre de mise en forme est plus classique mais n'offre pas
    les options wiki (composants...)
  - **Texte non interprété –** Cette valeur limite la saisie aux seuls
    caractères sans mise en forme (pas d'italique ni de gras par exemple). C'est
    très utile pour saisir des adresses postales.
- **Largeur champ de saisie** : Ce paramètre permet de préciser la largeur du
  champ de saisie.

### 1.2.5. Champ Date

Un champ de type date permet de saisir sans erreur une date. Certains affichages
des résultats (calendrier, agenda, etc.) nécessitent la présence d'un champ
date, ou même deux la plupart du temps pour avoir une date de début et une date
de fin.

#### Paramètres spécifiques au type de champs date

- **Initialiser à Aujourd'hui** : Ce paramètre permet de préciser si on souhaite
  que la date soit prédéfinie à la date du jour.

#### Programmer la récurrence d'un évènement

Afin de permettre à l'utilisateurice, de pouvoir programmer la récurrence d'un
évènement, il conviendra d'insérer deux champs dates dans le formulaire. Leurs
identifiants uniques devront être respectivements :

- `bf_date_debut_evenement`
- `bf_date_fin_evenement`

Lors de la complétion d'une fiche contenant ces deux dates, il est possible de
programmer une récurrence.

- Compléter les dates et horaire d'un évènement. Les suivants seront créés par
  la réccurrence.
- **Récurrence :** Sélectionner la bonne récurence (Une fois par semaine, une
  fois par mois ...)

Si cette veleur est renseignée, les menus suivants s'afficheront : - **Jusqu'au
:** Permet de déterminer une date de fin de la récurrence. - Le bloc suivant
dépendra des réponses aux questions précédente et permettra de sélectionner les
bonnes occurences ou d'en exclure d'autres.

### 1.2.5. Champ Horaires d'ouvertures

Ce champ permet à l'utilisateur de spécifier des horaires d'ouverture d'un lieu.
En mode complétion de fiche, on pourra pour chaque jour compléter les horaires
d'ouvertures.

### 1.2.7. Image

Un champ de type image permet d'importer un fichier image qui sera ensuite
visualisable dans la fiche. Il est possible de définir une image par défaut.

#### Paramètres spécifiques au type de champs « image »

- **Hauteur vignette** : YesWiki génère une vignette des images afin de les
  afficher rapidement si besoin. Ce paramètre permet de préciser la hauteur de
  cette vignette.
- **Largeur vignette** : YesWiki génère une vignette des images afin de les
  afficher rapidement si besoin. Ce paramètre permet de préciser la largeur de
  cette vignette.
- **Hauteur re-dimension** : YesWiki peut harmoniser la taille des images
  importées pour ce formulaire. Ce paramètre permet de préciser la hauteur de
  cette image redimensionnée.
- **Largeur re-dimension** : YesWiki peut harmoniser la taille des images
  importées pour ce formulaire. Ce paramètre permet de préciser la largeur de
  cette image redimensionnée.
- **Alignement** : C'est là que l'on paramètre le comportement d'affichage de
  l'image. Son fonctionnement est similaire à ce qui se passe dans l'édition de
  pages (quand on joint une image avec le bouton Fichier).
- **Taille max** Ce paramètre permet de limiter la taille du fichier. Il s'agit
  d'un nombre d'octets mais qui peut être écrit avec des préfixe d'unités : k
  pour kilo, m pour mega (par ex.: 2097152, 2048k, 2m). Si la valeur donnée
  dépasse la valeur configurée sur le serveur, la valeur du serveur sera prise.

### 1.2.8. URL

Permet de saisir un lien web qui sera cliquable dans la fiche Vous pourrez
demander, si le lien proposé est celui d'une vidéo, que celle ci s'affiche
automatiquement

### 1.2.9. Upload de fichier

Ce type de champ permet d'uploader un fichier (par exemple au format PDF). Ce
fichier est ensuite téléchargeable par les personnes qui visualisent la fiche.

#### Paramètres spécifiques au type de champs « upload de fichier »

- **Taille max** Ce paramètre permet de limiter la taille du fichier. Il s'agit
  d'un nombre d'octets mais qui peut être écrit avec des préfixe d'unités : k
  pour kilo, m pour mega (par ex.: 2097152, 2048k, 2m). Si la valeur donnée
  dépasse la valeur configurée sur le serveur, la valeur du serveur sera prise.

### 1.2.10. Email

Ce type de champs permet de saisir une adresse électronique. YesWiki effectue
automatiquement des contrôles sur la syntaxe de l'adresse et propose également
de paramétrer des comportements spécifiquement liés à ce type de données.

#### Paramètres spécifiques au type de champs « email »

- **Remplacer l'email par un bouton contact** En sélectionnant « oui » pour ce
  paramètre, on fait en sorte que l'adresse électronique soit remplacée, lors de
  l'affichage de la fiche, par un bouton qui renvoie vers un formulaire de
  contact automatiquement généré. L'email n'est donc pas visible par les
  personnes qui visualisent la fiche.
- **Affichage brut de l'email autorisé pour** Ce paramêtre permet d'indiquer qui
  a le droit de voir l'email saisi depuis ce champ dans des tableaux et dans les
  affichages de la fiche sans bouton contact
- **Envoyer le contenu de la fiche à cet email** Ce paramètre permet de demander
  à YesWiki d'envoyer le contenu de la fiche à l'adresse saisie. Cet envoi se
  fera lorsque la personne aura validé la saisie de la fiche. **Astuce** : Il
  est possible d'ajouter un contenu personnalisé dans le corps du mail via la
  page Fichier de configuration dans Gestion du site. Pour cela : insérer votre
  contenu (avec mise en forme possible en HTML) au niveau du paramètre
  `Message personnalisé des mails envoyés depuis l'action contact - mail_custom_message`
  **Pour aller plus loin dans la personnalisation des mails envoyés** : pour les
  développeurs Il est possible d'adapter les messages affichés dans les e-mails
  en copiant les templates associés depuis
  templates/notify-email-\*.twig dans custom/templates/core/
  puis en modifiant le contenu de ces modèles (syntaxe twig)

### 1.2.11. Proposer des choix entre plusieurs possibilités

Les 3 types de champs suivants permettent de proposer à l'utilisateur une liste
fermée de choix.

En premier lieu, il faut donc pouvoir énumérer les différentes valeurs
possibles. Cela se fait directement dans le paramétrage du champ :

- **Origine des données** : dans ce paramètre, pour permettre à l'utilisateurice
  de choisir parmi les valeur d'une liste, sélectionnez « une liste ». Si, au
  contraire, vous souhaitez permettre à l'utilisateurice de choisir parmi des
  fiches d'un autre formulaire, sélectionnez « Un formulaire Bazar ».
- **Choix de la liste/du formulaire** : avec ce paramètre vous choisissez la
  liste (ou le formulaire) à partir de laquelle vous souhaitez que les
  utilisateurices choisissent.
- **Valeur par défaut** : ce paramètre vous permet de proposer une valeur par
  défaut. Attention, pour préciser la valeur par défaut, il faut indiquer sa
  clef dans la liste (et non pas sa valeur).

Il est également possible de créer des **listes à deux niveaux**. Par exemple le
premier niveau peut être des régions, et le second niveau les départements. Cela
permettra lorsqu'une région est choisie de ne proposer ensuite qu'un choix parmi
les départements rattachés à la région.

?> L'utilisation de ce type de champ permettra ensuite de proposer aux
utilisateurices de filtrer les fiches parmi celles qui sont remplies (cf.
[facettes](/docs/fr/bazar.md#afficher-des-filtres-facettes)).

#### 1.2.11.1. Liste déroulante

Les choix possibles seront présentés sous forme d'une liste déroulante : une
seule valeur pourra être sélectionnée par l'utilisateurice.

#### 1.2.11.2. Cases à cocher

Les choix possibles seront présentés sous forme de cases à cocher : plusieurs
valeurs pourront être sélectionnées par l'utilisateurice.

#### 1.2.11.3. Boutons radio

Les choix possibles seront présentés sous forme d'un groupe de boutons radio :
cela se présente comme les cases à cocher mais l'utilisateurice ne pourra
choisir qu'une option parmi la liste.

### 1.2.12. Géolocalisation de l'adresse

![image champ_zone.png (24.5kB)](images/DocBazarChampGeo_champ_geoloc.png)

Ce champ est un outil qui permet de transformer une adresse saisie en un jeu de
coordonnées (longitude et latitude).  
Son comportement est donc un peu différent de ce qu'on trouve dans les autres
champs.

Afin que YesWiki puisse définir les coordonnées géographiques d'un point, votre
formulaire doit obligatoirement contenir au moins un des champs suivants :

- **« bf_adresse »** : Une adresse, numéro et nom de rue (Il est inséré
  automatiquement lors de la création du champ « géolocalisation de l'adresse »)
  ;
- **« bf_ville » :** Nom de la ville ;
- **« bf_codepostal » :** Code postal ;
- **« bf_pays » :** Nom du pays.

Il est indispensable d'avoir un champ de ce type dans votre formulaire si vous
souhaitez afficher vos résultats sous forme de carte.

#### Paramètres du type de champs « géolocalisation de l'adresse »

- **Nom Champ Latitude** Sauf besoin précis, conservez la valeur par défaut qui
  est « bf_latitude ».
- **Nom Champ Longitude** Sauf besoin précis, conservez la valeur par défaut qui
  est « bf_longitude ».
- **Champ code postal pour l'autocomplétion** : s'assurer que le formulaire
  possède un champ texte court pour le code postal et noter son nom (ex. :
  bf_codepostal). Ainsi quand vous allez taper un code postal puis sélectionner
  la ville associée, la géolocalisation sera automatiquement mise à jour.
- **Champ ville pour l'autocomplétion** : s'assurer que le formulaire possède un
  champ texte court pour la ville et noter son nom (ex. : bf_ville). Ainsi quand
  vous allez taper un nom de ville puis sélectionner le code postal associé, la
  géolocalisation sera automatiquement mise à jour.

- Possibilité d'activer la géolocalisation depuis la position de l'ordi ou du
  GSM

- Possibilité d'activer l'affichage d'une carte de localisation dans la fiche

#### Type d'objets

Depuis la version Doryphore 4.6, il est désormais possible en mode édition de
fiche, de placer des objets différents que de simples points. Pour y parvenir il
faut :

- En mode édition du formulaire dans les paramètres avancés dans le paramètre
  "Formes autorisées" renseigner les formes que l'on va mettre à disposition de
  la personne qui va compléter la fiche. Ecrire les formes autorisés (parmi :
  marker, line, polygon, rectangle, circle) en les séparants par des virgules.

Il est maintenant possible de placer sur les cartes :

- **Des points (marker)**
- **Des lignes (line) :** Une ligne peut être un simple segment (2 points) comme
  une suite de segments (plusieurs points).
- **Des polygones (polygon) :** Suite de segments fermés. On placera l'un après
  l'autre les points qui formeront les sommets du polygone. Pour fermer la
  polygone cliquer sur la premier point.
- **Des rectangles (rectangle)**
- **Des cercles (circle)**

!>Par défaut seul les points (marker) sont actifs.

?>**Exemple :** Pour utiliser les polygones et les points, renseigner dans les
paramètres avancés de la géolocalisation en mode édition de formulaire les
aramètres suivants : `marker,polygon`

!>Si vous n'entrez que `polygon` il sera impossible en édition de fiche de
placer un simple point.

### 1.2.13. Mots clés

Possibilité d'ajouter des mots clés en les séparant par un clic sur la touche
entrée

### 1.2.14. Custom HTML/Wiki

Le champ custom html/Wiki permet d'insérer un texte, un titre, un lien, ou tout
autre contenu qui ne sera pas éditable en mode édition de fiche mais seulement
en mode édition de formulaire.

Il est destiné à compléter les intitulé des champs à informer l'utilisateurice,
à ajouter des informations.

Le menu déroulant (mode édition de formulaire) intitulé : "Utiliser la syntaxe
wiki plutot que du HTML" permet de choisir la synthaxe d'écriture.

?> Depuis la version 4.6 de Doryphore il est possible de renseigner cela en wiki
et plus uniquement en HTML.

Deux champs permettent de renseigner du texte :

- le contenu qui sera affiché lors de la saisie (cela peut être une information
  destinée à expliquer à l'utilisateur ce qu'on attend comme élément )
- le contenu affiché lors de la consultation d'une fiche

?>Pour avoir le même contenu à l'édition et à la consultation d'une fiche,
renseigner le même contenu dans les deux champs.

#### Quelques exemples (en HTML) de fonctionnalités avancées

?> Le plus simple maintenant est d'écrire cela avec la synthaxe habituel de
YesWiki (voir la documentation de prise en main)

##### Ajout d'un encadré dans un formulaire

```html
<div style="border: 1px solid #EE784B;padding:0px 20px 5px 20px;">
  Texte à l'intérieur du cadre
</div>
```

Si on veut mettre des champs à l'intérieur du cadre : il faut arrêter le label
html après `0px 20px 5px 20px;">` et insérer un second label html pour fermer
l'encadré après les champs concernés avec `</div>`

##### Ajout d'un accordion dans un formulaire

```html
<div
  class="panel-group"
  id="accordion"
  role="tablist"
  aria-multiselectable="true"
>
  <div class="panel panel-default">
    <div class="panel-heading" role="tab" id="headingOne">
      <h4 class="panel-title">
        <a
          role="button"
          data-toggle="collapse"
          data-parent="#accordion"
          href="#collapseOne"
          aria-expanded="true"
          aria-controls="collapseOne"
        >
          Titre de l'accordion</a
        >
      </h4>
    </div>
    <div
      id="collapseOne"
      class="panel-collapse collapse"
      role="tabpanel"
      aria-labelledby="headingOne"
    >
      <div class="panel-body">
        Texte à inscrire à l'intérieur de l'accordion
      </div>
    </div>
  </div>
</div>
```

Si on veut mettre un ou des champs (par exemple un champ "cases à cocher") à
l'intérieur de l'accordion : il faut arrêter le label html après
`<div class="panel-body">` et insérer un second label html pour fermer
l'accordion après les champs concernés avec`</div></div></div></div>`

##### Ajout d'un petit bouton dans un formulaire pour ouvrir une autre page

Cette fonctionnalité est utile quand il y a une liste de personne dans le
formulaire et que les personnes doivent s'ajouter dans la liste avant de pouvoir
remplir le formulaire exemple =>
<https://pimp-ta-balade.be/?BazaR&vue=saisir&action=saisir_fiche&id=7>
Figurez-vous déjà dans la liste ? (ci-dessous).

```html
<a href="URLCOMPLETE" class="btn btn-primary btn-xs">
  Si vous n'êtes pas dans la liste, cliquez ici avant d'aller plus loin
</a>
```

### 1.2.15. Titre automatique

Il est possible d'utiliser un titre combiné à partir de 2 champs (ou plus) : par
exemple : "champ prénom + champ nom". Dans le paramètre **valeur** mettre les
identifiants uniques des champs que l'on souhaite utiliser : {{bf_nom}}
{{bf_prenom}} . Vous pouvez également y ajouter du texte : par exemple mettre un
tiret entre le nom et le prénom. !> Si vous utilisez un titre automatique, il
faudra supprimer le champ bf_titre créé par défaut.

### 1.2.16. Bookmarklet

Ce champ spécial génère un bouton qui sera affiché dans votre formulaire de
saisie. En glissant le bouton vers la barre de raccourci du navigateur, les
utilisateurs pourront bénéficier d'un raccourci pour faire une veille partagée.

### 1.2.17. Affichage conditionnel

Ce champ permet d'afficher certaines questions en fonction de la réponse
apportée à une des questions précédentes. Par exemple : lorsque lʼutilisateur
répond « autre » à une liste, on lui propose alors un champ texte pour préciser.
**La question conditionnelle fait donc suite à une question de type Liste
déroulante, Cases à cocher ou Radio.** Lorsque vous insérez un « Affichage
conditionnel » dans votre formulaire, deux champs sont créés : le premier,
intitulé « Condition », le second intitulé « Fin de condition ». Vous devez
placer, entre le champ « Condition » et le champ « Fin de condition », le ou les
champs que vous souhaitez faire apparaître de manière conditionnelle.

#### Paramètres spécifiques au type de champs « question conditionnelle »

- **Condition** Ce paramètre définit la condition à respecter pour afficher les
  champs qui suivent (jusquʼà « Fin de condition »). Voici quelques exemples
  pour illustrer la syntaxe. On suppose quʼon dispose d'un champ de type «
  Sélectionner » (ou radio ou checkbox) dont l'**identifiant unique** est
  _bf_champ_ | Pour afficher si… | valeur du paramètre | |---|------| |on a
  répondu « autre »|`bf_champ==autre`| |on nʼa pas répondu « autre
  »|`bf_champ!=autre`| |on nʼa répondu ni « 1 », ni « 5
  »|`bf_champ!=1 and bf_champ!=5`| |on nʼa pas répondu du
  tout|`bf_champ is empty`| |on a répondu « 1 » ou « 2 » (pareil quʼen
  dessous)|`bf_champ==1 or bf_champ==2`| |on a répondu « 1 » ou « 2 » (pareil
  quʼau dessus)|`bf_champ in [1,2]`| |dans le cas dʼune checkbox, on a répondu «
  1 » et « 2 »|`bf_champ==[1,2]`| |dans le cas dʼune checkbox, moins de trois
  cases ont été cochées|`bf_champ\|length < 3`| |dans le cas dʼune checkbox, au
  moins deux cases ont été cochées mais pas la case « autre
  »|`bf_champ\|length > 2 and bf_champ!=5`|

- **Masquer à l'affichage** :
  - valeur par défaut :`Effacer` supprime le champ et les valeurs éventuellement
    saisies precedemment dedans,
  - autre valeur :`ne pas effacer` permet de cacher le champ et les valeurs et
    on retrouverait les valeurs si le champs réapparaissait

### 1.2.18. Calculs

Ce champ permet de réaliser un calcul mathématique à l'enregistrement de la
fiche. Le résultat sera visible après avoir sauvé la fiche.

#### Paramètres spécifiques du champ calcul

- **Texte d'affichage** : permet d'ajouter un symbole après la valeur si
  nécessaire - exemple : `{value}€`
- **Formule** : pour faire référence à un nombre saisi dans le formulaire
  utilisez son identifant - exemple : `bf_nombre*15`

### 1.2.19. Réactions

Permet au rédacteur de la fiche d'ajouter la possibilité de réagir au contenu et
pour le lecteur identifié de réagir sur en cliquant sur des propositions Par
défaut, vous aurez les propositions suivantes
![image reactions (24.5kB)](images/reactions.png) Elles sont modifiables et
agissant sur les champs

- Noms des réactions (séparées par des virgules)
- Noms des fichiers d'images (séparées par des virgules) vous pouvez vous
  inspirer du composant réactions afin de repérer les possibilités notamment au
  niveau des illustrations :far fa-grin,far fa-angry

### 1.2.20. Inscription liste de diffusion

- Email d'inscription fourni par le service ex. <liste-subscribe@framaliste.org>
- Type de Service de Diffusion : sympa ou mailman

### 1.2.21. Créer un utilisateur lorsque la fiche est validée

Ce champ est utile pour créer un compte utilisateur à partir des informations
contenues dans le formulaire.

- Champ pour le nom d'utilisateur
- Champ pour l'email de l'utilisateur
- Auto. Synchro. e-mail
- Groupes où ajouter l'utilisateur

Tips : ce champ peut être associé à l'option **Config droits d'accès** afin de
permettre à ce nouvel utilisateur de modifier sa propre fiche par la suite.

### 1.2.22. Config droits d'accès

Ce champ n'est pas un vrai champ. Il s'agit d'un outil qui permet de définir les
droits d'accès qui seront affectés à chacune des fiches du formulaire. Vous
pouvez donc ainsi préciser quelles catégories d'utilisateurs (Tout le monde,
Utilisateurs identifiés, Membres du groupe admins, ou Propriétaire de la fiche
et admins) peuvent lire, saisir ou modifier ou encore commenter des fiches de
votre formulaire.

!> Cette configuration des droits d'accès ne s'applique qu'aux fiches saisies
après son paramétrage. Autrement dit, si vous ajoutez ce « champ » à votre
formulaire, ou si vous le modifiez, seules les fiches saisies ou modifiées après
cet ajout, ou cette modfication, auront les droits que vous avez définis.

### 1.2.23. Config thème de la fiche

Permet de définir un thème graphique spécifique à associer à toutes les fiches
du formulaire, cela peut être un jeu de couleur que vous définissez dans la page
LookWiki

### 1.2.24. Liste des fiches liées

Ce type de champs est utilisable dans le cas où un autre formulaire est lié à
celui-ci. Il permet d'afficher les fiches liées. Son effet n'est visible que
dans la phase 3 d'affichage des résultats du formulaire.

?> **Exemple :** Un auteur a écrit plusieurs livres. On peut avoir un formulaire
auteur (identifiant 1) et un formulaire livre (identifiant 2). Le formulaire
livre contiendra un champ liste basé sur le formulaire auteur pour identifier
son auteur Le formulaire auteur pourra contenir un champ liste des fiches liées
afin d'afficher dans la fiche auteur tous les livres qui ont été écrits par
l'auteur .

#### 1.2.24.1 Paramètres spécifiques au type de champs « liste des fiches liées »

- **Id du formulaire lié** : Ce paramètre, obligatoire, doit contenir
  l'identifiant Bazar du formulaire lié. Le formulaire lié est celui qui
  contient la référence au formulaire courant (via une liste déroulante, des
  cases à cocher ou des boutons radio)
- **Query** : Ce paramètre permet de n'afficher qu'une partie des fiches liées.
  Il est facultatif, par défaut, toutes les fiches liées s'afficheront. La
  syntaxe du paramètre query est la même que dans bazar liste - voir
  [afficher une partie des données - query](/docs/fr/bazar?id=afficher-une-partie-des-donn%c3%a9es-query)
- **Params de l'action** : permet l'ordre d'affichage des fiches liées. Par
  défaut elles sont triées par ordre alphabétique croissant sur le champ
  titre.On peut modifier le champ de référence pour le tri ainsi que l'ordre de
  tri - voir [ordre et champ](/docs/fr/bazar?id=ordre-et-champ) On notera que
  les deux paramètres sont séparés par un espace.
- **Nombre de fiches à afficher** vous pouvez préciser ici le nombre de fiches
  liées à afficher
- **Template de restitution** Par défaut, les fiches liées s'afficheront sous
  forme de liste en accordéon. Pour utiliser un autre template saisir dans cette
  zone le nom du template, par exemple : _trombinoscope.tpl.html_
- **Type de fiche liée (ou label du champ)** Vous devez préciser ici le type de
  champ utilisé dans le formulaire lié pour effectuer cette liaison. « liste »
  pour une liste déroulante. « checkbox » pour un groupe de cases à cocher. «
  radio » pour un groupe de boutons radio.

### 1.2.25. Custom

Ce champ sera utile pour les développeurs qui ont recours à un champ custom.
Plus de détails (dans la section
développeurs)[/docs/fr/dev?id=custom-bazar-field]

### 1.2.26. Navigation par onglet /Passage à l'onglet suivant

Il est possible de découper le formulaire en plusieurs onglets pour rendre le
formulaire plus lisible.

## 2. Permettre la saisie des fiches

Pour permettre la **saisie des fiches**, insérez le formulaire de saisie dans la
page wiki de votre choix via le bouton composant **Afficher un formulaire de
création de fiche**. Dans les options avancées, vous pouvez choisir le **Nom de
la page de ce wiki à afficher après création d'une fiche** pour renvoyer vers
une page préparée par vos soins suite à la saisie d'une fiche.

## 3. Afficher les résultats du formulaire

Le composant **Afficher les données d'un formulaire** permet d'insérer une
visualisation des fiches qui ont été saisies. Plusieurs types d'affichages sont
possibles (voir la partie 3.1) : liste simple, blocs, carte, calendrier, agenda,
annuaire, carrousel photo, tableau,...

Le composant permet de choisir parmi ces différentes visualisations et affiche
un aperçu qui permet de voir facilement les rendus. Chaque type de
visualisations propose des paramétrages dont une partie peut être commune à
différentes visualisations (voir la partie 3.2).

### 3.1. Informations sur quelques visualisations

#### 3.1.1. Blocs

L'affichage sous forme de bloc est le plus souple d'utilisation. Son paramétrage
permet de personnaliser l'affichage pour mettre en valeur vos données. Le
constructeur graphique permet de lister les options possibles.

#### 3.1.2. Carte

- **Cluster et facettes** Les options affichage en cluster et filtre par facette
  ne sont compatibles qu'en activant le rendu dynamique
- **Afficher les contours de département** (à completer)

### 3.2. Paramètres des visualisations

#### 3.2.1. Afficher des filtres (facettes)

Les facettes permettent d'afficher des filtres à coté de vos données,
l'utilisateurice pourra cocher un ou plusieurs pour afficher une sélection de
données. Ces filtres sont basés sur les listes, les cases à cocher et les
boutons radios. Les filtres peuvent être configurés via l'interface composant
**Afficher les données d'un formulaire**

#### 3.2.2. Tri dynamique des fiches

Accessible en mode avancé lors du paramétrage du composant "Afficher les données
d'un formulaire". L'option "tri dynamique" permet à l'utilisateurice de trier
les fiches par n'importe quel champ (titre, date,...).

Il conviendra lors du paramètrage de l'affichage du composant de spécifier quels
champs peuvent servir à l'utilisateurice à trier.

#### 3.2.3. Afficher une partie des données (query)

Dans certains cas, il peut être nécéssaire de n'afficher qu'une seule partie des
fiches d'un formulaire.

> Par exemple : On dispose d'une base d'informations où chaque fiches contient
> une adresse (code postale et ville). Dans ce cas on peut d'une page à l'autre
> n'afficher qu'une partie de ces fiches avec par exemple, une page par
> département.

!> Ce qui suit ne peut à ce jour pas être fait avec l'interface graphique.

Ceci se fait dans le code de la page en ajoutant le paramètre `query` à
l'objet`{{bazarliste ...}}`. Ce paramètre sera suivi d'une condition.

> Par exemple dans le code `{{bazarliste id=2 query="bf_departement=29"}}`
> Pourrait se lire : Afficher les fiches du formulaire "2" pour lesquelles la
> valeur du champ "bf_departement" est égal à 29. Afficher uniquement les fiches
> qui concernent le Finistère (29).

Avant de rentrer dans les exemples, explorons les oppérateurs logiques qui
permettent de comparer des valeurs ou de réaliser plusieurs actions en même
temps.

Les deux symboles ci-dessous s'emploient entre deux expressions et permettent de
vérifier si une des deux conditions est vraie (ou), ou si les deux conditions
sont vraies (et).

- Le symbole `|` (et logique) permet de vérifier que plusieurs conditions sont
  vrais.
- Le symbole `,` (ou logique) permet de vérifier que au moins une des conditions
  est vrais.

Quelques expressions régulières simples à connaitre.

- `.*Toto` permettra de renvoyer les chaines de caractères se terminant par
  "Toto".
- `Toto.*` permettra de renvoyer les chaines de caractères commençant par
  "Toto".
- `.*Toto.*` permettra de renvoyer les chaines de caractères qui contiennent
  "Toto".

> **Pour aller plus loin :** Il est possible d'utiliser les expressions
> régulières de "MySQL"

Les oppérateurs logiques suivants s'emploient pour évaluer la relation entre un
champ et la valeur qu'il contient.

- `=` (ou enciennement `==`) pour une égalité. _ex : `bf_departement=29`, la
  condition est vraie si le champ departement contient le nombre "29"_
- `<` Strictement inférieur à. _ex : `bf_departement<29`, la condition est vraie
  si le champ departement est inférieur à "29"_
- `>` Strictement supérieur à
- `<=` Inférieur ou égal à
- `>=` Supérieur ou égal à
- `!=` Différent de

##### Quelques exemples

- `query="bf_description=.*toto"` : Renverra les fiches dont le champ
  "bf_description" se termine par "toto"
- `query="bf_description=toto.*"` Renverra les fiches dont le champ
  "bf_description" commençant par "toto"
- `query="bf_description=.*toto.*tata.*"` : Renverra les fiches dont le champ
  "bf_description" contient "toto" suivi de "tata".
- `query="bf_description=/Ta+To?/"` : Renverra les fiches dont le champ
  "bf_description" contient un "T" suivi de au moins un caractère "a" suivi de
  "T" et éventuellement "o"
- `query="bf_age>18"` Renverra les fiches dont le champ "bf_age" est supérieur
  à 18.
- `query="bf_age >= 20  | bf_age < 40` : Renverra les fiches dont le champ
  "bf_age" est supérieur ou égal à 20 et strictement inférieur à 40.
  query="bf_nom=/.*toto/, /.*tata.\*/ | bf_age < 18" : (bf_nom finit par toto OU
  contient tata) ET bf_age < 18

- `query="listeListeGenre=M|listeListeDep=26"` : On peut aussi filtrer selon
  plusieurs champs. Ici la fche sera affiché si les deux conditions sont vraies.

#### 3.2.4. Ordre et champ

- **ordre** Permet d'afficher la liste par ordre croissant ou décroissant. Par
  défaut : rangé par ordre croissant (asc) sinon mettre "desc" pour l'ordre
  décroissant
- **champ** Permet de choisir le champ utilisé pour le tri. Par défaut : tri sur
  le champ titre (bf_titre). Par date par ex : `champ="date_creation_fiche"` ou
  `champ="date_maj_fiche"`

#### 3.2.5. Random

Permet d'afficher une sélection aléatoire de fiches `random="1"` en général on
l'utilise avec le paramètre **nb** `nb="5"` pour afficher 5 ressources au hasard
à mettre en valeur.

#### 3.2.6. Données issues d'un autre yeswiki

Il est possible d'afficher les données issues d'un YesWiki distant.

1. Définir l'action `{{bazarliste id="1" template="map" ...}}` en utilisant le
   bouton **composants** "Afficher les données d'un formulaire" lors de la
   modification d'une page
2. Identifier l'adresse des ""YesWiki"" distants et les formulaires recherchés.
   Ex: sur le formulaire 4 sur wiki <https://example.com> et le formulaire 5 sur
   le wiki <https://example.com/trombi2/>
3. remplacer l'identifiant du formulaire dans l'action bazarliste id par
   `{{bazarliste id="1,https://example.com|4,https://example.com/trombi2|5"}}`
4. Sauver la page et enjoy

##### Explications

- un formulaire local est uniquement représenté par un nombre. Dans l'exemple,
  nous avons les formulaires 1 et 6
- un formulaire distant est représenté par son url suivi de `|` suivi du numéro
  de son formulaire. Dans l'exemple, nous avons deux formulaires distants.
- plusieurs formulaires peuvent être appelés depuis une même action bazarliste,
  chaque formulaire est séparé par une virgule
- S'il faut plusieurs formulaires distants d'un même YesWiki, il faut à chaque
  fois répéter l'url devant `|`

##### Rafraichir les données locales

Il y a un système de cache des requêtes externes dont la durée est paramètrable
par les variables //baz_external_service_time_cache_for_entries// et
//baz_external_service_time_cache_for_forms//
([voir ici](https://github.com/YesWiki/yeswiki/blob/doryphore/tools/bazar/config.yaml#L106).
Pour forcer un rafraîchissement des données, il faut être connecté et ajouter à
la fin de l'url : %%&refresh=1%%

##### Tips avancés

**Avoir des couleur différentes par formulaire (entre données du formulaire
local et distants) :** Sur la base du fonctionnement colorfield="id_typeannonce"
(voir ActionBazarliste section color), definir un ID pour le formulaire externe
n'existant pas en local (999 par exemple) de la manière suivante
`id="5,http://www.exemple.com/?PagePincipale|1->999" color="green=5, blue=999"`
NB : Dans l'exemple ci-dessus l'id du formulaire local est 5 et celui du
formulaire distant 1

##### Pour aller plus loin, pour les personnes connaissant les fields

- pour configurer l'affichage des données sur le site local, il faut plutôt
  créer un formulaire qui ressemble au formulaire distant (même nom de champs)
  mais avec vos adaptations
- noter le numéro de ce formulaire en local (A pour l'exemple)
- noter le numéro du formulaire distant (B pour l'exemple)
- entrer dans id ceci `id="http://www.exemple.com/?PagePrincipale|B->A"` Tout se
  joue avec l'association de B vers A.

##### Pour lier à un template custom fiche-x.tpl.html

//x étant le numéro du formulaire local concerné//

1. dupliquer le formulaire distant sur le ""YesWiki"" local en utilisant la
   fonctionnalité d'importation disponible en bas de la page ""BazaR""
2. copier le fichier //fiche-x.tpl.html// dans le dossier local
   //custom/templates/bazar/// avec le nom //fiche-y.tpl.html// où y est le
   numéro du formulaire dupliqué en local
3. modifier le formulaire y en local en mode code en remplaçant, //z étant le
   numéro du formulaire distant//

```text
%%liste**_..._**...**\* \*** **_...%% par
%%externalselectlistfield_**...**_..._**
https://www.example.com/?BazaR/json&demand=forms&id=y***liste***...%%
%%listefiche**_..._**...**\* \*** **_...%% par
%%externalselectentryfield_**...**_..._**
https://www.example.com/?BazaR/json&demand=forms&id=y***listefiche***...%%
%%listefiches**_..._**...**\* \*** **_...%% par
%%externallinkedentryfield_**...**_..._**
https://www.example.com/?BazaR/json&demand=forms&id=y***listefiches***...%%
%%listefichesliees**_..._**...**\* \*** **_...%% par
%%externallinkedentryfield_**...**_..._**
https://www.example.com/?BazaR/json&demand=forms&id=y***listefichesliees***...%%
%%checkbox**_..._**...**\* \*** **_...%% par
%%externalcheckboxlistfield_**...**_..._**
https://www.example.com/?BazaR/json&demand=forms&id=y***checkbox***...%%
%%checkboxfiche**_..._**...**\* \*** **_...%% par
%%externalcheckboxentryfield_**...**_..._**
https://www.example.com/?BazaR/json&demand=forms&id=y***checkboxfiche***...%%
%%radio**_..._**...**\* \*** **_...%% par
%%externalradiolistfield_**...**_..._**
https://www.example.com/?BazaR/json&demand=forms&id=y***radio***...%%
%%tags**_..._**...**\* \*** **_...%% par %%externaltagsfield_**...**_..._**
https://www.example.com/?BazaR/json&demand=forms&id=y***tags***...%%
%%fichier**_..._**...**\* \*** **_...%% par %%externalfilefield_**...**_..._**
https://www.example.com/?BazaR/json&demand=forms&id=y***fichier***...%%
%%image**_..._**...**\* \*** **_...%% par %%externalimagefield_**...**_..._**
https://www.example.com/?BazaR/json&demand=forms&id=y***image***...%%
```

Dans l'exemple:

- le formulaire concerné est `https://www.example.com|z`
- la formule entrée `{{bazarliste id="https://www.example.com|z->y"}}`

//Si dans votre formulaire local vous voulez un comportement correct pour les
liens, inspirez-vous des
[[https://github.com/YesWiki/yeswiki/tree/doryphore/tools/bazar/fields externalfields]],
comme par exemple ://

- pour les urls vers les fiches %%$fiche['url']%%
- pour les urls vers les fiches avec un handler %%$fiche['url'] . '/pdf'%%
- pour savoir si la fiche est externe %%isset($fiche['external-data'])%%
- pour avoir l'url de base du site distant pour les fiches externes
  `$fiche['external-data']['baseUrl']`

## 4. Importer / exporter des données

Afin de faciliter l'importation d'un certain nombre de fiches à la fois, il est
possible d'importer dans un formulaire bazar des données organisés sous forme
d'un tableur par exemple. Ou encore d'exporter des données d'un formulaire bazar
pour les utiliser ensuite dans un tableur.

Prérequis :

- Disposer des accés administrateurs.
- Avoir accés à un logiciel de tableur tel que libre office calc.

### 4.1. Étapes pour importer des données

- Se rendre sur la page permettant la gestion des formulaires (elle contient
  l'action{{bazar}}). Par défaut cette page se trouve en joignant "/?Bazar" à
  l'URL de votre wiki tel que <https://monwiki.net/?Bazar>
- Cliquer sur l'onglet **importer**
- Choisir parmi les formulaires celui dans lequel on souhaite importer des
  données

![image importbazzar1.png (34.6kB)](images/BazarImportExport_importbazzar1_vignette_780_544_20160405102322_20160405102344.png)

- le wiki fournit alors diverses infos sur la structure du fichier nécessaire
  pour permettre une bonne importation
- le wiki fournit un fichier type vide au format CSV comme exemple. Il est
  possible de télécharger cette exemple puis de l'ouvrir au moyen d'un logiciel
  de tableur. Si le logiciel le demande, les virgules "," sont séparateurs de
  cellules et les guillemets encadrent les textes.

![image importbazarnext1.png (55.1kB)](images/BazarImportExport_importbazarnext1_vignette_780_544_20160405102909_20160405103228.png)

Une fois que l'on a préparé son fichier d'importation selon les consignes
données et que l'on a enregistré ce dernier en ".csv":

- Sélectionner le fichier à importer
- Cliquer sur le bouton **Importer le fichier\`**
- une étape de contrôle / validation est proposée
- si tout s'est bien passé on reçoit un message

### 4.2. Importer plusieurs fichiers à la fois

Pour lier des fiches de formulaire à des fichiers comme souvent des images, il
est nécessaire d'importer les fiches via un tableur CSV comme détaillé à l'étape
précédente. A la différence que le formulaire cible devra impérativement
disposer d'un champ "image" ou "Upload de fichier".

Lors de l'édition du tableur qui contiendra les données, renseigner la ou les
colonne(s) correspondantes à "image" et/ou "fichier" avec l'url du fichier en
question qui sera détaillé lors des étapes suivantes.

Prérequis :

- Disposer des accés administrateurs.
- Avoir accés à un logiciel de tableur tel que libre office calc.
- Avoir accés au serveur sur lequel est placé le wiki par les protocoles FTP.
- Disposer d'un logiciel de transfert de fichiers FTP tel que
  [FileZilla](https://filezilla-project.org/).

Etapes :

- Via le FTP, déposer sur le serveur dans le dossier "/files" les fichiers que
  l'on souhaitent voir lier avec les fiches que l'on va importer.
- Ouvrir un tableur ou le fichier ".csv" d'exemple importé précédemment. A
  chaque ligne de ce tableur correspondra une fiche importé.
- Dans la colonne "image" ou "fichier" renseigner l'URL où se trouve le fichier
  suivi du nom du fichier et de son extension. Exemple :
  <https://monwiki.net/files/image.jpg>
- Faire de même pour chaque fiche.
- Enregistrer puis importé le fichier csv comme détaillé dans la partie ci
  dessus : "Étapes pour importer des données".

Astuces :

- Pour lister des fichiers contenu dans un dossier, il existe des outils tel que
  la commande linux : `ls > filenames.txt` qui va stocker dans un fichier du nom
  de "filenames.txt" la liste des noms de fichiers. Il sera alors facile de
  copier coller ces noms et des les intégrer au tableur.
- Afin de lier l'URL de base tel que <https://monwiki.net/files/> au nom de
  fichier on peut utiliser une fonction de jonction de textes comme sur libre
  office calc : `=JOINDRE.TEXTE( ; ;cellule1;cellule2)`. Dans ce cas, nettoyer
  le tableur des colonnes non attendu par le formulaire ye wiki.

### 4.2. Étapes pour exporter des données

- se connecter au wiki (il faut être parmi les administrateurs pour pouvoir
  importer des données)
- se rendre sur la page permettant la gestion des formulaires (elle contient
  l'action{{bazar}})
- cliquer sur le bouton **exporter**
- choisir parmi les formulaires celui que l'on souhaite exporter

![image bazarexport1.png (34.6kB)](images/BazarImportExport_bazarexport1_vignette_780_544_20160405103248_20160405103507.png)

- le wiki génère un fichier CSV à télécharger

![image bazarexportnext1.png (26.6kB)](images/BazarImportExport_bazarexportnext1_vignette_780_544_20160405103525_20160405103651.png)

**Quoi faire avec mon fichier CSV ?** Un CSV peut s'ouvrir avec Excel, Open
Office, Google Doc ... en précisant simplement que le caractère d'espacement est
une virgule.
