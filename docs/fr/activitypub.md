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
- la case "Valider les abonnés à la main" (voir plus bas).

Le profil est enregistré avec le formulaire, dans les métadonnées de sa page.

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

## Exemples de templates

Voilà ci-dessous deux exemples de template. A noter que, si l'on veut que
ActivityPub soit utilisé pour synchroniser deux instances YesWiki, il est
important que tous les champs soient inclus, sinon des données seront perdues.

### Agenda

Note: Avec la template ci-dessous, Mastodon affiche le nom de l'événement,
l'image et un lien vers la page. La compatibilité Mobilizon n'est pas encore
possible car Mobilizon attend deux activités (`Announce` et `Create`) - cela
devrait être réglé dans une future version.

#### Template de l'agenda

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Event",
  "name": {{ bf_titre | json_encode }},
  "content": {{ bf_description | default("") | json_encode }},
  "startTime": {{ bf_date_debut_evenement | json_encode }},
  "endTime": {{ bf_date_fin_evenement | default("") | json_encode }},
  "url": {{ bf_site_internet | json_encode }},
  "published": {{ date_creation_fiche | replace({" ": "T"}) | json_encode }},
  "updated": {{ date_maj_fiche | replace({" ": "T"}) | json_encode }},
  {% if bf_adresse and bf_ville and bf_code_postal %}
  "location": {
    "type": "Place",
    "name": {{ [bf_adresse, bf_ville] | join(", ") | trim(", ") | json_encode }},
    "latitude": {{ geolocation.bf_latitude | json_encode }},
    "longitude": {{ geolocation.bf_longitude | json_encode }},
    "address": {
      "type": "PostalAddress",
      "streetAddress": {{ bf_adresse | default("") | json_encode }},
      "postalCode": {{ bf_code_postal | default("") | json_encode }},
      "addressLocality": {{ bf_ville | default("") | json_encode }}
    }
  },
  {% endif %}
  "attachment": [
    {% if imagebf_image %}
    {
      "type": "Image",
      "url": {{ fileUrl(imagebf_image) | json_encode }}
    }
    {% endif %}
  ]
}
```

#### Template inverse de l'agenda

```json
{
  "bf_titre": {{ name | json_encode }},
  "bf_description": {{ content | default("") | json_encode }},
  "bf_date_debut_evenement": {{ startTime | json_encode }},
  "bf_date_fin_evenement": {{ endTime | default("") | json_encode }},
  "bf_adresse": {{ location.address.streetAddress | default("") | json_encode }},
  "bf_code_postal": {{ location.address.postalCode | default("") | json_encode }},
  "bf_ville": {{ location.address.addressLocality | default("") | json_encode }},
  "bf_site_internet": {{ url | default("") | json_encode }},
  "imagebf_image": {{ (attachment | first).url | default("") | json_encode }},
  "geolocation": {
    "bf_latitude": {{ location.latitude | default("") | json_encode }},
    "bf_longitude": {{ location.longitude | default("") | json_encode }}
  }
}
```

### Blog

Note: Nous n'utilisons pas la propriété `summary` car Mastodon l'interprête
comme s'il fallait cacher le contenu du texte. Le titre n'est pas afficher par
Mastodon. Il serait possible d'améliorer ça en agrégeant le titre, le chapeau et
le contenu dans la propriété `content`, mais il faudrait prévoir de les séparer
dans la template inverse (ce qui est possible avec Twig, mais peut demander un
peu de travail)

#### Template du blog

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Note",
  "name": {{ bf_titre | json_encode }},
  "preview": {{ bf_chapeau | default("") | json_encode }},
  "content": {{ bf_description | default("") | json_encode }},
  "published": {{ date_creation_fiche | replace({" ": "T"}) | json_encode }},
  "updated": {{ date_maj_fiche | replace({" ": "T"}) | json_encode }},
  "attachment": [
    {% if imagebf_image %}
    {
      "type": "Image",
      "url": {{ fileUrl(imagebf_image) | json_encode }}
    }
    {% endif %}
  ]
}
```

### Template inverse du blog

```json
{
  "bf_titre": {{ name | json_encode }},
  "bf_chapeau": {{ preview | default("") | json_encode }},
  "bf_description": {{ content | default("") | json_encode }},
  "imagebf_image": {{ (attachment | first).url | default("") | json_encode }}
}
```

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
