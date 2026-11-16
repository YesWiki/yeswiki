# Connexion : comptes du wiki, SSO et annuaires

Un **fournisseur** est une façon de se connecter au wiki. Les comptes du wiki lui-même, avec un
nom et un mot de passe que le wiki garde, sont un fournisseur, appelé **local**. Les autres
permettent de se connecter avec un compte que l'on a déjà ailleurs, le plus souvent celui de son
entreprise ou de son organisation :

| Type                    | À quoi il s'adresse                                                            | Comment on se connecte                                                        |
| ----------------------- | ------------------------------------------------------------------------------ | ----------------------------------------------------------------------------- |
| OpenID Connect / OAuth2 | Keycloak, LemonLDAP::NG, Authentik, Microsoft Entra, Google...                 | un bouton qui envoie sur la page du fournisseur, puis ramène au wiki          |
| SAML 2.0                | un fournisseur d'identité (IdP) : ADFS, Shibboleth, LemonLDAP::NG, Keycloak... | un bouton, de la même façon                                                   |
| CAS                     | un serveur CAS (2.0 ou 3.0)                                                    | un bouton, de la même façon                                                   |
| LDAP                    | un annuaire : OpenLDAP, Active Directory...                                    | l'identifiant et le mot de passe de l'annuaire, saisis dans le formulaire du wiki |
| Local                   | rien : le wiki garde lui-même les comptes                                      | un nom ou un email et un mot de passe, saisis dans le formulaire du wiki      |

Plusieurs fournisseurs peuvent être activés en même temps, y compris plusieurs du même type (deux
fournisseurs SAML, par exemple). Une extension peut ajouter d'autres types : ils apparaissent dans
les mêmes listes et fonctionnent de la même façon.

> Un wiki mis à jour depuis une version précédente arrive avec ses fournisseurs déjà convertis.
> La partie de `UPGRADE.md` consacrée à la connexion dit ce qui est converti, et ce qu'il faut
> faire **avant** la mise à jour : l'adresse de retour à enregistrer chez un serveur OpenID Connect.

## Où trouver l'écran

Dans la gestion du site, **Administration › Connexion** (adresse `?admin/sign-in`). Il faut être
identifié comme administrateurice.

L'écran liste les fournisseurs, dans l'ordre où la page de connexion les affiche, avec leur type,
leur état (activé ou non) et le nombre de comptes qui leur appartiennent. De là, vous pouvez :

- **ajouter un fournisseur** : les boutons en bas de l'écran proposent chaque type installé sur
  le wiki ;
