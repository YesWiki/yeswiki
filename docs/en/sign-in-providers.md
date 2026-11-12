# Sign-in providers

A **provider** is one way of signing in to the wiki. The wiki's own accounts, with a name and a
password kept by the wiki, are one provider, called **local**. The others let people sign in with
an account they already have elsewhere, usually their company's:

| Type                    | What it talks to                                                              | How people sign in                                                 |
| ----------------------- | ----------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| OpenID Connect / OAuth2 | Keycloak, LemonLDAP::NG, Authentik, Microsoft Entra, Google, Nextcloud...     | a button that sends them to the provider's page, then back         |
| SAML 2.0                | an identity provider (IdP): ADFS, Shibboleth, LemonLDAP::NG, Keycloak...      | a button, the same way                                             |
| CAS                     | a CAS server (2.0 or 3.0)                                                     | a button, the same way                                             |
| LDAP                    | a directory: OpenLDAP, Active Directory...                                    | their directory login and password, typed into the wiki's own form |
| Local                   | nothing: the wiki keeps the accounts                                          | a name or email and a password, typed into the wiki's form         |

Any number of providers can be switched on at once, including several of one type (two SAML
providers, for example). An extension can add more types; they appear in the same lists and work
the same way.

> This replaces the Doryphore extensions `loginsso`, `loginldap` and `logincas`. A wiki upgraded
> from Doryphore arrives with its settings already converted: see `UPGRADE.md`, section _Sign-in
> providers replace the login extensions_.
>
> **With `loginsso`, one step comes before the upgrade.** The old callback
> `?api/auth_sso/callback` is gone: each converted provider sends people back to
> `?user/sign-in/<identifier>/callback`, the identifier coming from the button label (`Acme SSO`
> gives `acme-sso`). Register that address at the OAuth2 / OpenID Connect server **before** the
> upgrade, next to the old one, or nobody can sign in through it until it is. How the identifier
> is made is in `UPGRADE.md`, under _Before upgrading a wiki that uses `loginsso`_.

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
A new provider is added at the end of the list, and the screen cannot change the order of the
sign-in page yet (see [Where the providers are kept](#where-the-providers-are-kept)).

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
   on (it is by default). Its name is made from the first and last name, or the start of the email,
   or the user ID.

If none of this works, the sign-in is refused, and the person sees why (see
[When someone cannot sign in](#when-someone-cannot-sign-in)).

At every sign-in, the account's first name, last name, email and any
[extra attributes](#extra-attributes) are refreshed from the provider, and its
[groups](#group-mapping) are updated. Nothing is written when nothing changed.

### What a linked account can no longer do

- Its first name, last name, email, mapped extra fields and password **cannot be changed on the
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
`user/sign-in/<identifier>/callback`, for example
`https://wiki.example.org/?user/sign-in/acme-sso/callback`, so you can give it to the provider
before you save if you know the identifier.

**Attributes.** The names the provider uses for what the wiki needs: **User ID**, **Email**,
**First name**, **Last name** and **Groups**. Leave a field blank to use the usual name for this
type, shown in grey. The user ID must never change for a person and never be given to someone
else: prefer a technical identifier (`sub`, an employee number, a directory `uid`) to an email.

The wiki refuses a sign-in without a user ID, and without an email, and says which attribute it
was looking for. Missing names stay empty.

**Accounts.**

- **Create an account the first time someone signs in.** On by default. Off, only people who
  already have an account on the wiki can sign in with this provider.
- **Connect to the existing account that has the same email.** Off by default. **Only turn it on
  if the provider checks the emails it sends**: otherwise anyone who can choose their email at the
  provider can take over the wiki account that has it. An OpenID Connect provider that sends
  `email_verified: false` is never matched by email, whatever this setting says. This is also the
  setting to turn on when you move the provider to a new server that gives people new user IDs (see
  [Changing server](#changing-server)).

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
Those fields become read-only for the person, like the name and email.

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
   `https://wiki.example.org/?user/sign-in/<identifier>/callback`. Note its **client ID** and
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
   identity comes from the userinfo endpoint only, as with Doryphore's `loginsso`. Endpoints
   written here win over the discovered ones.
4. Fill in the **Client ID** and **Client secret**. **Scopes** defaults to
   `openid email profile`; add the scope that releases groups if your provider needs one.
5. The usual attribute names are `sub`, `email`, `given_name`, `family_name` and `groups`.
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
     `https://wiki.example.org/?user/sign-in/<identifier>/callback`, with the HTTP-POST binding.
3. At the identity provider, register the wiki with these two values, and have it **sign its
   assertions**: the wiki refuses unsigned ones. Have it release the attributes you need.
4. Set the attribute names. The user ID defaults to `name_id` (the NameID of the assertion); the
   others to `mail`, `givenName`, `sn` and `groups`. Many identity providers send names like
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
   `https://wiki.example.org/?user/sign-in/<identifier>/callback`.
6. The user ID defaults to `user`, the login the CAS server validates. The others default to
   `mail`, `givenName`, `sn` and `groups`, which must be released by the server as attributes.
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
5. The usual attribute names are `uid`, `mail`, `givenName`, `sn` and `memberOf`.
6. **Save and test**: Test asks for a login and password.

The login and password typed into the wiki's form are sent to the directory, never stored. An empty
password is refused before the directory is asked, because many directories would accept it as an
anonymous connection.

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
| `provider_unreachable`    | The wiki could not reach the provider: network, DNS, TLS certificate, or timeout.             | Check the server can reach the provider and trusts its certificate, then Test.                  |
| `provider_rejected`       | The provider returned an error, or refused this person.                                       | Read the raw error; check the client settings and the person's access at the provider.          |
| `invalid_response`        | The answer failed a check: state, signature, audience, an expired assertion, or the sign-in took more than 10 minutes or started in another browser. | Ask the person to start again, in one browser. If it persists, check the keys and the server clock. |
| `configuration_invalid`   | The provider's settings, or a PHP module they need, cannot be used.                           | Correct the settings, or install the module, then Test.                                         |
| `missing_attribute`       | The provider did not send the user ID or the email under the configured name.                 | Test to see what it sends; correct the attribute names, or what the provider releases.          |
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
every server of a replicated wiki sees a change at its next request. The upgrade from Doryphore
saves the converted providers there too.

A deployment can also write `sso_providers` in `yeswiki.config.php`, with the shape below. The file
only counts until the setting is saved: from then on, the value in the database replaces the whole
section of the file, and editing the file changes nothing. To change the providers of a running
wiki, use the screen, or `./yeswicli sign-in:enable` and `sign-in:disable` for switching one on or
off.

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

- The **order** of the entries is the order of the sign-in page. The screen adds a provider at the
  end and cannot change the order yet.
- The **key** is the provider's identifier. It is in the callback URL registered at the provider
  and the accounts are linked to it: do not change it.
- `type` is the provider's type; `connection` holds that type's settings, under the names used
  above (`discovery_url`, `metadata_xml`, `server_url`, `ca_cert`, `uri`, `bind_dn_template`...).
- `extra_fields` maps a field of the user form to the attribute that fills it.
- `account_name_template` (no screen) names new accounts from attributes, like
  `'#[given_name] #[family_name]'`.
- `match_by_name_until` (no screen) only exists on providers converted from Doryphore's LDAP and
  CAS extensions: until that date, a first sign-in also finds an unlinked account named after the
  person's user ID, as Doryphore did, and an existing account may sign in without an email, as
  Doryphore allowed. Both end on that date.
- The secrets in this section are read when needed and never copied into the wiki's compiled
  cache. They are stored in the wiki's database, with the other settings: protect its backups as
  you protect the database itself.

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
