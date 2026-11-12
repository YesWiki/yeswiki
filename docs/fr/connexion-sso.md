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

> Ceci remplace les extensions de Doryphore `loginsso`, `loginldap` et `logincas`. Un wiki mis à
> jour depuis Doryphore arrive avec ses réglages déjà convertis : voir `UPGRADE.md`, section
> _Sign-in providers replace the login extensions_.

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
s'ajoute en fin de liste ; pour changer l'ordre de la page de connexion, changez l'ordre des
entrées dans le fichier de configuration (voir
[Le fichier de configuration](connexion-sso.md#le-fichier-de-configuration)).

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
   cas par défaut). Son nom est fait du prénom et du nom, ou du début de l'email, ou de
   l'identifiant.

Si rien de tout cela ne marche, la connexion est refusée, et la personne voit pourquoi (voir
[Quand quelqu'un ne peut pas se connecter](connexion-sso.md#quand-quelquun-ne-peut-pas-se-connecter)).

À chaque connexion, le prénom, le nom, l'email et les éventuels
[attributs supplémentaires](connexion-sso.md#attributs-supplémentaires) du compte sont mis à jour depuis le
fournisseur, et ses [groupes](connexion-sso.md#correspondance-des-groupes) aussi. Rien n'est écrit si rien n'a
changé.

### Ce qu'un compte lié ne peut plus faire

- Son prénom, son nom, son email, ses champs remplis par le fournisseur et son mot de passe **ne
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
suivie de `user/sign-in/<identifiant>/callback`, par exemple
`https://wiki.example.org/?user/sign-in/acme-sso/callback` : vous pouvez donc la donner au
fournisseur avant d'enregistrer si vous connaissez l'identifiant.

**Attributs.** Les noms qu'emploie le fournisseur pour ce dont le wiki a besoin : **Identifiant de
l'utilisateur**, **Email**, **Prénom**, **Nom de famille** et **Groupes**. Laissez un champ vide
pour utiliser le nom habituel de ce type, affiché en gris. L'identifiant ne doit jamais changer pour
une personne, ni être redonné à quelqu'un d'autre : préférez un identifiant technique (`sub`, un
matricule, l'`uid` de l'annuaire) à un email.

Le wiki refuse une connexion sans identifiant, et sans email, en disant quel attribut il cherchait.
Un prénom ou un nom absent reste vide.

**Comptes.**

- **Créer un compte à la première connexion.** Activé par défaut. Désactivé, seules les personnes
  qui ont déjà un compte sur le wiki peuvent se connecter avec ce fournisseur.
- **Rattacher au compte existant qui a le même email.** Désactivé par défaut. **Ne l'activez que si
  le fournisseur vérifie les emails qu'il envoie** : sinon, quiconque peut choisir son email chez
  le fournisseur peut prendre le compte du wiki qui l'utilise. Un fournisseur OpenID Connect qui
  envoie `email_verified: false` n'est jamais rattaché par email, quel que soit ce réglage. C'est
  aussi le réglage à activer quand le fournisseur change de serveur et que les identifiants
  changent avec lui (voir [Changer de serveur](connexion-sso.md#changer-de-serveur)).

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
attribut du fournisseur. Ces champs deviennent non modifiables pour la personne, comme le nom et
l'email.

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
compte lui est alors rattaché à votre première connexion), ou désactivez les comptes locaux dans le
fichier de configuration.

### OpenID Connect ou OAuth2

1. Chez le fournisseur, créez un client (parfois appelé application) de type « confidentiel » ou
   « application web », avec le flux _authorization code_. Donnez-lui l'adresse de retour :
   `https://wiki.example.org/?user/sign-in/<identifiant>/callback`. Notez son **identifiant de
   client** et son **secret**.
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
   userinfo, comme avec `loginsso` sous Doryphore. Les adresses écrites ici l'emportent sur celles
   de la découverte.
4. Renseignez l'**Identifiant du client** et le **Secret du client**. Les **Portées (scopes)** sont
   par défaut `openid email profile` ; ajoutez celle qui donne les groupes si votre fournisseur en
   demande une.
5. Les noms d'attributs habituels sont `sub`, `email`, `given_name`, `family_name` et `groups`.
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
     réponse : `https://wiki.example.org/?user/sign-in/<identifiant>/callback`, en HTTP-POST.
3. Chez le fournisseur d'identité, enregistrez le wiki avec ces deux valeurs, et faites-lui
   **signer ses assertions** : le wiki refuse celles qui ne le sont pas. Faites-lui envoyer les
   attributs dont vous avez besoin.
4. Réglez les noms d'attributs. L'identifiant vaut par défaut `name_id` (le NameID de l'assertion) ;
   les autres `mail`, `givenName`, `sn` et `groups`. Beaucoup de fournisseurs d'identité envoient
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
   fournisseur, `https://wiki.example.org/?user/sign-in/<identifiant>/callback`.
6. L'identifiant vaut par défaut `user`, l'identifiant que le serveur CAS valide. Les autres
   valent `mail`, `givenName`, `sn` et `groups`, que le serveur doit envoyer comme attributs.
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
5. Les noms d'attributs habituels sont `uid`, `mail`, `givenName`, `sn` et `memberOf`.
6. **Enregistrer et tester** : le test demande un identifiant et un mot de passe.

L'identifiant et le mot de passe saisis dans le formulaire du wiki sont envoyés à l'annuaire, jamais
gardés. Un mot de passe vide est refusé avant d'interroger l'annuaire, parce que beaucoup
d'annuaires l'accepteraient comme une connexion anonyme.

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
| `provider_unreachable`    | Le wiki n'a pas pu joindre le fournisseur : réseau, DNS, certificat TLS ou délai dépassé.        | Vérifiez que le serveur joint le fournisseur et fait confiance à son certificat, puis testez.      |
| `provider_rejected`       | Le fournisseur a renvoyé une erreur, ou a refusé la personne.                                    | Lisez l'erreur brute ; vérifiez les réglages du client et l'accès de la personne chez le fournisseur. |
| `invalid_response`        | La réponse n'a pas passé une vérification : state, signature, audience, assertion expirée, ou la connexion a pris plus de 10 minutes ou a commencé dans un autre navigateur. | Demandez à la personne de recommencer, dans un seul navigateur. Si ça persiste, vérifiez les clés et l'horloge du serveur. |
| `configuration_invalid`   | Les réglages du fournisseur, ou un module PHP dont ils ont besoin, sont inutilisables.           | Corrigez les réglages ou installez le module, puis testez.                                         |
| `missing_attribute`       | Le fournisseur n'a pas envoyé l'identifiant ou l'email sous le nom configuré.                    | Testez pour voir ce qu'il envoie ; corrigez les noms d'attributs, ou ce que le fournisseur envoie. |
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
peut se connecter, vous compris·e. Tout est dans le fichier de configuration : quiconque a accès
aux fichiers du serveur peut réactiver les comptes locaux.

1. Dans `yeswiki.config.php`, mettez `'enabled' => true` sur l'entrée `local` de `sso_providers` :

   ```php
   'sso_providers' => [
       'local' => ['type' => 'local', 'enabled' => true],
       // ... les autres fournisseurs, sans changement
   ],
   ```

   Un wiki dont la section `sso_providers` est absente ou vide a ses comptes locaux activés.

2. Votre compte d'administrateurice est sans doute lié au fournisseur en panne, et un compte lié
   n'a pas de mot de passe. Créez un nouveau compte local d'administration en ligne de commande,
   dans le dossier du wiki :

   ```sh
   ./yeswicli user:create SecoursAdmin secours-admin@example.org --ask-password --group admins
   ```

   `--ask-password` demande le mot de passe sans l'afficher. Pour ajouter un compte existant à un
   groupe : `./yeswicli user:add-to-group <nom> admins`.

3. Connectez-vous avec ce compte. Une fois le fournisseur réparé, désactivez à nouveau les comptes
   locaux si vous le souhaitez, et supprimez le compte de secours.

## Le fichier de configuration

Tout ce que l'écran de connexion modifie se trouve dans une seule section de `yeswiki.config.php`,
`sso_providers`. L'écran écrit le fichier, comme les autres écrans d'administration, et un
déploiement peut l'écrire directement à la place :

```php
'sso_providers' => [
    'local' => ['type' => 'local', 'enabled' => false],
    'acme-sso' => [
        'type' => 'oidc',
        'label' => 'Acme SSO',
        'enabled' => true,
        'connection' => [
            'discovery_url' => 'https://login.acme.example/.well-known/openid-configuration',
            'client_id' => 'yeswiki',
            'client_secret' => '...',
        ],
        'attributes' => [
            'id' => 'sub',
            'email' => 'email',
            'first_name' => 'given_name',
            'last_name' => 'family_name',
            'groups' => 'groups',
        ],
        'create_accounts' => true,
        'match_by_email' => false,
        'group_mapping' => [
            ['provider' => 'wiki-editors', 'wiki' => 'Editors'],
        ],
        'extra_fields' => ['department' => 'service'],
    ],
],
```

- L'**ordre** des entrées est celui de la page de connexion.
- La **clé** est l'identifiant du fournisseur. Elle figure dans l'adresse de retour enregistrée
  chez le fournisseur, et les comptes y sont liés : ne la changez pas.
- `type` est le type du fournisseur ; `connection` contient les réglages de ce type, sous les noms
  utilisés plus haut (`discovery_url`, `metadata_xml`, `server_url`, `ca_cert`, `uri`,
  `bind_dn_template`...).
- `extra_fields` associe un champ du formulaire des utilisateurices à l'attribut qui le remplit.
- `account_name_template` (sans écran) nomme les nouveaux comptes à partir d'attributs, comme
  `'#[given_name] #[family_name]'`.
- `match_by_name_until` (sans écran) n'existe que sur les fournisseurs convertis depuis les
  extensions LDAP et CAS de Doryphore : jusqu'à cette date, une première connexion retrouve aussi
  un compte non lié qui porte le nom de l'identifiant de la personne, comme le faisait Doryphore,
  et un compte existant peut se connecter sans email, comme Doryphore l'acceptait. On peut le
  supprimer à tout moment, ce qui met fin aux deux.
- Les secrets de cette section sont lus au moment où il en faut, et jamais copiés dans le cache
  compilé du wiki. Ils sont dans le fichier de configuration lui-même : protégez-le comme le mot de
  passe de la base de données qui s'y trouve aussi.

**Plusieurs serveurs.** L'écran écrit le fichier de configuration du serveur sur lequel il tourne.
Si votre wiki tourne sur plusieurs serveurs (des répliques) qui ne partagent pas ce fichier,
n'utilisez pas l'écran pour changer les fournisseurs : gérez `sso_providers` par votre
déploiement, pour que chaque serveur ait la même section.

**http simple et SAML.** En `https`, le wiki retient une connexion en cours dans un cookie,
`yw-sign-in`, qui dure 10 minutes et est envoyé avec `SameSite=None; Secure`, pour que la réponse
SAML, un envoi de formulaire depuis le site du fournisseur d'identité, l'emporte avec elle. En
`http` simple, les navigateurs refuseraient un tel cookie : il est envoyé en `SameSite=Lax`, et
SAML ne marche alors que si le wiki et le fournisseur d'identité sont sur le même site (par exemple
`wiki.example.org` et `idp.example.org`). Les autres types ne sont pas concernés.

## Pour les développeurs et développeuses

Ajouter un type de fournisseur depuis une extension est décrit dans
[la documentation de développement](/docs/fr/dev?id=ajouter-un-fournisseur-de-connexion). La décision derrière ce fonctionnement est
`docs/adr/0031-sign-in-providers-replace-the-login-extensions.md`.
