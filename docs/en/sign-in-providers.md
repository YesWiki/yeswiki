# Sign-in providers

A **provider** is one way of signing in to the wiki. The wiki's own accounts, with a name and a
password kept by the wiki, are one provider, called **local**. The others let people sign in with
an account they already have elsewhere, usually their company's or organisation's:

| Type                    | What it talks to                                                              | How people sign in                                                 |
| ----------------------- | ----------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| OpenID Connect / OAuth2 | Keycloak, LemonLDAP::NG, Authentik, Microsoft Entra, Google...                | a button that sends them to the provider's page, then back         |
| SAML 2.0                | an identity provider (IdP): ADFS, Shibboleth, LemonLDAP::NG, Keycloak...      | a button, the same way                                             |
| CAS                     | a CAS server (2.0 or 3.0)                                                     | a button, the same way                                             |
| LDAP                    | a directory: OpenLDAP, Active Directory...                                    | their directory login and password, typed into the wiki's own form |
| Local                   | nothing: the wiki keeps the accounts                                          | a name or email and a password, typed into the wiki's form         |

Any number of providers can be switched on at once, including several of one type (two SAML
providers, for example). An extension can add more types; they appear in the same lists and work
the same way.

> A wiki upgraded from an earlier version arrives with its providers already converted. The
> sign-in section of `UPGRADE.md` says what is converted, and what to do **before** the upgrade:
> the callback address to register at an OpenID Connect server.

## Where to find it

In the dashboard, **Administration › Sign-in** (address `?admin/sign-in`). You must be signed in
as an administrator.

The screen lists the providers, in the order the sign-in page shows them, with their type, whether
they are switched on, and how many accounts belong to each. From there you can:

