# Activer la compatibilité ActivityPub

Les fiches Bazar étant structurées, il est possible de les rendre compatible
ActivityPub. Chaque formulaire devient un acteur ActivityPub que l'on peut
suivre, et qui envoie des activités `Create`, `Update` et `Delete`. Le
formulaire lui-même peut suivre d'autres acteurs, et les fiches qu'il reçoit
vont être ajoutées automatiquement dans la liste.

Cela peut permettre de synchroniser deux instances YesWiki, ou alors de
permettre à n'importe quel utilisateur Mastodon d'être averti lorsqu'il y a des
nouveaux éléments qui sont postés. D'autres usages seront possible dans le
futur.

## Activer ActivityPub sur un formulaire Bazar

- Éditez le formulaire et ouvrez la section "ActivityPub" tout en bas.
- Cochez la case "Activer ActivityPub pour ce formulaire".
- Entrez une template sémantique, qui convertit les données YesWiki en données
  ActivityPub (voir les exemples plus bas).
- Entrez une template sémantique inverse, qui convertit les données ActivityPub
  en données YesWiki.

Une fois le formulaire enregistré, la liste des formulaires montre deux
nouveaux éléments :

- dans les formats de données, un bouton "ActivityPub" qui mène à l'acteur du
  formulaire ;
- dans les actions, un bouton "ActivityPub : profil et abonnements", visible
  des administrateurs seulement.

Ce bouton ouvre une page en trois onglets : profil, abonnements et abonnés. Le
compte n'existe pour les autres serveurs qu'une fois un nom d'utilisateur
choisi et le profil enregistré.

## Profil du compte

L'onglet "Profil" règle ce que voient les personnes qui consultent ou suivent le
compte depuis Mastodon, GoToSocial ou un autre serveur :

- le nom d'utilisateur, par exemple "agenda", qui donne l'identifiant
  `@agenda@monwiki.org` ;
- la présentation. Laissée vide, c'est la description du formulaire qui sert.
  Les adresses web deviennent des liens ;
- l'image de profil et la bannière. On donne une adresse complète, ou on
  choisit une image dans les fichiers du wiki ;
- jusqu'à six informations, avec un libellé et une valeur. Mastodon en affiche
  quatre, GoToSocial six ;
- la case "Valider les abonnés à la main" (voir plus bas) ;
- les cases "Publications reçues" (voir plus bas).

Le profil est enregistré avec le formulaire, dans les métadonnées de sa page.
À l'enregistrement, YesWiki envoie le profil à jour aux abonnés (une activité
`Update` de l'acteur), pour que leurs serveurs le rafraîchissent.

Les serveurs distants gardent le profil en cache, donc un changement peut
mettre quelques heures à apparaître chez eux. Changer le nom d'utilisateur ne
fait pas perdre les abonnés, car l'adresse de l'acteur reste celle du
formulaire (`/actors/<identifiant du formulaire>`). L'ancien identifiant, lui,
ne mène plus au compte.

## Abonnements et abonnés

L'onglet "Abonnements" liste les comptes que le formulaire suit. On peut y :

- s'abonner à un nouveau compte en entrant son identifiant ;
- synchroniser toutes les fiches d'un compte suivi ;
- se désabonner.

Un abonnement reste marqué "en attente d'acceptation" jusqu'à ce que le compte
distant l'accepte.

L'onglet "Abonnés" liste les comptes qui suivent le formulaire. On peut y
suivre un abonné en retour ou le retirer.

Par défaut, YesWiki accepte tout de suite une demande d'abonnement. Si la case
"Valider les abonnés à la main" est cochée, chaque demande attend dans l'onglet
"Abonnés", avec un bouton pour l'accepter et un pour la refuser. Une demande
dont l'acceptation automatique n'a pas pu partir vers le serveur distant reste
aussi affichée là, et le bouton "Accepter" la renvoie.

## Publications reçues

Par défaut, une publication reçue ne devient une fiche que si elle remplit
trois conditions :

- elle vient d'un compte que le formulaire suit, ou elle est attribuée
  (`attributedTo`) à un compte suivi du même serveur. C'est le cas d'un
  événement créé par une personne dans un groupe Mobilizon suivi ;
- elle est publique, ou non listée ;
- ce n'est pas une réponse.

Trois cases du profil lèvent chacune de ces règles : publier aussi les
messages des comptes non suivis (les mentions), les messages réservés aux
abonnés et les messages privés, et les réponses. Une fiche reste visible selon
les droits du formulaire, même si le message d'origine était privé.

Une publication écartée reçoit quand même une réponse `202 Accepted`, pour que
le serveur distant ne la renvoie pas. La raison apparaît dans le journal des
échanges.

L'auteur d'une fiche reçue s'affiche avec son nom et son lien de profil :
YesWiki lit son profil une fois, puis le met à jour quand le compte envoie une
activité `Update` à son sujet.

## Clés de signature