- **modifier** un fournisseur (le bouton au crayon) et le **Tester** (voir
  [Tester un fournisseur](connexion-sso.md#tester-un-fournisseur)) ;
- **Activer** ou **Désactiver** un fournisseur ;
- **Supprimer** un fournisseur, seulement tant qu'aucun compte ne lui appartient. Un fournisseur
  qui a des comptes peut seulement être désactivé ;
- ouvrir **Connexions refusées**, c'est-à-dire le Journal (`?admin/logs`) filtré sur les connexions
  refusées.

Trois règles vous empêchent d'enfermer tout le monde dehors : l'écran refuse de désactiver, ou de
supprimer, **le dernier fournisseur activé** ; les comptes locaux peuvent être activés et
désactivés, jamais supprimés ; et si vous êtes connecté·e avec un compte local, l'écran refuse de
désactiver les comptes locaux tant qu'aucun fournisseur activé n'a **Rattacher au compte existant
qui a le même email** coché, puisque vous seriez déconnecté·e sans moyen de revenir (voir
[Comptes du wiki](connexion-sso.md#comptes-du-wiki-local)). Un nouveau fournisseur
s'ajoute en fin de liste, et l'écran ne change pas l'ordre de la page de connexion (voir [Où sont gardés les fournisseurs](connexion-sso.md#où-sont-gardés-les-fournisseurs)).

Un fournisseur dont le type n'est plus installé (son extension a été désactivée) reste dans la
liste, marqué **Indisponible**. Ses réglages sont conservés, personne ne peut s'y connecter, et on
ne peut que le désactiver ou le supprimer jusqu'au retour de l'extension.

## Ce qui se passe quand quelqu'un se connecte

Sur la page de connexion (l'action `{{login}}` et `?user`), chaque fournisseur à bouton
(OpenID Connect, SAML, CAS) affiche un bouton « Continuer avec... » en haut. En dessous, « ou avec
un mot de passe » mène à un seul formulaire, avec un onglet par fournisseur à mot de passe (local,
LDAP). Mot de passe perdu et inscription n'apparaissent que sur l'onglet local.

**Chaque compte appartient à un seul endroit** : un fournisseur, ou local. Un compte qui appartient
à un fournisseur lui est **lié** : le wiki garde l'identifiant que le fournisseur donne à cette
personne, si bien qu'un changement d'email dans l'entreprise ne casse rien.

Quand un fournisseur dit qui est la personne, le wiki cherche son compte dans cet ordre :

1. un compte déjà lié à ce fournisseur avec cet identifiant : la personne est connectée ;
2. sinon, un compte qui a le même email. Il n'est utilisé que si **Rattacher au compte existant qui
   a le même email** est activé pour ce fournisseur ; il est alors lié, et son mot de passe du wiki
   est effacé, puisqu'il se connecte désormais par le fournisseur. Un compte qui appartient à un
   autre fournisseur n'est jamais pris ;
3. sinon, un compte est créé, si **Créer un compte à la première connexion** est activé (c'est le
   cas par défaut). Il est nommé d'après le [modèle de nom](connexion-sso.md#nom-des-comptes) du
   fournisseur, sinon d'après le début de l'email, sinon d'après l'identifiant.

Si rien de tout cela ne marche, la connexion est refusée, et la personne voit pourquoi (voir
[Quand quelqu'un ne peut pas se connecter](connexion-sso.md#quand-quelquun-ne-peut-pas-se-connecter)).

À chaque connexion, l'email et les éventuels
[attributs supplémentaires](connexion-sso.md#attributs-supplémentaires) du compte sont mis à jour depuis le
fournisseur, et ses [groupes](connexion-sso.md#correspondance-des-groupes) aussi. Rien n'est écrit si rien n'a
changé.

### Ce qu'un compte lié ne peut plus faire

- Son email, ses champs remplis par le fournisseur et son mot de passe **ne
  peuvent pas être modifiés sur le wiki**, ni par la personne ni par un·e administrateurice. Son
  profil le dit, et l'invite à les corriger chez le fournisseur : le wiki reprend les nouvelles
  valeurs à la connexion suivante.
- Il ne peut pas utiliser **Mot de passe perdu** ni changer de mot de passe : il n'en a pas.
- Il ne peut pas se connecter avec le formulaire à mot de passe, et il cesse de fonctionner dès que
  son fournisseur est désactivé : une personne déjà connectée est déconnectée à la page suivante.

### Combien de temps dure une connexion

Une connexion par un fournisseur dure **90 jours**, et se prolonge tant que la personne continue
d'utiliser le wiki. Pendant ce temps, le wiki ne redemande rien au fournisseur : désactiver
quelqu'un dans l'entreprise ne le déconnecte pas du wiki. **Pour couper l'accès de quelqu'un,
supprimez son compte sur le wiki, ou désactivez le fournisseur.**

Se déconnecter du wiki déconnecte du wiki seulement, pas du fournisseur de l'entreprise.

## Ajouter un fournisseur : ce que tous les types ont en commun

Cliquez sur le type voulu sous **Ajouter un fournisseur**. Le formulaire a les mêmes parties pour
tous les types.

**Nom.** Affiché sur le bouton de connexion (« Continuer avec Acme SSO »). L'**identifiant** du
fournisseur en est tiré une fois pour toutes, au premier enregistrement (`Acme SSO` donne
`acme-sso`), et ne change plus : il figure dans les adresses où le fournisseur renvoie les
personnes. Vous pouvez renommer le fournisseur plus tard ; l'identifiant reste.

**Activé.** Un nouveau fournisseur est activé par défaut. Pendant que vous le réglez, vous pouvez
décocher la case : un fournisseur désactivé peut quand même être [testé](connexion-sso.md#tester-un-fournisseur).

**Connexion au fournisseur.** Les réglages propres au type, décrits plus bas. Les secrets (secret
du client, clé privée, mots de passe) ne sont plus jamais affichés une fois enregistrés : laissez
le champ vide pour garder celui qui est enregistré, ou cochez **Supprimer la valeur enregistrée**
à côté pour le retirer (par exemple la clé SAML du wiki, pour ne plus signer les demandes).

Une fois le fournisseur enregistré, cette partie affiche aussi ce qu'il faut donner au
fournisseur : l'**Adresse de retour (callback)**, où il renvoie les personnes, et pour SAML
l'**identifiant d'entité** et l'**adresse ACS** du wiki. L'adresse de retour est l'adresse du wiki
suivie de `user/sign-in/<identifiant>/callback&sso_callback=<identifiant>`, par exemple
`https://wiki.example.org/?user/sign-in/acme-sso/callback&sso_callback=acme-sso` : vous pouvez
donc la donner au fournisseur avant d'enregistrer si vous connaissez l'identifiant.
L'identifiant y figure deux fois exprès : certains serveurs reconstruisent la requête de l'adresse
et perdent `user/sign-in/<identifiant>/callback`, une clé sans valeur ; `sso_callback=<identifiant>`
mène alors quand même la personne au retour.

**Attributs.** Les noms qu'emploie le fournisseur pour ce dont le wiki a besoin : **Identifiant de
l'utilisateur**, **Email** et **Groupes**. Laissez un champ vide
pour utiliser le nom habituel de ce type, affiché en gris. L'identifiant ne doit jamais changer pour
une personne, ni être redonné à quelqu'un d'autre : préférez un identifiant technique (`sub`, un
matricule, l'`uid` de l'annuaire) à un email.

Le wiki refuse une connexion sans identifiant, et sans email, en disant quel attribut il cherchait.
Un email fait seulement d'espaces compte comme absent. Un email qui n'est pas une seule adresse
(plusieurs réunies en une valeur) est refusé aussi, même pour une personne dont le compte est déjà
lié : le wiki ne la connecte pas avec l'email qu'il garde.

**Comptes.**

- **Créer un compte à la première connexion.** Activé par défaut. Désactivé, seules les personnes
  qui ont déjà un compte sur le wiki peuvent se connecter avec ce fournisseur.
- **Nom des comptes créés** et **Faire du nom un NomWiki (majuscules automatiques)**. Voir
  [Nom des comptes](connexion-sso.md#nom-des-comptes).
- **Rattacher au compte existant qui a le même email.** Désactivé par défaut. **Ne l'activez que si
  le fournisseur vérifie les emails qu'il envoie** : sinon, quiconque peut choisir son email chez
  le fournisseur peut prendre le compte du wiki qui l'utilise. Un fournisseur OpenID Connect qui
  envoie `email_verified: false` n'est jamais rattaché par email, quel que soit ce réglage. C'est
  aussi le réglage à activer quand le fournisseur change de serveur et que les identifiants
  changent avec lui (voir [Changer de serveur](connexion-sso.md#changer-de-serveur)).

### Nom des comptes

Un nouveau compte est nommé d'après le modèle **Nom des comptes créés**. Chaque `#[attribut]`
est remplacé par la première valeur que le fournisseur envoie sous ce nom (les noms que montre le test). Laissé
vide, ou si aucun de ses attributs n'est envoyé, le nom vient du début de l'email, puis de
l'identifiant.

**Faire du nom un NomWiki (majuscules automatiques)** dit ce qui en est fait :

- **Activé** (par défaut) : la ponctuation sépare les mots, les accents
  disparaissent, chaque mot prend une majuscule, les espaces disparaissent, et un nom de moins de
  deux majuscules voit sa dernière lettre passer en majuscule.
- **Désactivé** : le nom reste tel que le fournisseur l'envoie,
  casse et accents compris ; seuls les espaces deviennent des tirets, et les caractères qu'un nom
  de page ne peut pas contenir disparaissent.

| Modèle                         | Le fournisseur envoie     | Activé                 | Désactivé               |
| ------------------------------ | ------------------------- | ---------------------- | ----------------------- |
| `#[given_name] #[family_name]` | `Marie`, `Dupont-Lefèvre` | `MarieDupontLefevre`   | `Marie-Dupont-Lefèvre`  |
| `#[uid]`                       | `jdupont`                 | `JduponT`              | `jdupont`               |
| (aucun)                        | `marie.dupont@acme.example` | `MarieDupont`        | `marie.dupont`          |

Si une page ou un compte porte déjà ce nom, un numéro est ajouté : `MarieDupontLefevre2`,
`jdupont-2`. Un nom que le wiki se réserve (`admin`, `user`, `api`…) n'est jamais donné. Le nom est
choisi une fois, à la première connexion : changer ces réglages ne renomme personne.

### Correspondance des groupes

Chaque ligne dit : une personne que le fournisseur met dans **ce groupe** est mise dans **ce groupe
du wiki**. Plusieurs lignes peuvent nommer le même groupe du fournisseur. À chaque connexion, la
personne est ajoutée aux groupes du wiki qui correspondent à ses groupes chez le fournisseur, et
retirée des groupes du wiki nommés ici auxquels elle n'a plus droit. **Les groupes du wiki qu'aucune
ligne ne nomme ne sont jamais touchés** : les groupes que vous gérez à la main restent tels que vous
les avez réglés. Un groupe du wiki qui n'existe pas encore est créé.

Les noms de groupes sont comparés à l'identique, majuscules comprises. Le
[test](connexion-sso.md#tester-un-fournisseur) montre les noms exacts que le fournisseur envoie.

Une connexion ne vide jamais le groupe `admins` : le dernier administrateurice y reste.

### Attributs supplémentaires

Chaque ligne remplit un champ du formulaire des utilisateurices (à choisir dans la liste) avec un
attribut du fournisseur. Ces champs deviennent non modifiables pour la personne, comme l'email.

### Enregistrer, ou Enregistrer et tester

**Enregistrer** revient à la liste. **Enregistrer et tester** enregistre, puis lance un test
aussitôt.

## Pas à pas, type par type

### Comptes du wiki (local)

Les comptes locaux n'ont pas de réglage : on peut seulement les activer ou les désactiver, depuis
la liste. Désactivés, le formulaire à mot de passe perd son onglet local, l'inscription et le mot de
passe perdu ne sont plus disponibles, `/api/login` refuse tout le monde, et les comptes locaux sont
déconnectés à la page suivante.

Ne désactivez les comptes locaux qu'une fois les autres fournisseurs en état de marche, et gardez
en tête [comment revenir dans le wiki](connexion-sso.md#revenir-dans-le-wiki) si le serveur de l'entreprise tombe.

Si vous êtes vous-même connecté·e avec un compte local, désactiver les comptes locaux vous
déconnecte aussi : l'écran vous demande donc de confirmer. Il refuse tout net quand aucun
fournisseur activé n'a **Rattacher au compte existant qui a le même email** coché : vous ne
pourriez plus vous connecter. Cochez-le sur le fournisseur avec lequel vous vous connecterez (votre
compte lui est alors rattaché à votre première connexion), ou désactivez les comptes locaux en
ligne de commande, dans le dossier du wiki : `./yeswicli sign-in:disable local`.

### OpenID Connect ou OAuth2

1. Chez le fournisseur, créez un client (parfois appelé application) de type « confidentiel » ou
   « application web », avec le flux _authorization code_. Donnez-lui l'adresse de retour :
   `https://wiki.example.org/?user/sign-in/<identifiant>/callback&sso_callback=<identifiant>`.
   Notez son **identifiant de client** et son **secret**.
2. Sur le wiki, **Ajouter un fournisseur › OpenID Connect ou OAuth2**, et donnez-lui un nom.
3. Renseignez l'**URL de découverte**, qui se termine par `/.well-known/openid-configuration`
   (par exemple `https://login.example.org/realms/staff/.well-known/openid-configuration`). Le wiki
   y lit toutes les autres adresses.

   Pour un serveur OAuth2 simple, sans document de découverte : laissez l'URL de découverte vide et
   renseignez plutôt l'**Adresse d'autorisation**, l'**Adresse du jeton** et l'**Adresse
   userinfo**. Ajoutez l'**Émetteur (issuer)** et l'**URL des clés (jwks_uri)** si le serveur les
   publie. Avec une URL de découverte, ou avec ces deux valeurs, le jeton d'identité est vérifié
   (signature, émetteur, audience, expiration, nonce) et ses informations s'ajoutent à celles de
   userinfo ; sans elles, le jeton d'identité est ignoré et l'identité vient seulement de l'adresse
   userinfo. Les adresses écrites ici l'emportent sur celles de la découverte.
4. Renseignez l'**Identifiant du client** et le **Secret du client**. Les **Portées (scopes)** sont
   par défaut `openid email profile` ; ajoutez celle qui donne les groupes si votre fournisseur en
   demande une.
5. Les noms d'attributs habituels sont `sub`, `email` et `groups`.
   Changez-les si votre fournisseur en utilise d'autres.
6. **Enregistrer et tester.**

Le wiki utilise le flux _authorization code_ avec `state`, un nonce et PKCE (S256).

### SAML 2.0

Le wiki est le fournisseur de service (SP). Chaque fournisseur SAML du wiki est un fournisseur de
service à part, avec son propre identifiant d'entité.

1. Sur le wiki, **Ajouter un fournisseur › SAML 2.0**, donnez-lui un nom, décochez **Activé** pour
   l'instant, et renseignez le côté du fournisseur d'identité, de l'une de ces trois façons :
   - l'**URL des métadonnées du fournisseur d'identité** ;
   - ou ses **métadonnées (XML)**, collées en entier, sur autant de lignes qu'il faut ;
   - ou, sans métadonnées, son **identifiant d'entité**, son **adresse de connexion unique (SSO)**
     et son **certificat de signature** (PEM, collé sur plusieurs lignes si vous voulez). Ces trois
     valeurs l'emportent sur les métadonnées quand les deux sont données.
2. Enregistrez. L'écran de modification affiche alors ce qu'il faut recopier chez le fournisseur
   d'identité :
   - l'**Identifiant d'entité de ce wiki**, qui est aussi l'adresse des métadonnées du wiki :
     `https://wiki.example.org/?user/sign-in/<identifiant>/metadata`. Beaucoup de fournisseurs
     d'identité savent les lire directement à cette adresse ;
   - l'**Adresse de réception des assertions (ACS)**, où le fournisseur d'identité envoie sa
     réponse :
     `https://wiki.example.org/?user/sign-in/<identifiant>/callback&sso_callback=<identifiant>`,
     en HTTP-POST.
3. Chez le fournisseur d'identité, enregistrez le wiki avec ces deux valeurs, et faites-lui
   **signer ses assertions** : le wiki refuse celles qui ne le sont pas. Faites-lui envoyer les
   attributs dont vous avez besoin.
4. Réglez les noms d'attributs. L'identifiant vaut par défaut `name_id` (le NameID de l'assertion) ;
   les autres `mail` et `groups`. Beaucoup de fournisseurs d'identité envoient
   des noms comme `urn:oid:0.9.2342.19200300.100.1.3` : un attribut qui a aussi un nom court
   (_friendly name_) s'utilise sous l'un ou l'autre. Le test montre exactement ce qui arrive.

   Avec l'identifiant laissé à `name_id`, le fournisseur d'identité doit envoyer un NameID
   **persistant** (format `urn:oasis:names:tc:SAML:2.0:nameid-format:persistent`), le même pour
   une personne à chaque connexion. Un NameID transitoire change à chaque fois : la première
   connexion de la personne crée ou rattache son compte, et la deuxième ne le retrouve plus sous
   cet identifiant, si bien qu'elle est refusée (`email_conflict`), ou, avec le rattachement par
   email activé, réécrit l'identifiant à chaque connexion. Une réponse sans aucun NameID est
   refusée en `missing_attribute`. Si le fournisseur d'identité ne peut pas envoyer de NameID
   persistant, prenez plutôt pour identifiant un attribut stable, comme un matricule ou `uid`.
5. **Enregistrer et tester**, puis activez le fournisseur.

Le wiki vérifie la signature, l'audience, la période de validité, que la réponse répond bien à la
demande envoyée depuis ce navigateur, et, quand la réponse nomme une destination, que c'est
exactement l'adresse ACS de ce fournisseur. Les documents qui contiennent une DTD sont refusés.

**Demandes signées et assertions chiffrées (facultatif).** Donnez au wiki un certificat et sa clé
privée, dans **Certificat de ce wiki** et **Clé privée de ce wiki**. Le wiki signe alors ses
demandes, sait lire les assertions chiffrées, et publie le certificat dans ses métadonnées. Le wiki
ne fabrique pas la paire à votre place ; cette commande en fait une, valable dix ans :

```sh
openssl req -x509 -newkey rsa:3072 -sha256 -days 3650 -nodes \
  -keyout wiki-saml.key -out wiki-saml.crt -subj "/CN=wiki.example.org"
```

Collez `wiki-saml.crt` dans le champ du certificat et `wiki-saml.key` dans celui de la clé privée.
Renseignez les deux, ou aucun. La clé privée est un secret, comme le secret d'un client : elle n'est
plus jamais affichée. Changer de paire oblige à mettre à jour le fournisseur d'identité aussi.

### CAS

1. Sur le wiki, **Ajouter un fournisseur › CAS**, et donnez-lui un nom.
2. **URL du serveur CAS** : l'adresse sous laquelle se trouvent les pages `login` et
   `serviceValidate` du serveur, par exemple `https://cas.example.org/cas`.
3. **Version du protocole** : 3.0 (par défaut) ou 2.0. La version 3.0 est celle qui définit les
   attributs ; en 2.0, qu'un serveur en envoie ou non dépend du serveur.
4. **Fichier de l'autorité de certification** : laissez-le vide pour vérifier le certificat du
   serveur avec les autorités du système. Si le certificat du serveur CAS vient d'une autorité
   privée, indiquez le chemin, sur le serveur du wiki, du fichier PEM de cette autorité. Les
   certificats sont toujours vérifiés : il n'existe aucun moyen de l'empêcher.
5. Si le serveur CAS n'accepte que des services enregistrés, enregistrez-y l'adresse de retour du
   fournisseur,
   `https://wiki.example.org/?user/sign-in/<identifiant>/callback&sso_callback=<identifiant>`.
6. L'identifiant vaut par défaut `user`, l'identifiant que le serveur CAS valide. Les autres
   valent `mail` et `groups`, que le serveur doit envoyer comme attributs.
7. **Enregistrer et tester.**

Si le serveur CAS ne répond pas, une connexion peut l'attendre jusqu'à 10 secondes, deux fois (une
pour valider le ticket, une pour savoir ce qui ne va pas), avant que la personne voie le refus. Les
tickets passés par des proxys CAS sont refusés.

### LDAP

LDAP a besoin de l'extension PHP `ldap` sur le serveur. Sans elle, le fournisseur ne peut connecter
personne, et la page **Santé** le signale.

1. Sur le wiki, **Ajouter un fournisseur › Annuaire LDAP**, et donnez-lui un nom : c'est celui de
   son onglet dans le formulaire à mot de passe.
2. **Adresse de l'annuaire** : `ldaps://ldap.example.org:636`, ou `ldap://ldap.example.org:389`
   avec **Chiffrer avec StartTLS** coché. Le certificat du serveur est toujours vérifié avec les
   autorités du système, quoi que disent `TLS_REQCERT` dans `ldap.conf` ou la variable
   d'environnement `LDAPTLS_REQCERT` : un certificat auto-signé, ou d'une autorité privée, est
   refusé (`provider_unreachable`, avec `certificate verify failed` dans l'erreur) tant que le
   serveur ne fait pas confiance à cette autorité, par le magasin du système ou `TLS_CACERT` dans
   `ldap.conf`. Une adresse `ldap://` sans StartTLS fait passer chaque mot de passe en clair sur le
   réseau, sans aucune vérification : l'écran de modification vous en avertit.
3. Dites au wiki comment trouver la personne, de l'une de ces deux façons :
   - un **Modèle de DN de connexion**, le DN sous lequel les personnes se connectent, avec
     `{login}` à la place de l'identifiant : `uid={login},ou=people,dc=example,dc=org`. C'est le
     plus simple ;
   - ou, en laissant le modèle vide, une recherche : le **DN de base de la recherche** où sont les
     personnes, le **Filtre des personnes** (par défaut `(uid={login})` ; pour Active Directory,
     `(sAMAccountName={login})`), et si l'annuaire n'accepte pas les recherches anonymes, un **DN
     du compte de recherche** et son **mot de passe**.
4. Les groupes sont lus par défaut dans l'attribut `memberOf` de la personne : chaque groupe est
   alors son DN complet, comme `cn=editors,ou=groups,dc=example,dc=org`, et c'est ce que vous
   écrivez dans la correspondance des groupes. Si votre annuaire n'a pas de `memberOf`, renseignez
   un **DN de base des groupes** : le wiki y cherche les groupes que trouve le **Filtre des
   groupes** (par défaut `(member={dn})` ; `{dn}` est le DN de la personne et `{login}` son
   identifiant), et chaque groupe est alors son `cn`, comme `editors`.
5. Les noms d'attributs habituels sont `uid`, `mail` et `memberOf`.
6. **Enregistrer et tester** : le test demande un identifiant et un mot de passe.

L'identifiant et le mot de passe saisis dans le formulaire du wiki sont envoyés à l'annuaire, jamais
gardés. Un mot de passe vide est refusé avant d'interroger l'annuaire, parce que beaucoup
d'annuaires l'accepteraient comme une connexion anonyme.

## Serveurs d'identité validés

Le wiki a été essayé, à la main, avec ces serveurs d'identité, à la version exacte indiquée :
association, connexion et principaux cas d'échec, pour chaque protocole que le serveur parle. La
méthode est décrite dans `docs/adr/0031-sign-in-providers-replace-the-login-extensions.md`, section
_Testing against a real server_. Un serveur absent de la liste peut fonctionner, il n'a simplement
pas été essayé.

| Serveur | Version | Protocoles |
| --- | --- | --- |
| Keycloak | 26.8.2 | OIDC, SAML |
| LemonLDAP::NG | 2.23.4 | OIDC, SAML, CAS |
| LemonLDAP::NG | 2.21.6 | OIDC, SAML, CAS |
| LemonLDAP::NG | 2.16.10 | OIDC, SAML, CAS |
| Apereo CAS | 8.0.3 | OIDC, SAML, CAS |
| Shibboleth IdP | 5.2.3 | OIDC, SAML, CAS |
| SimpleSAMLphp | 2.5.4 | OIDC, SAML, CAS |
| SimpleSAMLphp | 2.4.11 | OIDC, SAML, CAS |
| Pocket ID | 2.19.2 | OIDC |
| Authelia | 4.39.28 | OIDC |
| Dex | 2.46.1 | OIDC |
| Samba AD DC | 4.25.1 | LDAP |
| Samba AD DC | 4.24.8 | LDAP |
| Samba AD DC | 4.23.13 | LDAP |
| 389 Directory Server | 3.3.1 | LDAP |
| 389 Directory Server | 3.2.3 | LDAP |
| FreeIPA | 4.13.4 | LDAP |
| OpenLDAP | 2.7.2 | LDAP |
| OpenLDAP | 2.6.16 | LDAP |
| lldap | 0.6.3 | LDAP |
| YunoHost | 12.1.41.2 | LDAP |
| authentik | 2026.8.5 | OIDC, SAML, LDAP |
| authentik | 2026.5.8 | OIDC, SAML, LDAP |

## Réglages que le serveur d'identité peut demander

Le wiki a été essayé avec les [serveurs d'identité validés](connexion-sso.md#serveurs-didentité-validés).
Plusieurs ont eu besoin d'un réglage de leur côté avant que les personnes puissent se connecter, ou
avant que le compte reçoive son email et ses groupes. Aucun réglage du wiki ne remplace la plupart
d'entre eux. Le test montre l'effet de chacun.

### OpenID Connect

Ce que le wiki attend de tout serveur :

- **Le secret du client dans le corps de la requête** (`client_secret_post`). Un client qui ne
  peut l'envoyer que dans l'en-tête `Authorization` (`client_secret_basic`) est refusé à l'adresse
  du jeton, et chaque connexion échoue en `provider_rejected` (`invalid_client`).
- **Une seule adresse email.** Un serveur qui envoie une information à plusieurs valeurs en une
  seule chaîne, valeurs jointes par une espace, fait de deux emails une adresse qui n'en est pas une
  (refus `missing_attribute`) et de deux groupes un seul groupe qu'aucune correspondance ne
  reconnaît. Les tableaux JSON sont lus : le wiki garde le premier email.
- **Un jeton d'identité signé en RS256, ES256 ou EdDSA** : les trois ont été vus et acceptés. Un
  jeton signé en HS256, avec le secret du client, est refusé en `invalid_response`.
- **`email_verified: true`**, pour que **Rattacher au compte existant qui a le même email**
  rattache qui que ce soit.
- **Des groupes en liste de noms.** Une information envoyée en objet, les noms des rôles en clés,
  ne donne aucun groupe, sans erreur.
- **La requête de l'adresse de retour gardée.** Le serveur doit renvoyer les personnes à
  l'adresse de retour en y ajoutant ses paramètres ; leur ordre n'importe pas. Un serveur qui perd
  la clé `user/sign-in/<identifiant>/callback` arrive quand même au retour par
  `sso_callback=<identifiant>`. Un serveur qui perd les deux les envoie sur la page d'accueil du
  wiki, ou une page inexistante, non connectées, et rien n'est enregistré.

Serveur par serveur :

- **Authelia** : donnez au client `token_endpoint_auth_method: client_secret_post`. Sans cela, le
  client doit utiliser `client_secret_basic` et refuse le wiki.
- **Shibboleth IdP** (extension OIDC) : enregistrez le client avec
  `token_endpoint_auth_method: client_secret_post` ; mettez `oidc.asArray` sur les règles de
  `email` et de `groups` ; importez les règles OIDC dans `shibboleth.AttributeRegistryResources` et
  donnez une règle à `sub` ; gardez le consentement sur le serveur
  (`idp.consent.StorageService = shibboleth.StorageService`), sinon userinfo n'envoie que `sub` et
  le wiki refuse en `missing_attribute`.
- **authentik** : perd la clé du retour et revient par `sso_callback=<identifiant>`, ce qui marche.
  Sa correspondance `email` par défaut dit toujours `email_verified: false`, son fournisseur OAuth2
  a besoin d'une clé de signature (sans elle ses jetons d'identité sont en HS256, que le wiki
  refuse).
- **ZITADEL** : envoie les rôles d'un projet en objet (`urn:zitadel:iam:org:project:roles`), que la
  correspondance des groupes ne sait pas lire. Ajoutez une information `groups` à plat, la liste des
  clés des rôles, avec une Action (v1, flux « Complement Token », déclencheur « Pre Userinfo
  creation »), et faites correspondre `groups`.
- **LemonLDAP::NG** 2.16 : réglez l'algorithme de signature des jetons d'identité du client sur
  RS256 ; son défaut, HS512, est refusé.
- **Apereo CAS** 7.3.8.3 : réglez
  `cas.authn.oauth.session-replication.cookie.pin-to-session=false`, sinon CAS perd sa propre
  session et renvoie les personnes sans code (`invalid_response`).
- **Pocket ID** : `email_verified` suit le champ `emailVerified` de l'utilisateur, faux par défaut ;
  une personne sans email demande `requireUserEmail` désactivé.

### SAML 2.0

Ce que le wiki attend de tout fournisseur d'identité :

- **Une assertion signée.** Une réponse dont la seule signature porte sur la réponse elle-même est
  refusée en `invalid_response` (« The Assertion of the Response is not signed and the SP requires
  it »).
- **Pas de chiffrement**, sauf si le fournisseur a reçu un **Certificat de ce wiki** et une **Clé
  privée de ce wiki** : sans eux, les métadonnées du wiki n'ont pas de clé pour laquelle chiffrer.

Serveur par serveur :

- **Apereo CAS** : ne signe par défaut que la réponse, quoi que demandent les métadonnées du wiki.
  Mettez `signAssertions` à vrai sur le service SAML du wiki.
- **Keycloak** : gardez l'assertion signée sur le client SAML (`saml.assertion.signature`) ; la
  signature du document seule est refusée.
- **Shibboleth IdP** : chiffre les assertions par défaut et échoue pour un SP sans clé, ce que le
  wiki refuse en `provider_rejected` (`Responder`). Mettez `encryptAssertions` à faux pour le wiki,
  ou donnez au wiki un certificat et une clé.
- **authentik** : avec une clé de signature, son fournisseur SAML doit signer l'assertion
  (`sign_assertion`).
- **ZITADEL** : n'envoie aucun rôle en SAML : une Action (v1) qui ajoute un attribut `groups` les
  apporte. Une valeur d'attribut avec `&` casse ses signatures, et les connexions de la personne
  sont refusées : n'envoyez pas une telle valeur.

### CAS

Ce que le wiki attend de tout serveur CAS :

- **Des attributs dans la réponse de validation** : à `/p3/serviceValidate` en version 3.0, à
  `/serviceValidate` en 2.0. Sans eux, seul l'identifiant arrive, et chaque connexion est refusée
  faute d'email.
- **Des valeurs d'attributs échappées dans le XML.** LemonLDAP::NG envoie une valeur avec `&` telle
  quelle : la réponse est illisible, et les connexions de la personne sont refusées en
  `invalid_response` (`is not well-formed XML`). Le module `casserver` de SimpleSAMLphp abandonne un
  tel attribut avec un avertissement PHP : la valeur manque sans erreur, et la réponse ne casse que
  si `display_errors` de PHP est actif. N'envoyez pas une telle valeur en CAS.
- **Des tickets à usage unique.** Le wiki compte sur le serveur pour refuser un ticket déjà validé.

Serveur par serveur :

- **Apereo CAS** : en version 2.0, les attributs ne sont envoyés qu'avec
  `cas.view.cas2.v3-forward-compatible=true`. La version 3.0 ne demande rien.
- **Shibboleth IdP** : n'a pas de `/p3/serviceValidate` : choisissez la version **2.0**, avec l'URL
  du serveur `https://<idp>/idp/profile/cas`. Choisissez `simpleTicketService` dans
  `cas-protocol.xml` : le service de tickets par défaut laisse un ticket reconnecter quelqu'un
  pendant ses 15 secondes.
- **SimpleSAMLphp** (module `casserver`) : n'a pas de `/p3/serviceValidate` : choisissez la version
  **2.0**. Avec SimpleSAMLphp 2.4 (`casserver` 6.x), le module ne répond qu'à `login.php`,
  `serviceValidate.php`... : le serveur web doit réécrire `/login`, `/serviceValidate` et les autres
  adresses du protocole vers elles, sinon les personnes arrivent sur une page « introuvable ».
  Désactivez `display_errors` de PHP : sinon l'avertissement pour une valeur d'attribut avec `&`
  s'imprime dans la réponse et la casse.

### LDAP

Ce que le wiki attend de tout annuaire :

- **Chacun peut lire sa propre entrée.** Le wiki lit l'entrée de la personne en se connectant
  comme elle, même quand un compte de recherche l'a trouvée : elle doit pouvoir lire son propre
  email, ses groupes, les attributs du modèle de nom des comptes et les attributs supplémentaires. Ce qu'elle ne peut pas lire reste
  vide, ou, pour l'email, fait refuser la connexion.
- **Un modèle de DN de connexion qui donne un DN.** `{login}@ad.example.org` permet de se connecter
  à Active Directory, mais le wiki lit ensuite l'entrée à ce nom et échoue : utilisez plutôt une
  recherche.
- **Un identifiant qui vient avec l'entrée, en texte.** Un attribut binaire (`objectGUID`
  d'Active Directory) est refusé, en le disant ; un attribut opérationnel (`nsUniqueId` de
  389 Directory Server) n'est pas envoyé, puisque le wiki demande les attributs ordinaires de
  l'entrée et `memberOf`. Avec un identifiant de connexion pour identifiant (`uid`,
  `sAMAccountName`), une personne dont l'identifiant est renommé reçoit un nouveau compte à sa
  connexion suivante, ou est refusée en `email_conflict` ; l'ancien compte reste.

Serveur par serveur :

- **Active Directory** (Samba AD DC) : trouvez les personnes par une recherche, avec un compte de
  recherche et `(sAMAccountName={login})`. `memberOf` ne donne que les groupes directs : pour les
  groupes imbriqués, renseignez un **DN de base des groupes** et le **Filtre des groupes**
  `(member:1.2.840.113556.1.4.1941:={dn})` ; chaque groupe est alors son cn. Prenez
  `sAMAccountName` ou `userPrincipalName` pour identifiant.
- **389 Directory Server** : l'extension MemberOf est éteinte dans une instance neuve
  (`dsconf <instance> plugin memberof enable`, puis redémarrage), ou utilisez un DN de base des
  groupes, qui ne donne que les groupes directs. Les règles d'accès par défaut ne laissent pas une
  personne lire son propre `departmentNumber` ; une règle comme celle-ci, sur
  `ou=people`, le lui permet :

  ```
  (targetattr="departmentNumber")(targetfilter="(objectClass=inetOrgPerson)")
  (version 3.0; acl "Enable self read of department";
  allow (read, search, compare)(userdn="ldap:///self");)
  ```

- **OpenLDAP** : un slapd neuf ne calcule pas `memberOf`. Donnez-lui l'overlay `dynlist` avec
  `olcDynListAttrSet: groupOfURLs memberURL member+memberOf@groupOfNames*` (groupes imbriqués
  compris ; il remplace l'overlay `memberof`, déprécié), ou renseignez un **DN de base des
  groupes**, qui ne donne que les groupes directs. `entryUUID` est opérationnel, donc pas envoyé :
  prenez `uid`, ou `uidNumber`, pour identifiant.
- **FreeIPA** : le serveur du wiki doit faire confiance à l'autorité de certification propre à
  FreeIPA. `ipaUniqueID` peut servir d'identifiant. Son domaine d'email par défaut donne
  `<login>@<domaine>` à toute personne créée sans email, et le wiki rattache ou crée des comptes
  sur cette adresse.
- **YunoHost** : son annuaire est un OpenLDAP qui n'écoute en clair que sur
  `ldap://localhost:389`, pour les applications de la même machine. Un wiki sur cette machine
  prend cette adresse, et le mot de passe ne quitte pas la machine ; renseignez le **Modèle de DN
  de connexion** `uid={login},ou=users,dc=yunohost,dc=org` et faites correspondre les groupes tels
  que `memberOf` les donne, `cn=<groupe>,ou=groups,dc=yunohost,dc=org` (il donne aussi
  `all_users` et le groupe propre à chaque personne). Ou cherchez sous
  `ou=users,dc=yunohost,dc=org` sans compte de recherche : YunoHost laisse chacun lire toutes les
  entrées sauf les mots de passe. Prenez `uidNumber` ou `uid` pour identifiant (`entryUUID`
  n'est pas envoyé). Un wiki sur une autre machine demande que slapd y écoute et que le port soit
  ouvert (`yunohost firewall open 389`), et devrait passer par `ldaps://` (port 636) ou StartTLS :
  remplacez d'abord le certificat que sert slapd, dans `/etc/yunohost/certs/yunohost.org/`, que
  personne ne peut vérifier tel qu'installé.
- **authentik** (avant-poste LDAP) : `uid` est une empreinte, `cn` l'identifiant de connexion :
  cherchez avec `(cn={login})` sous `ou=users,<suffixe>` (le suffixe entier trouve aussi le groupe
  propre à la personne). Le compte de recherche a besoin de `search_full_directory`.
- **Kanidm** 1.12.0 : une personne connectée ne lit que ce que lit l'accès anonyme, si bien que le
  wiki ne reçoit pas d'email. Son LDAP ne peut pas servir avec le wiki ; son OpenID Connect, si.

## Tester un fournisseur

**Tester** (dans la liste) et **Enregistrer et tester** (sur l'écran de modification) font une
vraie connexion auprès du fournisseur, dans votre propre navigateur, et vous montrent ce qui est
revenu au lieu de connecter qui que ce soit :

- les étapes atteintes : envoi au fournisseur, réponse du fournisseur, lecture des attributs,
  compte trouvé, groupes ;
- chaque attribut renvoyé par le fournisseur, et ce que le wiki en a fait ;
- ce que contiendrait le compte, les groupes du wiki qu'il rejoindrait et quitterait, et les
  groupes du fournisseur qu'aucune ligne ne fait correspondre ;
- l'erreur brute, s'il y en a une ;
- et une phrase qui dit ce qu'aurait fait une vraie connexion : créer un compte, rattacher un
  compte existant, ouvrir un compte déjà lié, ou refuser, et pourquoi.

Un bandeau rappelle que personne n'a été connecté et que rien n'a été modifié. Un test n'écrit rien
dans le Journal.

Pour un fournisseur à bouton (OpenID Connect, SAML, CAS), vous êtes envoyé·e chez le fournisseur et
vous vous y connectez **en tant que la personne que vous voulez tester** : un compte de test de
l'entreprise, ou le vôtre. Pour un fournisseur à mot de passe (LDAP), le wiki demande un
identifiant et un mot de passe de test.

Les tests sont réservés aux administrateurices. Un test ne peut être terminé que par
l'administrateurice qui l'a lancé, toujours connecté·e : quelqu'un d'autre qui revient avec est
refusé. Un fournisseur désactivé peut être testé : c'est la façon d'essayer un fournisseur avant que
quiconque puisse s'en servir. Les comptes locaux ne se testent pas ici.

Un test SAML vous reconnaît à votre connexion au wiki : le cookie `yw-auth` doit revenir avec la
réponse du fournisseur d'identité, qui est un envoi de formulaire depuis le site de celui-ci. Sur
un wiki en `https://`, cela marche avec le réglage `cookies_samesite` du wiki à sa valeur par
défaut, `None`. Réglé à `Lax` ou `Strict`, le navigateur n'envoie pas `yw-auth` avec ce formulaire
et le test est refusé, comme non terminé par l'administrateurice qui l'a lancé. Les vraies
connexions n'en souffrent pas : leur propre cookie de courte durée revient toujours.

Le test est aussi le moyen le plus rapide de voir ce que renvoie une connexion qui marche, avant
d'écrire une correspondance de groupes ou un attribut supplémentaire.

## Quand quelqu'un ne peut pas se connecter

La personne voit une page titrée « Nous n'avons pas pu vous connecter », qui dit simplement ce qui
s'est passé en nommant le fournisseur. Elle l'invite à vous contacter en donnant l'**heure** et
l'**email ou l'identifiant** affichés. Un mauvais mot de passe dans le formulaire est signalé dans
le formulaire lui-même.

De votre côté, ouvrez **Connexions refusées** depuis l'écran de connexion, ou le Journal
(`?admin/logs`), et tapez l'email ou l'identifiant dans le filtre **Sur**. Vous y trouvez deux
sortes d'entrées :

- **une entrée par tentative refusée**, gardée 365 jours : qui, quel fournisseur, et la raison.
  Chaque refus et chaque mauvais mot de passe y figure ;
- **une entrée détaillée par fournisseur, raison et jour**, gardée 14 jours, pour les refus
  survenus après la réponse du fournisseur et pour les pannes (pas pour les mauvais mots de passe).
  Sa colonne **Pourquoi** montre les étapes atteintes, la raison en clair, un lien vers les réglages
  du fournisseur, chaque attribut renvoyé et ce qu'il est devenu, le résultat de la correspondance
  des groupes et l'erreur brute. Dans la journée, un nouveau refus pour le même fournisseur et la
  même raison remplace les détails précédents : elle montre la dernière tentative. Pour voir les
  attributs d'une personne en particulier, demandez-lui de réessayer, ou utilisez le test.

Les raisons, et que faire :

| Raison                    | Ce que ça veut dire                                                                              | Que faire                                                                                          |
| ------------------------- | ------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------- |
| `provider_unreachable`    | Le wiki n'a pas pu joindre le fournisseur : réseau, DNS, certificat TLS, délai dépassé, ou un proxy devant lui qui a répondu 502, 503 ou 504 (le fournisseur est arrêté ou surchargé). | Vérifiez que le serveur joint le fournisseur et fait confiance à son certificat, puis testez.      |
| `provider_rejected`       | Le fournisseur a renvoyé une erreur, ou a refusé la personne.                                    | Lisez l'erreur brute ; vérifiez les réglages du client et l'accès de la personne chez le fournisseur. |
| `invalid_response`        | La réponse n'a pas passé une vérification : state, signature, audience, assertion expirée, ou la connexion a pris plus de 10 minutes ou a commencé dans un autre navigateur. | Demandez à la personne de recommencer, dans un seul navigateur. Si ça persiste, vérifiez les clés et l'horloge du serveur. |
| `configuration_invalid`   | Les réglages du fournisseur, ou un module PHP dont ils ont besoin, sont inutilisables.           | Corrigez les réglages ou installez le module, puis testez.                                         |
| `missing_attribute`       | Le fournisseur n'a pas envoyé l'identifiant ou l'email sous le nom configuré, ou l'a envoyé sous une forme que le wiki ne peut pas utiliser : binaire (l'`objectGUID` d'Active Directory), pas du texte (un objet), ou, comme email, autre chose qu'une seule adresse (plusieurs jointes par des espaces, des virgules ou des points-virgules). | Testez pour voir ce qu'il envoie ; corrigez les noms d'attributs, ou ce que le fournisseur envoie. |
| `email_conflict`          | Un compte a déjà cet email, et le rattachement par email est désactivé, ou le fournisseur ne garantit pas l'adresse. | Activez le rattachement si le fournisseur vérifie les emails, ou corrigez l'email du compte existant. |
| `linked_elsewhere`        | Le compte qui a cet email appartient à un autre fournisseur.                                     | Elle se connecte avec le fournisseur nommé dans l'erreur. Ou supprimez le compte : il sera recréé. |
| `no_account`              | Aucun compte n'a cet email, et ce fournisseur ne crée pas de comptes.                            | Créez le compte à la main, ou activez la création de comptes.                                      |
| `provider_disabled`       | Le fournisseur est désactivé, ou son type n'est plus installé.                                   | Activez-le, ou activez l'extension qui fournit son type.                                           |
| `account_creation_failed` | Le compte n'a pas pu être écrit (le wiki est en hibernation, par exemple).                       | Lisez l'erreur brute, vérifiez que le wiki accepte les modifications, et demandez de réessayer.    |
| `wrong_credentials`       | Le fournisseur à mot de passe a rejeté l'identifiant ou le mot de passe.                         | Rien, sauf si cet identifiant ne marche jamais.                                                     |
| `internal_error`          | Un événement imprévu s'est produit dans le wiki.                                                 | Lisez l'erreur brute, ici et dans le journal du serveur, et signalez-la si elle persiste.          |

**Les connexions réussies ne sont enregistrées nulle part.** Une connexion qui réussit mais
rencontre un problème (le nouvel email du fournisseur est déjà pris par un autre compte, un groupe
n'a pas pu être mis à jour) laisse un avertissement dans le Journal, parce que quelque chose
demande votre attention.

### Ce qui est enregistré, et où

Le Journal écrit aussi chaque entrée dans le **journal du serveur** (la sortie d'erreur du serveur
web ou de PHP), avec tous ses détails. Donc **les attributs renvoyés par un fournisseur lors d'une
connexion refusée arrivent dans le journal du serveur** : noms, emails, groupes et tout ce que le
fournisseur envoie. Ce sont des données personnelles : gardez ce journal comme vous gardez les
autres données personnelles du serveur, et sachez qui peut le lire.

Les mots de passe, jetons, secrets de client, assertions SAML et cookies ne sont jamais écrits, ni
dans le Journal ni dans le journal du serveur. Les attributs sont réduits à une taille raisonnable
avant d'être écrits.

## Contrôles de santé

La page **Santé** (`?admin/health`) signale ce qui empêche la connexion de fonctionner :

| Contrôle                   | Signale                                                                                                        |
| -------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `sign-in-no-provider`      | Aucun fournisseur n'est activé et utilisable : personne ne peut se connecter.                                  |
| `sign-in-config`           | Des entrées de `sso_providers` que le wiki ignore (sans type, d'un type non installé, d'un type déclaré deux fois). |
| `sign-in-orphan-accounts`  | Des comptes liés à un fournisseur qui n'est plus dans la configuration : ils ne peuvent plus se connecter.      |
| `sign-in-ldap-module`      | Un fournisseur LDAP activé, alors que l'extension PHP `ldap` n'est pas chargée.                                |
| `sign-in-plain-http`       | Un avertissement qui nomme chaque fournisseur activé joint à une adresse `http://`, et le réglage concerné.     |

Les adresses en `http://` simple sont acceptées, parce que les serveurs de test et de développement
n'ont souvent rien d'autre, mais pas en silence : l'écran de modification du fournisseur affiche un
avertissement pour chaque adresse qui commence par `http://`, et ce contrôle les nomme sur la page
Santé. Ce qui passe par une connexion non chiffrée,
l'identité de qui se connecte comprise, peut être lu et modifié en chemin. Utilisez `https://`
partout ailleurs.

## Changer de serveur

Quand l'entreprise change de serveur d'identité, **modifiez le fournisseur existant** plutôt que
d'en ajouter un nouveau : les comptes restent liés à lui, avec leur contenu et leurs groupes.

Si le nouveau serveur garde les mêmes identifiants, changer les réglages de connexion suffit. S'il
en donne de nouveaux, activez **Rattacher au compte existant qui a le même email** le temps du
changement : à la première connexion de chaque personne sur le nouveau serveur, le wiki retrouve
son compte par l'email et remplace l'identifiant enregistré par le nouveau. Désactivez-le ensuite,
une fois que tout le monde s'est connecté, sauf si le serveur vérifie les emails.

On ne peut pas déplacer un compte d'un fournisseur à un autre, ni le rendre local.

## Revenir dans le wiki

Si le serveur d'un fournisseur est en panne et que les comptes locaux sont désactivés, personne ne
peut se connecter, vous compris·e. Quiconque a un terminal sur le serveur peut réactiver les
comptes locaux, dans le dossier du wiki :

1. Réactivez les comptes locaux :

   ```sh
   ./yeswicli sign-in:enable local
   ```

   Modifier `sso_providers` dans `yeswiki.config.php` n'y suffit pas : dès que l'écran de connexion
   ou la mise à jour a enregistré les fournisseurs, le wiki les lit dans sa base de données (voir
   [Où sont gardés les fournisseurs](connexion-sso.md#où-sont-gardés-les-fournisseurs)).

2. Votre compte d'administrateurice est sans doute lié au fournisseur en panne, et un compte lié
   n'a pas de mot de passe. Créez un nouveau compte local d'administration en ligne de commande,
   dans le dossier du wiki :

   ```sh
   ./yeswicli user:create SecoursAdmin secours-admin@example.org --ask-password --group admins
   ```

   `--ask-password` demande le mot de passe sans l'afficher. Pour ajouter un compte existant à un
   groupe : `./yeswicli user:add-to-group <nom> admins`.

3. Connectez-vous avec ce compte. Une fois le fournisseur réparé, désactivez à nouveau les comptes
   locaux si vous le souhaitez (`./yeswicli sign-in:disable local`, ou l'écran), et supprimez le
   compte de secours.

## Où sont gardés les fournisseurs

Tout ce que l'écran de connexion modifie tient dans un seul réglage, `sso_providers`. Comme tout
réglage qu'un écran d'administration enregistre, il est gardé dans la base de données du wiki,
jamais dans `yeswiki.config.php` : chaque serveur d'un wiki répliqué voit un changement dès la
requête suivante. La mise à jour y enregistre aussi les fournisseurs qu'elle convertit.

Un déploiement peut aussi écrire `sso_providers` dans `yeswiki.config.php`, sous la forme
ci-dessous. Le fichier ne compte que tant que le réglage n'a pas été enregistré : ensuite, la
valeur de la base remplace toute la section du fichier, et modifier le fichier ne change plus
rien. Pour changer les fournisseurs d'un wiki en service, passez par l'écran, ou par
`./yeswicli sign-in:enable` et `sign-in:disable` pour en activer ou désactiver un.

L'exemple ci-dessous montre toutes les options, avec un fournisseur de chaque type. Les options communes
(tout ce qui n'est pas sous `connection`) sont décrites sur le fournisseur OpenID Connect et
s'écrivent de même pour tous les types. Une option en commentaire (`// 'clé' => ...`) est
facultative, ou une autre façon de faire que celle de la ligne active.

```php
'sso_providers' => [
    // Comptes du wiki. Le type `local` n'est accepté que sous la clé `local`, et il n'a pas de
    // `connection`. Sans section `sso_providers`, il existe et il est activé.
    'local' => [
        'type' => 'local',
        // Nom de son onglet dans le formulaire à mot de passe. Par défaut « Compte du wiki »,
        // traduit dans la langue de la page.
        // 'label' => 'Compte du wiki',
        'enabled' => true,
    ],

    // OpenID Connect ou OAuth2. La clé, ici `acme-sso`, est l'identifiant du fournisseur.
    'acme-sso' => [
        // `local`, `oidc`, `saml`, `cas`, `ldap`, ou le type qu'une extension ajoute.
        'type' => 'oidc',
        // Le nom du bouton (« Continuer avec Acme SSO »). Par défaut, l'identifiant.
        'label' => 'Acme SSO',
        // Par défaut `true`. Désactivé, le fournisseur peut quand même être testé.
        'enabled' => true,
        'connection' => [
            // Le document de découverte, où le wiki lit toutes les autres adresses.
            'discovery_url' => 'https://login.acme.example/.well-known/openid-configuration',
            // Sans découverte (OAuth2 simple), ces trois adresses sont obligatoires. À côté d'une
            // découverte, chacune l'emporte sur ce que dit la découverte.
            // 'authorize_url' => 'https://login.acme.example/authorize',
            // 'token_url' => 'https://login.acme.example/token',
            // 'userinfo_url' => 'https://login.acme.example/userinfo',
            // Sans découverte, ces deux-là font vérifier le jeton d'identité (signature, émetteur,
            // audience, expiration, nonce) ; sans elles, il est ignoré.
            // 'issuer' => 'https://login.acme.example',
            // 'jwks_uri' => 'https://login.acme.example/jwks',
            // Obligatoire.
            'client_id' => 'yeswiki',
            // Obligatoire. Un secret : l'écran ne l'affiche plus une fois enregistré.
            'client_secret' => '...',
            // Par défaut `openid email profile`. Séparées par des espaces ou des virgules, ou en
            // liste PHP : ['openid', 'email', 'profile', 'groups'].
            'scopes' => 'openid email profile',
            // Sans écran : ce qui sépare les portées dans la demande envoyée au fournisseur, une
            // espace par défaut. Seule la mise à jour depuis `loginsso` l'écrit.
            // 'scope_separator' => ',',
        ],
        // Les noms des attributs chez le fournisseur. Une clé absente prend le nom habituel du
        // type : `sub`, `email` et `groups` pour OpenID Connect.
        'attributes' => [
            // Identifiant de la personne : stable, jamais redonné à quelqu'un d'autre.
            'id' => 'sub',
            'email' => 'email',
            // Les groupes, que lit `group_mapping`.
            'groups' => 'groups',
        ],
        // Par défaut `true`. Créer un compte à la première connexion ; à `false`, seules les
        // personnes qui ont déjà un compte se connectent.
        'create_accounts' => true,
        // Par défaut `false`. Rattacher au compte existant qui a le même email : seulement si le
        // fournisseur vérifie les emails qu'il envoie.
        'match_by_email' => false,
        // Le nom des comptes créés, chaque `#[attribut]` remplacé par sa première valeur. Absent,
        // le nom vient du début de l'email, puis de l'identifiant.
        'account_name_template' => '#[given_name] #[family_name]',
        // Par défaut `true`. Faire du nom un NomWiki (majuscules automatiques), ou, à `false`, le
        // garder tel que le fournisseur l'envoie.
        'account_name_pascalcase' => true,
        // Correspondance des groupes, une ligne par paire : le groupe du fournisseur (comparé à
        // l'identique, majuscules comprises), puis le groupe du wiki. Les groupes du wiki
        // qu'aucune ligne ne nomme ne sont jamais touchés.
        'group_mapping' => [
            ['provider' => 'wiki-editors', 'wiki' => 'Editors'],
            ['provider' => 'staff', 'wiki' => 'Editors'],
        ],
        // Attributs supplémentaires : champ du formulaire des utilisateurices => attribut du
        // fournisseur qui le remplit. La personne ne peut plus modifier ces champs.
        'extra_fields' => [
            'service' => 'department',
        ],
    ],

    // SAML 2.0. Le côté du fournisseur d'identité se donne d'une de ces trois façons.
    'universite' => [
        'type' => 'saml',
        'label' => 'Université',
        'enabled' => true,
        'connection' => [
            // 1. L'adresse de ses métadonnées.
            'metadata_url' => 'https://idp.example.org/idp/shibboleth',
            // 2. Ou ses métadonnées, en entier. Données avec `metadata_url`, elles l'emportent.
            // 'metadata_xml' => '<md:EntityDescriptor ...>...</md:EntityDescriptor>',
            // 3. Ou, sans métadonnées, ces trois valeurs. Données avec des métadonnées, chacune
            //    l'emporte sur ce qu'elles disent.
            // 'idp_entity_id' => 'https://idp.example.org/idp/shibboleth',
            // 'idp_sso_url' => 'https://idp.example.org/idp/profile/SAML2/Redirect/SSO',
            // 'idp_certificate' => "-----BEGIN CERTIFICATE-----\nMIID...\n-----END CERTIFICATE-----",
            // Facultatifs, les deux ou aucun : le certificat et la clé privée du wiki, pour signer
            // ses demandes et lire les assertions chiffrées. La clé privée est un secret.
            // 'sp_certificate' => "-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----",
            // 'sp_private_key' => "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----",
        ],
        // Les noms habituels de SAML. `name_id` est le NameID de l'assertion, qui doit alors être
        // persistant.
        'attributes' => ['id' => 'name_id', 'email' => 'mail', 'groups' => 'groups'],
    ],

    // CAS 2.0 ou 3.0.
    'cas-universite' => [
        'type' => 'cas',
        'label' => 'CAS Université',
        'enabled' => true,
        'connection' => [
            // Obligatoire : l'adresse sous laquelle sont les pages `login` et `serviceValidate`.
            'server_url' => 'https://cas.example.org/cas',
            // `3.0` (par défaut) ou `2.0`.
            'version' => '3.0',
            // Le chemin, sur le serveur du wiki, du fichier PEM de l'autorité qui a signé le
            // certificat du serveur CAS. Absent, les autorités du système.
            // 'ca_cert' => '/etc/ssl/certs/autorite-privee.pem',
        ],
        // Les noms habituels de CAS. `user` est l'identifiant que le serveur valide.
        'attributes' => ['id' => 'user', 'email' => 'mail', 'groups' => 'groups'],
    ],

    // Annuaire LDAP, interrogé avec l'identifiant et le mot de passe saisis dans le wiki.
    'annuaire' => [
        'type' => 'ldap',
        // Le nom de son onglet dans le formulaire à mot de passe.
        'label' => 'Annuaire',
        'enabled' => true,
        'connection' => [
            // Obligatoire : `ldaps://hôte:port`, ou `ldap://hôte:port`.
            'uri' => 'ldaps://ldap.example.org:636',
            // Par défaut `false`. À `true` avec une adresse `ldap://`, pour chiffrer avec StartTLS.
            'start_tls' => false,
            // La personne se trouve d'une de ces deux façons :
            // 1. le DN sous lequel elle se connecte, `{login}` à la place de son identifiant ;
            'bind_dn_template' => 'uid={login},ou=people,dc=example,dc=org',
            // 2. ou, sans modèle, une recherche sous `base_dn` avec `user_filter` (par défaut
            //    `(uid={login})` ; Active Directory : `(sAMAccountName={login})`), faite sous un
            //    compte de recherche si l'annuaire refuse les recherches anonymes. Le mot de passe
            //    du compte de recherche est un secret, obligatoire avec `search_dn`.
            // 'base_dn' => 'ou=people,dc=example,dc=org',
            // 'user_filter' => '(uid={login})',
            // 'search_dn' => 'cn=wiki,ou=services,dc=example,dc=org',
            // 'search_password' => '...',
            // Les groupes : sans `group_base`, l'attribut des groupes de la personne (`memberOf`),
            // chaque groupe étant son DN complet. Avec `group_base`, les groupes que
            // `group_filter` y trouve, chacun étant son `cn` ; dans le filtre, `{dn}` est le DN de
            // la personne et `{login}` son identifiant. Par défaut `(member={dn})`.
            // 'group_base' => 'ou=groups,dc=example,dc=org',
            // 'group_filter' => '(member={dn})',
        ],
        // Les noms habituels de LDAP.
        'attributes' => ['id' => 'uid', 'email' => 'mail', 'groups' => 'memberOf'],
        'match_by_email' => true,
        // Sans écran : seule la mise à jour l'écrit, sur les fournisseurs LDAP et CAS qu'elle
        // convertit. Jusqu'à cette date, une première connexion rattache aussi le compte qui porte
        // l'identifiant de la personne pour nom ; `UPGRADE.md` dit ce qu'il permet d'autre.
        // 'match_by_name_until' => '2027-11-16',
    ],
],
```

- L'**ordre** des entrées est celui de la page de connexion. L'écran ajoute un fournisseur à la
  fin et ne change pas l'ordre.
- La **clé** est l'identifiant du fournisseur. Elle figure dans l'adresse de retour enregistrée
  chez le fournisseur, et les comptes y sont liés : ne la changez pas.
- Une entrée sans `type`, d'un type qui n'est pas installé, ou de type `local` sous une autre clé
  que `local`, est ignorée ; le contrôle de santé `sign-in-config` la nomme.
- Les options à `true` ou `false` acceptent aussi `'1'`, `'yes'`, `'on'` et leurs contraires :
  toute valeur qui n'est pas un oui vaut `false`, pour qu'une valeur mal écrite n'active jamais
  ce qu'elle voulait laisser éteint. Une option absente prend sa valeur par défaut.
- Le type d'une extension a ses propres réglages sous `connection` : l'écran de modification les
  affiche, et l'extension les documente.
- Les secrets de cette section (`client_secret`, `sp_private_key`, `search_password`) sont lus au
  moment où il en faut, et jamais copiés dans le cache compilé du wiki. Ils sont gardés dans la
  base de données du wiki, avec les autres réglages : protégez ses sauvegardes comme la base
  elle-même.

**http simple et SAML.** En `https`, le wiki retient une connexion en cours dans un cookie,
`yw-sign-in`, qui dure 10 minutes et est envoyé avec `SameSite=None; Secure`, pour que la réponse
SAML, un envoi de formulaire depuis le site du fournisseur d'identité, l'emporte avec elle. En
`http` simple, les navigateurs refuseraient un tel cookie : il est envoyé en `SameSite=Lax`, et
SAML ne marche alors que si le wiki et le fournisseur d'identité sont sur le même site (par exemple
`wiki.example.org` et `idp.example.org`). Les autres types ne sont pas concernés.

## Pour les développeurs et développeuses

Ajouter un type de fournisseur depuis une extension est décrit dans
[la documentation de développement](dev.md#ajouter-un-fournisseur-de-connexion). La décision derrière ce fonctionnement est
`docs/adr/0031-sign-in-providers-replace-the-login-extensions.md`.