- **Add a provider**: the buttons at the bottom offer every type installed on the wiki;
- **Edit** a provider, and **Test** it (see [Testing a provider](#testing-a-provider));
- **Switch on** or **Switch off** a provider;
- **Delete** a provider, only while no account belongs to it. A provider with accounts can only be
  switched off;
- open **Failed sign-ins**, which is the Journal (`?admin/logs`) filtered on refused sign-ins.

Three rules keep you from locking everyone out: the screen refuses to switch off, or delete, the
**last provider that is switched on**; local accounts can be switched on and off but never
deleted; and if you are signed in with a local account, the screen refuses to switch local accounts
off unless a switched-on provider has **Connect to the existing account that has the same email**
ticked, since you would be signed out with no way back in (see [Local accounts](#local-accounts)).
A new provider is added at the end of the list, and the screen does not change the order of the
sign-in page (see [Where the providers are kept](#where-the-providers-are-kept)).

A provider whose type is no longer installed (its extension was switched off) stays in the list as
**Unavailable**. Its settings are kept, nobody can sign in with it, and it can only be switched off
or deleted until the extension is back.

## What happens when someone signs in

On the sign-in page (the `{{login}}` action and `?user`), each provider with a button
(OpenID Connect, SAML, CAS) shows a "Continue with..." button at the top. Below, "or with a
password" leads to one form, with a tab for each password provider (local, LDAP). Lost password and
sign-up only appear on the local tab.

**Every account belongs to exactly one place**: one provider, or local. An account that belongs to
a provider is **linked** to it: the wiki stores the provider's own user ID for that person, so a
later change of email at the company does not break anything.

When a provider says who someone is, the wiki looks for their account in this order:

1. an account already linked to this provider with this user ID: the person is signed in;
2. otherwise, an account with the same email. It is only used if **Connect to the existing account
   that has the same email** is on for this provider; it is then linked, and its wiki password is
   removed, since from now on it signs in through the provider. An account that belongs to another
   provider is never taken;
3. otherwise, a new account is created, if **Create an account the first time someone signs in** is
   on (it is by default). It is named from the provider's
   [account name template](#account-names), else from the start of the email, else from the
   user ID.

If none of this works, the sign-in is refused, and the person sees why (see
[When someone cannot sign in](#when-someone-cannot-sign-in)).

At every sign-in, the account's email and any
[extra attributes](#extra-attributes) are refreshed from the provider, and its
[groups](#group-mapping) are updated. Nothing is written when nothing changed.

### What a linked account can no longer do

- Its email, mapped extra fields and password **cannot be changed on the
  wiki**, by the person or by an administrator. Their profile says so, and tells them to correct
  these at the provider: the wiki picks up the new values at the next sign-in.
- It cannot use **Lost password** or change its password: it has none.
- It cannot sign in with the password form, and it stops working the moment its provider is
  switched off: someone already signed in is signed out at their next page.

### How long a sign-in lasts

A sign-in through a provider lasts **90 days**, and is extended as long as the person keeps using
the wiki. The wiki does not ask the provider again during that time: disabling someone at the
company does not sign them out of the wiki. **To end someone's access, delete their account on the
wiki, or switch off the provider.**

Signing out of the wiki signs out of the wiki only, not out of the company's provider.

## Adding a provider: what every type shares

Click the type under **Add a provider**. The form has the same parts for every type.

**Name.** Shown on the sign-in button ("Continue with Acme SSO"). The provider's **identifier** is
made from it once, when you first save (`Acme SSO` gives `acme-sso`), and never changes: it is in
the addresses the provider sends people back to. You can rename the provider later; the identifier
stays.

**Switched on.** A new provider is switched on by default. While you set it up, you can untick
it: a provider that is switched off can still be [tested](#testing-a-provider).

**Connection.** The settings of this type, described in the walkthroughs below. Secrets (client
secret, private key, passwords) are never shown again once saved: leave the field blank to keep
the stored one, or tick **Remove the stored value** beside it to take it out (for instance the
wiki's SAML key, to stop signing requests).

Once the provider is saved, this section also shows what to give the provider: the **Callback
URL** (where the provider sends people back), and for SAML the wiki's own **entity ID** and
**ACS URL**. The callback URL is the wiki's address followed by
`user/sign-in/<identifier>/callback&sso_callback=<identifier>`, for example
`https://wiki.example.org/?user/sign-in/acme-sso/callback&sso_callback=acme-sso`, so you can give
it to the provider before you save if you know the identifier. The identifier appears twice on
purpose: some servers rebuild the address's query and drop `user/sign-in/<identifier>/callback`,
a key without a value, and `sso_callback=<identifier>` still takes the person to the callback.

**Attributes.** The names the provider uses for what the wiki needs: **User ID**, **Email** and
**Groups**. Leave a field blank to use the usual name for this
type, shown in grey. The user ID must never change for a person and never be given to someone
else: prefer a technical identifier (`sub`, an employee number, a directory `uid`) to an email.

The wiki refuses a sign-in without a user ID, and without an email, and says which attribute it
was looking for. An email of spaces only counts as no email. An email that is not one address
(several joined in one value) is refused too, even for a person whose account is already linked:
the wiki does not sign them in with the email it holds.

**Accounts.**

- **Create an account the first time someone signs in.** On by default. Off, only people who
  already have an account on the wiki can sign in with this provider.
- **Name of the accounts created** and **Make the name a WikiName (automatic capitals)**. See
  [Account names](#account-names).
- **Connect to the existing account that has the same email.** Off by default. **Only turn it on
  if the provider checks the emails it sends**: otherwise anyone who can choose their email at the
  provider can take over the wiki account that has it. An OpenID Connect provider that sends
  `email_verified: false` is never matched by email, whatever this setting says. This is also the
  setting to turn on when you move the provider to a new server that gives people new user IDs (see
  [Changing server](#changing-server)).

### Account names

A new account is named from the **Name of the accounts created** template. Each `#[attribute]`
is replaced by the first value the provider sent under that name (the names Test shows). Leave it blank, or let none of its
attributes be sent, and the name comes from the start of the email, then from the user ID.

**Make the name a WikiName (automatic capitals)** says what is done with it:

- **On** (the default): punctuation splits words, accents go, each word takes a
  capital, the spaces go, and a name with fewer than two capitals gets its last letter
  capitalised.
- **Off**: the name stays as the provider sent it, its case and
  accents included; only spaces become dashes, and the characters a page name cannot hold go.

| Template                       | The provider sends        | On                     | Off                     |
| ------------------------------ | ------------------------- | ---------------------- | ----------------------- |
| `#[given_name] #[family_name]` | `Marie`, `Dupont-Lefèvre` | `MarieDupontLefevre`   | `Marie-Dupont-Lefèvre`  |
| `#[uid]`                       | `jdupont`                 | `JduponT`              | `jdupont`               |
| (none)                         | `marie.dupont@acme.example` | `MarieDupont`        | `marie.dupont`          |

When a page or an account already has the name, a number is appended: `MarieDupontLefevre2`,
`jdupont-2`. A name the wiki keeps for itself (`admin`, `user`, `api`...) is never given. The name
is chosen once, at the first sign-in: changing these settings does not rename anyone.

### Group mapping

Each row says: a person the provider puts in **this group** is put in **this wiki group**.
Several rows may name the same provider group. At every sign-in, the person is added to the wiki
groups their provider groups map to, and removed from the mapped wiki groups they no longer
qualify for. **Wiki groups that no row names are never touched**, so groups you manage by hand
stay as you set them. A wiki group that does not exist yet is created.

Group names are compared exactly, case included. Use [Test](#testing-a-provider) to see the
exact names the provider sends.

A sign-in never empties the `admins` group: the last administrator stays in it.

### Extra attributes

Each row fills a field of the user form (choose it in the list) with an attribute of the provider.
Those fields become read-only for the person, like the email.

### Save, or Save and test

**Save** goes back to the list. **Save and test** saves, then starts a test at once.

## Walkthroughs

### Local accounts

Local accounts have no settings: they can only be switched on or off, from the list. Switched
off, the password form loses its local tab, sign-up and lost password are unavailable,
`/api/login` refuses everyone, and local accounts are signed out at their next page.

Switch local off only once the other providers work, and keep in mind
[how to get back in](#getting-back-in) if the company's server fails.

If you are signed in with a local account yourself, switching local off signs you out too, so the
screen asks you to confirm first. It refuses outright when no switched-on provider has **Connect to
the existing account that has the same email** ticked: you could not sign in again. Tick it on the
provider you will sign in with (your account is then connected to it at your first sign-in), or
switch local off from the command line, in the wiki's folder: `./yeswicli sign-in:disable local`.

### OpenID Connect or OAuth2

1. At the provider, create a client (sometimes called an application) of the "confidential" or
   "web application" kind, using the authorization code flow. Give it the callback URL:
   `https://wiki.example.org/?user/sign-in/<identifier>/callback&sso_callback=<identifier>`. Note
   its **client ID** and
   **client secret**.
2. On the wiki, **Add a provider › OpenID Connect or OAuth2**, and give it a name.
3. Fill in the **Discovery URL**, which ends with `/.well-known/openid-configuration` (for
   example `https://login.example.org/realms/staff/.well-known/openid-configuration`). The wiki
   reads every other address from it.

   A plain OAuth2 server with no discovery document: leave the discovery URL empty and fill in the
   **Authorization endpoint**, **Token endpoint** and **Userinfo endpoint** instead. Add the
   **Issuer** and **Keys URL (jwks_uri)** if the server publishes them. With a discovery URL, or
   with both of these, the ID token is checked (signature, issuer, audience, expiry, nonce) and its
   claims are added to what userinfo returns; without them, the ID token is ignored and the
   identity comes from the userinfo endpoint only. Endpoints written here win over the discovered ones.
4. Fill in the **Client ID** and **Client secret**. **Scopes** defaults to
   `openid email profile`; add the scope that releases groups if your provider needs one.
5. The usual attribute names are `sub`, `email` and `groups`.
   Change them if your provider uses others.
6. **Save and test.**

The wiki uses the authorization code flow with `state`, a nonce and PKCE (S256).

### SAML 2.0

The wiki is the service provider (SP). Each SAML provider on the wiki is its own service provider,
with its own entity ID.

1. On the wiki, **Add a provider › SAML 2.0**, give it a name, untick **Switched on** for now,
   and fill in the identity provider's side, in one of three ways:
   - its **metadata URL**;
   - or its **metadata (XML)**, pasted whole, on as many lines as it has;
   - or, without metadata, its **entity ID**, its **single sign-on URL** and its **signing
     certificate** (PEM, pasted on several lines if you like). These three values win over the
     metadata when both are given.
2. Save. The edit screen now shows what to copy into the identity provider:
   - **This wiki's entity ID**, which is also the address of the wiki's metadata:
     `https://wiki.example.org/?user/sign-in/<identifier>/metadata`. Many identity providers can
     read it directly from there;
   - the **Assertion consumer service (ACS) URL**, where the identity provider posts its answer:
     `https://wiki.example.org/?user/sign-in/<identifier>/callback&sso_callback=<identifier>`,
     with the HTTP-POST binding.
3. At the identity provider, register the wiki with these two values, and have it **sign its
   assertions**: the wiki refuses unsigned ones. Have it release the attributes you need.
4. Set the attribute names. The user ID defaults to `name_id` (the NameID of the assertion); the
   others to `mail` and `groups`. Many identity providers send names like
   `urn:oid:0.9.2342.19200300.100.1.3`: an attribute that also has a friendly name can be used
   under either. Test shows exactly what arrives.

   With the user ID left at `name_id`, the identity provider must send a **persistent** NameID
   (format `urn:oasis:names:tc:SAML:2.0:nameid-format:persistent`), the same for a person at
   every sign-in. A transient NameID changes each time: the person's first sign-in creates or
   connects their account, and the second no longer finds it under that user ID, so it is refused
   (`email_conflict`), or, with match by email on, rewrites the user ID at every sign-in. A
   response with no NameID at all is refused as `missing_attribute`. If the identity provider
   cannot send a persistent NameID, set the user ID to a stable attribute instead, like an
   employee number or `uid`.
5. **Save and test**, then switch the provider on.

The wiki checks the signature, the audience, the time window, that the answer replies to the
request this browser sent, and, when the answer names a destination, that it is this provider's
ACS URL exactly. Documents with a DTD are refused.

**Signing requests and encrypted assertions (optional).** Give the wiki a certificate and its
private key, in **This wiki's certificate** and **This wiki's private key**. The wiki then signs
its requests, can read encrypted assertions, and publishes the certificate in its metadata. The
wiki does not make the pair for you; this makes one valid for ten years:

```sh
openssl req -x509 -newkey rsa:3072 -sha256 -days 3650 -nodes \
  -keyout wiki-saml.key -out wiki-saml.crt -subj "/CN=wiki.example.org"
```

Paste `wiki-saml.crt` into the certificate field and `wiki-saml.key` into the private key field.
Set both or neither. The private key is a secret like a client secret: it is never shown again.
Replacing the pair means updating the identity provider too.

### CAS

1. On the wiki, **Add a provider › CAS** and give it a name.
2. **CAS server URL**: the address the server's `login` and `serviceValidate` pages are under,
   for example `https://cas.example.org/cas`.
3. **Protocol version**: 3.0 (the default) or 2.0. Version 3.0 is the one that defines attributes;
   with 2.0, whether a server sends any depends on the server.
4. **Certificate authority file**: leave it empty to check the server's certificate against the
   system's authorities. If the CAS server's certificate comes from a private authority, give the
   path, on the wiki's server, of that authority's PEM file. Certificates are always checked:
   there is no way to turn this off.
5. If the CAS server only accepts registered services, register the provider's callback URL,
   `https://wiki.example.org/?user/sign-in/<identifier>/callback&sso_callback=<identifier>`.
6. The user ID defaults to `user`, the login the CAS server validates. The others default to
   `mail` and `groups`, which must be released by the server as attributes.
7. **Save and test.**

If the CAS server does not answer, a sign-in can wait up to 10 seconds for it, twice (once to
validate the ticket, once to find out what went wrong), before the person sees the refusal.
Tickets that went through CAS proxies are refused.

### LDAP

LDAP needs the PHP `ldap` extension on the server. Without it, the provider cannot sign anyone in
and the **Health** page says so.

1. On the wiki, **Add a provider › LDAP directory** and give it a name: it is the name of its tab
   on the password form.
2. **Directory address**: `ldaps://ldap.example.org:636`, or `ldap://ldap.example.org:389` with
   **Encrypt with StartTLS** ticked. The server's certificate is always checked against the
   system's authorities, whatever `TLS_REQCERT` in `ldap.conf` or the `LDAPTLS_REQCERT`
   environment variable say: a self-signed certificate, or one from a private authority, is
   refused (`provider_unreachable`, with `certificate verify failed` in the error) until the
   server trusts that authority, through the system's store or `TLS_CACERT` in `ldap.conf`. An
   `ldap://` address without StartTLS sends every password across the network unencrypted, and is
   not checked at all: the edit screen warns you.
3. Tell the wiki how to find the person, in one of two ways:
   - a **Bind DN template**, the DN people sign in as, with `{login}` where the login goes:
     `uid={login},ou=people,dc=example,dc=org`. This is the simplest;
   - or, leaving the template empty, a search: the **Search base DN** where people are, the
     **User filter** (by default `(uid={login})`; for Active Directory, `(sAMAccountName={login})`),
     and if the directory does not allow anonymous searches, a **Search account DN** and its
     **password**.
4. Groups are read from the person's `memberOf` attribute by default: each group is then its full
   DN, like `cn=editors,ou=groups,dc=example,dc=org`, and that is what you write in the group
   mapping. If your directory has no `memberOf`, set a **Group search base DN**: the wiki then
   looks for the groups the **Group filter** finds there (by default `(member={dn})`; `{dn}` is the
   person's DN and `{login}` their login), and each group is its `cn`, like `editors`.
5. The usual attribute names are `uid`, `mail` and `memberOf`.
6. **Save and test**: Test asks for a login and password.

The login and password typed into the wiki's form are sent to the directory, never stored. An empty
password is refused before the directory is asked, because many directories would accept it as an
anonymous connection.

## Validated identity servers

The wiki was tried, by hand, against these identity servers, at the exact version given:
association, sign-in and the main failure cases, for every protocol the server speaks. The method
is described in `docs/adr/0031-sign-in-providers-replace-the-login-extensions.md`, section
_Testing against a real server_. A server missing from the list may work; it simply was not tried.

| Server | Version | Protocols |
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

## Settings the identity server may need

The wiki was tried against the [validated identity servers](#validated-identity-servers).
Several needed a setting on their own side before people could sign in, or before the account
got its email and groups. No setting of the wiki can replace most of them.
Test shows the result of each.

### OpenID Connect

What the wiki expects from any server:

- **The client secret in the request body** (`client_secret_post`). A client that may only send
  it in the `Authorization` header (`client_secret_basic`) is refused at the token endpoint, and
  every sign-in fails as `provider_rejected` (`invalid_client`).
- **One email address.** A server that sends a multivalued claim as one string, its values joined
  by a space, makes two emails into no address (refused as `missing_attribute`) and two groups into
  one group no mapping matches. JSON arrays are read: the wiki keeps the first email.
- **An ID token signed RS256, ES256 or EdDSA**: all three were seen and accepted. One signed
  HS256, with the client secret, is refused as `invalid_response`.
- **`email_verified: true`**, for **Connect to the existing account that has the same email** to
  link anyone.
- **Groups as a list of names.** A claim sent as an object, role names as its keys, gives no group,
  without an error.
- **The callback address's query kept.** The server must send people back to the callback URL
  with its own parameters added; the order does not matter. A server that drops the
  `user/sign-in/<identifier>/callback` key still reaches the callback through
  `sso_callback=<identifier>`. One that drops both sends them to the wiki's home page, or a
  missing page, signed out, and nothing is recorded.

Server by server:

- **Authelia**: give the client `token_endpoint_auth_method: client_secret_post`. Without it the
  client must use `client_secret_basic` and refuses the wiki.
- **Shibboleth IdP** (OIDC plugin): register the client with
  `token_endpoint_auth_method: client_secret_post`; set `oidc.asArray` on the claim rules of
  `email` and `groups`; import the OIDC claim rules into `shibboleth.AttributeRegistryResources`
  and give `sub` a rule; keep consent on the server
  (`idp.consent.StorageService = shibboleth.StorageService`), or userinfo sends `sub` alone and
  the wiki refuses as `missing_attribute`.
- **authentik**: drops the callback key and comes back through `sso_callback=<identifier>`, which
  works. Its default `email` mapping always says `email_verified: false`, its OAuth2 provider needs a signing
  key (without one its ID tokens are HS256, which the wiki refuses).
- **ZITADEL**: sends a project's roles as an object (`urn:zitadel:iam:org:project:roles`), which
  the group mapping cannot read. Add a flat `groups` claim, the list of role keys, with an Action
  (v1, flow "Complement Token", trigger "Pre Userinfo creation"), and map `groups`.
- **LemonLDAP::NG** 2.16: set the relying party's ID token signature algorithm to RS256; its
  default, HS512, is refused.
- **Apereo CAS** 7.3.8.3: set `cas.authn.oauth.session-replication.cookie.pin-to-session=false`,
  or CAS loses its own session and sends people back with no code (`invalid_response`).
- **Pocket ID**: `email_verified` follows the user's `emailVerified` field, off by default; a
  person without an email needs `requireUserEmail` off.

### SAML 2.0

What the wiki expects from any identity provider:

- **A signed assertion.** A response whose only signature is on the response itself is refused
  as `invalid_response` ("The Assertion of the Response is not signed and the SP requires it").
- **No encryption**, unless the provider is given **This wiki's certificate** and **This wiki's
  private key**: without them the wiki's metadata has no key to encrypt for.

Server by server:

- **Apereo CAS**: signs the response only by default, whatever the wiki's metadata asks. Set
  `signAssertions` to true on the wiki's SAML service.
- **Keycloak**: keep the assertion signed on the SAML client (`saml.assertion.signature`); the
  document signature alone is refused.
- **Shibboleth IdP**: encrypts assertions by default and fails for an SP with no key, which the
  wiki refuses as `provider_rejected` (`Responder`). Set `encryptAssertions` to false for the wiki,
  or give the wiki a certificate and key.
- **authentik**: with a signing key, its SAML provider must sign the assertion
  (`sign_assertion`).
- **ZITADEL**: sends no roles over SAML: an Action (v1) that adds a `groups` attribute brings them.
  An attribute value with `&` breaks its signatures, and that person's sign-ins are refused:
  do not release such a value.

### CAS

What the wiki expects from any CAS server:

- **Attributes in the validation answer**: at `/p3/serviceValidate` with version 3.0, at
  `/serviceValidate` with 2.0. Without them only the login arrives, and every sign-in is refused
  for lack of an email.
- **Attribute values escaped in the XML.** LemonLDAP::NG sends a value with `&` as is: the answer
  is unreadable, and that person's sign-ins are refused as `invalid_response` (`is not well-formed
  XML`). SimpleSAMLphp's `casserver` module drops such an attribute with a PHP warning: the value
  is silently missing, and the answer only breaks when PHP's `display_errors` is on. Do not release
  such a value over CAS.
- **Single-use tickets.** The wiki relies on the server to refuse a ticket validated before.

Server by server:

- **Apereo CAS**: with version 2.0, attributes are only sent with
  `cas.view.cas2.v3-forward-compatible=true`. Version 3.0 needs nothing.
- **Shibboleth IdP**: has no `/p3/serviceValidate`: choose version **2.0**, with the server URL
  `https://<idp>/idp/profile/cas`. Choose `simpleTicketService` in `cas-protocol.xml`: the default
  ticket service lets a ticket sign in again within its 15 seconds.
- **SimpleSAMLphp** (`casserver` module): has no `/p3/serviceValidate`: choose version **2.0**.
  With SimpleSAMLphp 2.4 (`casserver` 6.x), the module only answers at `login.php`,
  `serviceValidate.php`...: the web server must rewrite `/login`, `/serviceValidate` and the other
  protocol paths to them, or people land on a "not found" page. Turn PHP's `display_errors` off:
  otherwise the warning for an attribute value with `&` lands in the answer and breaks it.

### LDAP

What the wiki expects from any directory:

- **People may read their own entry.** The wiki reads the person's entry bound as that person,
  even when a search account found them: they must be allowed to read their own email,
  groups, the attributes of the account name template and the extra attributes. What they may not read stays empty, or, for the email, refuses the
  sign-in.
- **A bind DN template that makes a DN.** `{login}@ad.example.org` binds on Active Directory, but
  the wiki then reads the entry at that name and fails: use a search instead.
- **A user ID that comes with the entry, as text.** A binary attribute (Active Directory's
  `objectGUID`) is refused, saying so; an operational attribute (389 Directory Server's
  `nsUniqueId`) is not sent, since the wiki asks for the entry's ordinary attributes and
  `memberOf`. With a login as the user ID (`uid`, `sAMAccountName`), a person whose login is
  renamed gets a new account at their next sign-in, or is refused as `email_conflict`; the old
  account stays.

Server by server:

- **Active Directory** (Samba AD DC): find people by a search, with a search account and
  `(sAMAccountName={login})`. `memberOf` lists direct groups only: for nested groups, set a
  **Group search base DN** and the **Group filter** `(member:1.2.840.113556.1.4.1941:={dn})`;
  each group is then its cn. Use `sAMAccountName` or `userPrincipalName` as the user ID.
- **389 Directory Server**: the MemberOf plug-in is off in a new instance
  (`dsconf <instance> plugin memberof enable`, then restart), or use a group search base, which
  gives direct groups only. The default access rules do not let people read their own
  `departmentNumber`; a rule like this one, on `ou=people`, lets them:

  ```
  (targetattr="departmentNumber")(targetfilter="(objectClass=inetOrgPerson)")
  (version 3.0; acl "Enable self read of department";
  allow (read, search, compare)(userdn="ldap:///self");)
  ```

- **OpenLDAP**: a new slapd computes no `memberOf`. Give it the `dynlist` overlay with
  `olcDynListAttrSet: groupOfURLs memberURL member+memberOf@groupOfNames*` (nested groups
  included; it replaces the `memberof` overlay, deprecated), or set a **Group search base DN**,
  which gives direct groups only. `entryUUID` is operational, so it is not sent: take `uid`, or
  `uidNumber`, as the user ID.
- **FreeIPA**: the wiki's server must trust FreeIPA's own certificate authority. `ipaUniqueID`
  can be the user ID. Its default email domain gives `<login>@<domain>` to anyone created without
  an email, and the wiki links or creates accounts on it.
- **YunoHost**: its directory is an OpenLDAP that listens in clear on `ldap://localhost:389`,
  for the apps of the same machine. A wiki on that machine uses that address, so the password
  does not leave the machine; set the **Bind DN template** `uid={login},ou=users,dc=yunohost,dc=org`
  and map the groups as `memberOf` gives them, `cn=<group>,ou=groups,dc=yunohost,dc=org` (it also
  lists `all_users` and each person's own group). Or search under `ou=users,dc=yunohost,dc=org`
  with no search account: YunoHost lets anyone read every entry but passwords. Take `uidNumber` or
  `uid` as the user ID (`entryUUID` is not sent). A wiki on another machine needs slapd to listen
  there and the port open (`yunohost firewall open 389`), and should use `ldaps://` (port 636) or
  StartTLS: first replace the certificate slapd serves, in `/etc/yunohost/certs/yunohost.org/`,
  which as installed no one can verify.
- **authentik** (LDAP outpost): `uid` is a hash, `cn` the login: search with `(cn={login})` under
  `ou=users,<suffix>` (the whole suffix also finds the person's own group). The search account
  needs `search_full_directory`.
- **Kanidm** 1.12.0: a bound person reads only what anonymous may read, so the wiki gets no email.
  Its LDAP cannot be used with the wiki; its OpenID Connect can.

## Testing a provider

**Test** (in the list) and **Save and test** (on the edit screen) run the real sign-in against the
provider, in your own browser, and show you what came back instead of signing anyone in:

- the steps reached: sent to the provider, the provider answered, attributes read, account
  matched, groups;
- every attribute the provider returned, and what the wiki used it as;
- what the account would hold, the wiki groups it would join and leave, and the provider groups no
  row maps;
- the raw error, if there was one;
- and a sentence saying what a real sign-in would have done: create an account, connect an
  existing one, sign in to a linked one, or refuse it and why.

A banner says that nobody was signed in and nothing was changed. A test writes nothing to the
Journal.

For a button provider (OpenID Connect, SAML, CAS), you are sent to the provider and you sign in
there **as the person you want to test**: you can use a test account of the company, or your own.
For a password provider (LDAP), the wiki asks for a test login and password.

Tests are for administrators only. A test can only be finished by the administrator who started
it, still signed in: someone else coming back with it is refused. Test works on a provider that is
switched off, which is the way to try a provider before anyone can use it. Local accounts are not
tested from here.

A SAML test recognises you by your wiki session: the `yw-auth` cookie must come back with the
identity provider's answer, which is a POST from the identity provider's site. On an `https://`
wiki this works with the wiki's `cookies_samesite` setting at its default, `None`. Set to `Lax`
or `Strict`, the browser leaves `yw-auth` out of that POST and the test is refused as not finished
by the administrator who started it. Real sign-ins are not affected: their own short-lived cookie
is always sent back.

Test is also the quickest way to see what a working sign-in returns, before you write a group
mapping or an extra attribute.

## When someone cannot sign in

The person sees a page titled "We couldn't sign you in", saying in plain words what happened and
naming the provider. It tells them to contact you with the **time** and the **email or login**
shown on the page. A wrong password on the password form is shown on the form itself.

On your side, open **Failed sign-ins** from the sign-in screen, or the Journal (`?admin/logs`),
and type the email or login in the **On** filter. You find two kinds of entries:

- **one entry per refused attempt**, kept 365 days: who, which provider, and the reason. Every
  refusal and every wrong password is there;
- **a detailed entry per provider, reason and day**, kept 14 days, for refusals after the
  provider answered and for technical failures (not for wrong passwords). Its **Why** column shows
  the steps reached, why it failed in plain words, a link to the provider's settings, every
  attribute the provider returned and what it was used as, the group mapping result and the raw
  error. Within a day, a new refusal for the same provider and reason replaces the previous
  details: it shows the latest attempt. To see one person's attributes, ask them to try again, or
  use Test.

The reasons, and what to do:

| Reason                    | What it means                                                                                | What to do                                                                                     |
| ------------------------- | -------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `provider_unreachable`    | The wiki could not reach the provider: network, DNS, TLS certificate, timeout, or a proxy in front of it that answered 502, 503 or 504 (the provider is down or overloaded). | Check the server can reach the provider and trusts its certificate, then Test.                  |
| `provider_rejected`       | The provider returned an error, or refused this person.                                       | Read the raw error; check the client settings and the person's access at the provider.          |
| `invalid_response`        | The answer failed a check: state, signature, audience, an expired assertion, or the sign-in took more than 10 minutes or started in another browser. | Ask the person to start again, in one browser. If it persists, check the keys and the server clock. |
| `configuration_invalid`   | The provider's settings, or a PHP module they need, cannot be used.                           | Correct the settings, or install the module, then Test.                                         |
| `missing_attribute`       | The provider did not send the user ID or the email under the configured name, or sent it in a form the wiki cannot use: binary (Active Directory's `objectGUID`), not text (an object), or, as email, something that is not one address (several joined by spaces, commas or semicolons). | Test to see what it sends; correct the attribute names, or what the provider releases.          |
| `email_conflict`          | An account already has this email, and match by email is off, or the provider does not vouch for the email. | Turn on match by email if the provider checks emails, or correct the existing account's email. |
| `linked_elsewhere`        | The account with this email belongs to another provider.                                      | They sign in with the provider named in the error. Or delete the account: it is created again.  |
| `no_account`              | No account has this email, and this provider does not create accounts.                        | Create the account by hand, or turn on account creation.                                        |
| `provider_disabled`       | The provider is switched off, or its type is no longer installed.                             | Switch it on, or activate the extension that supplies its type.                                 |
| `account_creation_failed` | The account could not be written (the wiki is in hibernation, for example).                   | Read the raw error, check the wiki accepts changes, and ask the person to try again.            |
| `wrong_credentials`       | The password provider rejected the login or the password.                                     | Nothing, unless this login never works.                                                          |
| `internal_error`          | Something unexpected happened in the wiki.                                                    | Read the raw error, here and in the server's log, and report it if it persists.                 |

**Successful sign-ins are not recorded anywhere.** A sign-in that succeeds but hits a problem (the
provider's new email is already used by another account, a group could not be updated) leaves a
warning in the Journal, because something needs your attention.

### What is recorded, and where

The Journal writes every entry to the **server's log** too (the web server's or PHP's error
output), with all its details. So **the attributes a provider returned for a refused sign-in reach
the server's log**: names, emails, groups and whatever else the provider sends. These are personal
data: keep that log as you keep the rest of the server's personal data, and know who can read it.

Passwords, tokens, client secrets, SAML assertions and cookies are never written, in the Journal
or in the log. Attributes are cut down to a reasonable size before they are written.

## Health checks

The **Health** page (`?admin/health`) reports what keeps sign-in from working:

| Check                      | Reports                                                                                                     |
| -------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `sign-in-no-provider`      | No provider is switched on and usable: nobody can sign in.                                                  |
| `sign-in-config`           | Entries of `sso_providers` the wiki ignores (no type, a type that is not installed, a type declared twice). |
| `sign-in-orphan-accounts`  | Accounts linked to a provider that is no longer in the configuration: they cannot sign in.                  |
| `sign-in-ldap-module`      | A switched-on LDAP provider, and the PHP `ldap` extension is not loaded.                                    |
| `sign-in-plain-http`       | A warning naming every switched-on provider reached at an `http://` address, and which setting it is.       |

Plain `http://` addresses are accepted, because test and development servers often have nothing
else, but not silently: the provider's edit screen shows a warning for each address that starts
with `http://`, and this check names them on the Health page. What crosses an unencrypted
connection, the identity of whoever signs in included, can be read and changed on the way. Use
`https://` everywhere else.

## Changing server

When the company moves its identity server, **edit the existing provider** rather than adding a
new one: the accounts stay linked to it, with their content and groups.

If the new server gives people the same user IDs, changing the connection settings is enough. If
it gives them new ones, turn on **Connect to the existing account that has the same email** for
the move: at each person's first sign-in on the new server, the wiki finds their account by email
and replaces the stored user ID with the new one. Turn it off again once everyone has signed in,
unless the server checks emails.

Accounts cannot be moved from one provider to another, or back to local.

## Getting back in

If a provider's server is down and local accounts are switched off, nobody can sign in, you
included. Anyone with a shell on the server can switch local back on, in the wiki's folder:

1. Switch local accounts on:

   ```sh
   ./yeswicli sign-in:enable local
   ```

   Editing `sso_providers` in `yeswiki.config.php` does not do it: once the sign-in screen or the
   upgrade has saved the providers, the wiki reads them from its database (see
   [Where the providers are kept](#where-the-providers-are-kept)).

2. Your administrator account is probably linked to the provider that is down, and a linked account
   has no password. Create a new local administrator from the command line, in the wiki's folder:

   ```sh
   ./yeswicli user:create RescueAdmin rescue-admin@example.org --ask-password --group admins
   ```

   `--ask-password` asks for the password without showing it. To add an existing account to a
   group, use `./yeswicli user:add-to-group <name> admins`.

3. Sign in with that account. Once the provider works again, switch local off if you want
   (`./yeswicli sign-in:disable local`, or the screen), and delete the rescue account.

## Where the providers are kept

Everything the sign-in screen edits is one setting, `sso_providers`. Like every setting an
administration screen saves, it is kept in the wiki's database, never in `yeswiki.config.php`, so
every server of a replicated wiki sees a change at its next request. The upgrade saves the
providers it converts there too.

A deployment can also write `sso_providers` in `yeswiki.config.php`, with the shape below. The file
only counts until the setting is saved: from then on, the value in the database replaces the whole
section of the file, and editing the file changes nothing. To change the providers of a running
wiki, use the screen, or `./yeswicli sign-in:enable` and `sign-in:disable` for switching one on or
off.

The example below shows every option, with one provider of each type. The options every type
shares (everything outside `connection`) are described on the OpenID Connect provider and are
written the same way for every type. A commented-out option (`// 'key' => ...`) is optional, or
another way of doing what the active line does.

```php
'sso_providers' => [
    // The wiki's own accounts. The `local` type is only accepted under the `local` key, and has
    // no `connection`. With no `sso_providers` section, it exists and is switched on.
    'local' => [
        'type' => 'local',
        // The name of its tab in the password form. By default "Wiki account", translated into
        // the page's language.
        // 'label' => 'Wiki account',
        'enabled' => true,
    ],

    // OpenID Connect or OAuth2. The key, here `acme-sso`, is the provider's identifier.
    'acme-sso' => [
        // `local`, `oidc`, `saml`, `cas`, `ldap`, or a type an extension adds.
        'type' => 'oidc',
        // The name on the button ("Continue with Acme SSO"). By default, the identifier.
        'label' => 'Acme SSO',
        // `true` by default. Switched off, the provider can still be tested.
        'enabled' => true,
        'connection' => [
            // The discovery document, where the wiki reads every other address.
            'discovery_url' => 'https://login.acme.example/.well-known/openid-configuration',
            // Without discovery (plain OAuth2), these three addresses are required. Next to a
            // discovery document, each one wins over what the document says.
            // 'authorize_url' => 'https://login.acme.example/authorize',
            // 'token_url' => 'https://login.acme.example/token',
            // 'userinfo_url' => 'https://login.acme.example/userinfo',
            // Without discovery, these two have the ID token checked (signature, issuer,
            // audience, expiry, nonce); without them, it is ignored.
            // 'issuer' => 'https://login.acme.example',
            // 'jwks_uri' => 'https://login.acme.example/jwks',
            // Required.
            'client_id' => 'yeswiki',
            // Required. A secret: the screen never shows it again once saved.
            'client_secret' => '...',
            // `openid email profile` by default. Separated by spaces or commas, or as a PHP
            // list: ['openid', 'email', 'profile', 'groups'].
            'scopes' => 'openid email profile',
            // No screen: what separates the scopes in the request sent to the provider, a space
            // by default. Only the upgrade from `loginsso` writes it.
            // 'scope_separator' => ',',
        ],
        // The attribute names at the provider. A missing key takes the type's usual name: `sub`,
        // `email` and `groups` for OpenID Connect.
        'attributes' => [
            // The person's identifier: stable, never given to anyone else.
            'id' => 'sub',
            'email' => 'email',
            // The groups, which `group_mapping` reads.
            'groups' => 'groups',
        ],
        // `true` by default. Create an account the first time someone signs in; at `false`, only
        // people who already have an account can sign in.
        'create_accounts' => true,
        // `false` by default. Connect to the existing account that has the same email: only if
        // the provider checks the emails it sends.
        'match_by_email' => false,
        // The name of the accounts created, each `#[attribute]` replaced by its first value.
        // Missing, the name comes from the start of the email, then from the identifier.
        'account_name_template' => '#[given_name] #[family_name]',
        // `true` by default. Make the name a WikiName (automatic capitals), or, at `false`, keep
        // it as the provider sent it.
        'account_name_pascalcase' => true,
        // Group mapping, one row per pair: the provider's group (compared exactly, case
        // included), then the wiki group. Wiki groups that no row names are never touched.
        'group_mapping' => [
            ['provider' => 'wiki-editors', 'wiki' => 'Editors'],
            ['provider' => 'staff', 'wiki' => 'Editors'],
        ],
        // Extra attributes: field of the user form => the provider's attribute that fills it.
        // The person can no longer edit these fields.
        'extra_fields' => [
            'service' => 'department',
        ],
    ],

    // SAML 2.0. The identity provider's side is given in one of these three ways.
    'university' => [
        'type' => 'saml',
        'label' => 'University',
        'enabled' => true,
        'connection' => [
            // 1. The address of its metadata.
            'metadata_url' => 'https://idp.example.org/idp/shibboleth',
            // 2. Or its metadata, in full. Given with `metadata_url`, it wins.
            // 'metadata_xml' => '<md:EntityDescriptor ...>...</md:EntityDescriptor>',
            // 3. Or, without metadata, these three values. Given with metadata, each one wins
            //    over what the metadata says.
            // 'idp_entity_id' => 'https://idp.example.org/idp/shibboleth',
            // 'idp_sso_url' => 'https://idp.example.org/idp/profile/SAML2/Redirect/SSO',
            // 'idp_certificate' => "-----BEGIN CERTIFICATE-----\nMIID...\n-----END CERTIFICATE-----",
            // Optional, both or neither: the wiki's certificate and private key, to sign its
            // requests and read encrypted assertions. The private key is a secret.
            // 'sp_certificate' => "-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----",
            // 'sp_private_key' => "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----",
        ],
        // SAML's usual names. `name_id` is the assertion's NameID, which must then be persistent.
        'attributes' => ['id' => 'name_id', 'email' => 'mail', 'groups' => 'groups'],
    ],

    // CAS 2.0 or 3.0.
    'university-cas' => [
        'type' => 'cas',
        'label' => 'University CAS',
        'enabled' => true,
        'connection' => [
            // Required: the address under which the `login` and `serviceValidate` pages are.
            'server_url' => 'https://cas.example.org/cas',
            // `3.0` (the default) or `2.0`.
            'version' => '3.0',
            // The path, on the wiki's server, of the PEM file of the authority that signed the CAS
            // server's certificate. Missing, the system's authorities.
            // 'ca_cert' => '/etc/ssl/certs/private-authority.pem',
        ],
        // CAS's usual names. `user` is the identifier the server validates.
        'attributes' => ['id' => 'user', 'email' => 'mail', 'groups' => 'groups'],
    ],

    // An LDAP directory, asked with the login and password typed into the wiki.
    'directory' => [
        'type' => 'ldap',
        // The name of its tab in the password form.
        'label' => 'Directory',
        'enabled' => true,
        'connection' => [
            // Required: `ldaps://host:port`, or `ldap://host:port`.
            'uri' => 'ldaps://ldap.example.org:636',
            // `false` by default. At `true` with an `ldap://` address, to encrypt with StartTLS.
            'start_tls' => false,
            // The person is found in one of these two ways:
            // 1. the DN they sign in as, with `{login}` in place of their login;
            'bind_dn_template' => 'uid={login},ou=people,dc=example,dc=org',
            // 2. or, without a template, a search under `base_dn` with `user_filter` (by default
            //    `(uid={login})`; Active Directory: `(sAMAccountName={login})`), made as a search
            //    account if the directory refuses anonymous searches. The search account's
            //    password is a secret, required with `search_dn`.
            // 'base_dn' => 'ou=people,dc=example,dc=org',
            // 'user_filter' => '(uid={login})',
            // 'search_dn' => 'cn=wiki,ou=services,dc=example,dc=org',
            // 'search_password' => '...',
            // The groups: without `group_base`, the person's groups attribute (`memberOf`), each
            // group being its full DN. With `group_base`, the groups `group_filter` finds there,
            // each being its `cn`; in the filter, `{dn}` is the person's DN and `{login}` their
            // login. `(member={dn})` by default.
            // 'group_base' => 'ou=groups,dc=example,dc=org',
            // 'group_filter' => '(member={dn})',
        ],
        // LDAP's usual names.
        'attributes' => ['id' => 'uid', 'email' => 'mail', 'groups' => 'memberOf'],
        'match_by_email' => true,
        // No screen: only the upgrade writes it, on the LDAP and CAS providers it converts. Until
        // that date, a first sign-in also links the account named after the person's login;
        // `UPGRADE.md` says what else it allows.
        // 'match_by_name_until' => '2027-11-16',
    ],
],
```

- The **order** of the entries is the order of the sign-in page. The screen adds a provider at the
  end and does not change the order.
- The **key** is the provider's identifier. It is in the callback URL registered at the provider
  and the accounts are linked to it: do not change it.
- An entry with no `type`, of a type that is not installed, or of type `local` under another key
  than `local`, is ignored; the `sign-in-config` health check names it.
- The `true` or `false` options also accept `'1'`, `'yes'`, `'on'` and their opposites: any value
  that is not a yes is `false`, so a mistyped value never switches on what it meant to keep off. A
  missing option takes its default.
- An extension's type has its own settings under `connection`: the edit screen shows them, and the
  extension documents them.
- The secrets in this section (`client_secret`, `sp_private_key`, `search_password`) are read when
  needed and never copied into the wiki's compiled cache. They are stored in the wiki's database,
  with the other settings: protect its backups as you protect the database itself.

**Plain http and SAML.** On `https`, the wiki remembers a sign-in in progress in a cookie,
`yw-sign-in`, which lasts 10 minutes and is sent with `SameSite=None; Secure`, so that SAML's
answer, a POST from the identity provider's site, carries it. On plain `http`, browsers would
refuse such a cookie, so it is sent `SameSite=Lax`, and SAML only works there if the wiki and the
identity provider are on the same site (for example `wiki.example.org` and `idp.example.org`).
The other types are not affected.

## For developers

Adding a provider type from an extension is described in the
[development documentation](dev.md#add-a-sign-in-provider). The decision behind this design is
`docs/adr/0031-sign-in-providers-replace-the-login-extensions.md`.