Chaque compte signe ses échanges avec une clé RSA. YesWiki la crée au premier
besoin dans `private/keys/activitypub/<identifiant du formulaire>.pem`, avec
les autres clés du wiki. Elle n'est ni dans la base de données ni dans l'API.
Si le wiki range ses données protégées dans un stockage S3, la clé y va aussi.

Gardez ce fichier dans vos sauvegardes. Sans lui, YesWiki génère une nouvelle
clé. Mastodon recharge alors le profil de lui-même, mais GoToSocial refuse un
compte dont la clé a changé ("pubkey has changed" dans ses journaux) : il garde
l'ancien profil et ignore les messages du compte. Il faut alors qu'un
administrateur de chaque instance GoToSocial concernée fasse expirer les clés
du domaine du wiki, depuis les actions d'administration de GoToSocial.

Les wikis qui gardaient la clé dans les métadonnées du formulaire la voient
déplacée dans ce fichier par la mise à jour, sans changement de clé.

## Compatibilité GoToSocial

GoToSocial refuse toute requête non signée, y compris la simple lecture d'un
profil. YesWiki signe donc avec la clé du formulaire toutes ses requêtes vers
les autres serveurs : lecture des profils, des clés et des boîtes d'envoi.

Le serveur web doit faire suivre les adresses `/actors/…` et
`/.well-known/webfinger` à YesWiki. Le binaire et la configuration nginx
fournie le font déjà.

Dans une boîte d'envoi GoToSocial, les objets sont souvent donnés par leur
seule adresse. La synchronisation va alors chercher chaque objet, s'il est sur
le même serveur que le compte suivi.

Un serveur sur la même machine que le wiki peut répondre à la fois en
`127.0.0.1` et en adresse publique. YesWiki se connecte alors à l'adresse
publique, au lieu de refuser le serveur.

## Journal des échanges

Les échanges ActivityPub passent par le journal du wiki : une ligne JSON sur la
sortie d'erreur du serveur, puis l'écran `/admin/logs`, canal "diagnostic",
actions `activitypub.*`.

Les échecs y sont toujours :

- `activitypub.received` en `warning` : une activité refusée par la boîte de
  réception, avec la raison (`error`), la clé qui l'a signée
  (`request.keyId`) et le logiciel distant (`request.userAgent`) ;
- `activitypub.delivery_failed` : une activité qu'un abonné ou un compte suivi
  n'a pas acceptée, avec la réponse de son serveur ;
- `activitypub.sync_failed` : une publication que la synchronisation n'a pas
  pu importer.

Pour comprendre pourquoi un abonnement ou une publication ne passe pas,
activez le reste dans `yeswiki.config.php` :

```php
'activitypub_log' => true,
```

Le journal reçoit alors aussi, en `debug`, chaque activité acceptée
(`activitypub.received`), chaque envoi réussi (`activitypub.delivered`) et
chaque lecture par un serveur distant (`activitypub.served`) : profil,
collection, boîte d'envoi, fiche ou réponse WebFinger. Mobilizon relit une
fiche supprimée et doit obtenir 404. Chaque ligne porte alors l'activité
complète et la réponse du serveur distant.

Dans `/admin/logs`, les échanges de même nature sont regroupés par jour, avec
leur nombre et le contexte du dernier. Pour les suivre un par un, lisez la
sortie d'erreur de PHP (journal de PHP-FPM, du conteneur ou du binaire) en la
filtrant, par exemple avec `jq` :

```bash
jq -c 'select(.action // "" | startswith("activitypub")) | {at, action, message, status}'
```

Avec `activitypub_log`, le journal contient le texte des publications reçues
et envoyées, même privées. Désactivez-le une fois les essais finis.

## Exemples de templates

Les formulaires Agenda et Blog proposés à l'installation du wiki ont déjà leurs
templates. Pour donner le même comportement à un autre formulaire, copiez-les
depuis la configuration avancée de l'un d'eux. Si ActivityPub sert à
synchroniser deux instances YesWiki, tous les champs doivent figurer dans les
templates, sinon des données seront perdues.

Dans un template, les dates de la fiche s'appellent `created_at` et
`updated_at`. Les anciens noms `date_creation_fiche` et `date_maj_fiche` ne
renvoient plus rien : la mise à jour les remplace dans les templates des
formulaires existants, et donne les templates actuels aux formulaires qui
avaient encore ceux livrés avec Doryphore.

Quelques fonctions servent ici :

- `fileUrl(fichier)` donne l'adresse publique d'un fichier du wiki ;
- `format(texte)` rend un texte du wiki en HTML ;
- `markdown(html)` convertit le HTML reçu en Markdown, en neutralisant tout ce
  qui pourrait devenir une action ou une mise en forme du wiki.

### Refuser un objet

Un template inverse peut renvoyer `null` : l'objet est alors ignoré, sans
compter comme un échec de synchronisation. Celui de l'agenda ignore tout ce qui
n'est pas un `Event`. C'est utile avec Mobilizon, dont les groupes publient
aussi les commentaires de leurs événements :

