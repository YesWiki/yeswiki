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

- Editer le formulaire et cliquez sur le bouton "Configuration avancée" tout en
  bas.
- Cochez la case "Activer ActivityPub pour ce formulaire"
- Entrez une template sémantique (utilisée pour convertir les données YesWiki en
  données ActivityPub - voir ci-dessous)
- Entrez une template sémantique inverse (utilisée pour convertir les données
  ActivityPub en YesWiki - voir ci-dessous)

Après avoir validé la modification, il devrait y avoir deux nouveaux éléments
dans la liste des formulaires:

- Dans la colonne "Format de données", un bouton "AP" qui renvoie vers l'acteur
  ActivityPub du formulaire
- Dans les actions, un bouton "ActivityPub", visible des administrateurs

Ce bouton mène à une page en deux onglets. Le compte n'existe pour les autres
serveurs qu'une fois un nom d'utilisateur choisi et le profil enregistré.

## Profil du compte

L'onglet "Profil" règle ce que voient les personnes qui consultent ou suivent le
compte depuis Mastodon, GoToSocial, etc. :

- le nom d'utilisateur (par exemple "agenda" ou "blog"), qui donne
  l'identifiant `@agenda@monwiki.org` ;
- la présentation. Laissée vide, c'est la description du formulaire qui sert.
  Les adresses web deviennent des liens ;
- l'image de profil et la bannière : une adresse complète, le chemin d'une
  image du wiki, ou un fichier envoyé (PNG, JPEG, GIF ou WebP, 5 Mo au plus),
  rangé dans `files/` ;
- jusqu'à six informations, libellé et valeur. Mastodon en affiche quatre,
  GoToSocial six.

La page enregistre le tout dans `wakka.config.php`, sous la clé
`activitypub_profiles`, rangé par identifiant de formulaire. Le serveur web doit
donc pouvoir écrire ce fichier. On peut aussi l'éditer à la main :

```php
'activitypub_profiles' => [
    2 => [
        'username' => 'agenda',
        'summary' => "Les rencontres et ateliers du collectif.",
        'icon' => 'files/Logo_agenda.png',
        'image' => 'https://monwiki.org/files/Banniere.jpg',
        'fields' => [
            'Site' => 'https://monwiki.org/',
            'Contact' => 'contact@monwiki.org',
        ],
    ],
],
```

Les serveurs distants gardent le profil en cache : un changement peut mettre
quelques heures à apparaître chez eux. Changer le nom d'utilisateur ne fait pas
perdre les abonnés, car l'adresse de l'acteur reste celle du formulaire, mais
l'ancien identifiant ne mène plus au compte.

## Abonnements et abonnés

L'onglet "Abonnements" liste les comptes que le formulaire suit. On peut y :

- s'abonner à un nouveau compte en entrant son identifiant ;
- synchroniser toutes les fiches d'un compte suivi ;
- se désabonner.

L'onglet "Abonnés" liste les comptes qui suivent le formulaire. On peut y suivre
un abonné en retour ou le retirer.

Par défaut, une demande d'abonnement est acceptée tout de suite. En cochant
"Valider les abonnés à la main" dans le profil, chaque demande attend dans
l'onglet "Abonnés", avec un bouton pour l'accepter ou la refuser. Si l'acceptation
automatique n'a pas pu être envoyée au serveur distant, la demande reste
affichée de la même façon, et le bouton "Accepter" la renvoie.

## Clés de signature

Chaque compte signe ses échanges avec une clé RSA. YesWiki la crée au premier
besoin dans `private/activitypub/<identifiant du formulaire>.pem`, lisible
seulement par le serveur web. Elle n'est ni en base de données ni dans l'API.

Gardez ce fichier dans vos sauvegardes : sans lui, YesWiki génère une nouvelle
clé. Mastodon recharge alors le profil de lui-même. GoToSocial, lui, refuse un
compte dont la clé a changé ("pubkey has changed" dans ses journaux) : il garde
l'ancien profil et ignore les messages du compte. Un administrateur de chaque
instance GoToSocial concernée doit alors faire expirer les clés du domaine du
wiki, depuis les actions d'administration de GoToSocial.

## Compatibilité GoToSocial

GoToSocial refuse toute requête non signée, y compris la simple lecture d'un
profil. YesWiki signe donc avec la clé du formulaire toutes ses requêtes vers
les autres serveurs : lecture des profils, des clés et des boîtes d'envoi.

Le bouton de l'action `bazarfollow` redirige vers le serveur de la personne
quand celui-ci propose l'abonnement à distance (Mastodon le fait). Sinon,
l'action affiche l'identifiant du compte, à chercher depuis son propre compte
avant de s'abonner.

Un abonnement demandé depuis l'onglet des abonnements reste marqué "en
attente d'acceptation" jusqu'à ce que le compte distant l'accepte.

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

### `bazarfollow`

Si vous avez activés ActivityPub pour un formulaire Bazar, vous pouvez utiliser
cette action pour permettre à n'importe qui de suivre l'acteur avec un compte
ActivityPub (par exemple Mastodon).

Elle prend comme unique paramètre l'ID du formulaire:

```text
{{bazarfollow id="3"}}
```

Cela affiche un champ comme ça:

![interface de follow](images/follow-actor.png)

Lorsque l'utilisateur entre son identifiant ActivityPub, cela le redirige vers
la page de l'acteur formulaire sur sa propre instance.