```twig
{% if type == "Event" %}
{ "bf_titre": {{ name | json_encode }} }
{% else %}
null
{% endif %}
```

### Agenda

Le formulaire Agenda publie chaque fiche comme un événement (`Event`) que
Mobilizon, Mastodon et un autre YesWiki savent lire :

- une date sans heure (événement sur la journée) part à minuit, et une date de
  fin sans heure à 23:59:59. Toutes les dates portent leur décalage horaire,
  sans quoi Mobilizon refuse l'événement ;
- `timezone` reprend le fuseau du wiki. Mobilizon ne le garde que si
  l'événement a un lieu ;
- l'image devient la bannière : un `Document` nommé `Banner` ;
- le site internet devient un lien nommé `Website`, que Mobilizon affiche
  comme l'adresse de l'événement ;
- la latitude et la longitude du champ carte partent en nombres décimaux.
  Envoyées en texte, Mobilizon les ignore ;
- la description est mise en forme (HTML).

Le template inverse accepte les événements Mobilizon : bannière, lien
`Website` (sinon la page de l'événement sur Mobilizon), lieu donné en texte ou
en adresse complète, coordonnées, et dates ramenées au fuseau du wiki. La
description HTML est convertie en Markdown. Tout ce qui n'est pas un `Event`
est ignoré.

#### Recevoir les événements d'un groupe Mobilizon

Dans l'onglet "Abonnements" du formulaire, abonnez-vous au groupe
(`@legroupe@mobilizon.fr`). Mobilizon accepte tout de suite, sauf si le groupe
valide ses abonnés à la main. Chaque nouvel événement public du groupe arrive
ensuite dans l'agenda. Il est signé par la personne qui l'a créé, et c'est
elle seule qui peut ensuite le modifier ou le supprimer dans le wiki. Le bouton
"Synchroniser" importe les événements déjà publiés.

Les événements en brouillon ou non listés ne sont jamais envoyés par
Mobilizon.

#### Envoyer l'agenda vers une instance Mobilizon

Une personne de Mobilizon ne peut suivre qu'un groupe, pas un compte d'un autre
logiciel. C'est donc l'administration de l'instance qui s'abonne à l'agenda :
dans la partie Fédération de son administration, elle ajoute l'identifiant
du formulaire (`@agenda@monwiki.org`). L'interface peut afficher une erreur
alors que l'abonnement est bien parti : vérifiez l'onglet "Abonnés" du
formulaire.

Une fois l'abonnement accepté, Mobilizon lit la boîte d'envoi du formulaire
pour importer les événements existants, puis reçoit les nouveaux au fil de
l'eau.

Mobilizon relit chaque événement à son adresse, celle de la fiche, avec
l'en-tête `Accept: application/activity+json`. YesWiki y répond par l'objet
ActivityPub de la fiche. Une fiche supprimée répond 404, et sa suppression
part aux abonnés comme une `Tombstone` : sans cela, Mobilizon ne supprime pas
l'événement. La fiche doit donc rester lisible par tout le monde.

Une instance Mobilizon refuse un événement dont le titre fait moins de trois
caractères.

### Blog

Mastodon n'affiche pas la propriété `name` d'une `Note`. Le template met donc
le titre et le chapeau en tête de `content`, et le template inverse les retire
avant de convertir le reste en Markdown. Pour une note reçue sans `name`, il
reprend le titre en gras placé en tête par un autre YesWiki.

Le champ "Avertissement de contenu" (`bf_avertissement`) part dans `summary`,
avec `sensitive` : Mastodon et GoToSocial cachent alors le billet et son image
derrière ce texte. Une note reçue avec un avertissement le garde, et le
template de liste `blog` affiche le billet replié derrière l'avertissement,
image floutée, avec un bouton pour l'afficher.

Une note sans titre (le cas de presque toutes les notes Mastodon) reçoit un
titre tiré de son avertissement de contenu ou du début de son texte. Le
template de liste `blog` affiche alors le texte de la note plutôt que ce titre.
En tête de liste, la Une va à la première fiche qui a une image.

Avec le paramètre `origin`, une liste ne montre que les fiches créées sur le
wiki (`local`) ou celles reçues d'ailleurs (`remote`). La page du blog créée à
l'installation s'en sert pour séparer les articles du wiki de ceux des comptes
suivis.

## Actions

### `entryfollow`

Si ActivityPub est activé pour un formulaire et que son profil a un nom
d'utilisateur, cette action permet à n'importe qui de suivre l'acteur depuis
son propre compte (Mastodon par exemple).

Elle prend comme unique paramètre l'identifiant du formulaire :

```text
{{entryfollow id="3"}}
```

Cela affiche un champ comme celui-ci :

![interface de follow](images/follow-actor.png)

La personne entre son identifiant. Si son serveur propose l'abonnement à
distance, comme Mastodon, elle arrive sur la page de l'acteur dans son propre
compte. Sinon, l'action affiche l'identifiant du formulaire, à chercher depuis
son compte avant de s'abonner.
