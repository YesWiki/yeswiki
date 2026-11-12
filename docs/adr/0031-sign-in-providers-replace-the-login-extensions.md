# Sign-in providers replace the login extensions

Wikis run as a company's internal wiki had to sign people in through the company's identity
server, and three Doryphore extensions did it: `loginsso` (OAuth2 and OpenID Connect),
`loginldap` and `logincas`. Their flows mostly worked. What did not was everything around them:
only one could be active at a time, each had its own configuration and its own features, SAML
did not exist, and when a sign-in was refused nobody could see why. The employee got an
unhelpful message, the admin got nothing, and the only way to find out what the identity server
had sent was a developer with a debugger. Ectoplasme also took away two things they leaned on:
core keeps no PHP session any more (replicas sign people in from a signed `yw-auth` cookie), and
under FrankenPHP's worker mode request state may never be global (ADR-0024).

We decided that **sign-in is one system of Providers in core, configured in one section of the
configuration file, `sso_providers`, and that local accounts are a Provider like the others.** A
Provider has an identifier (the array key, immutable once created), a type (`local`, `oidc`,
`saml`, `cas`, `ldap`, or one an extension registers), settings and switches. Any number can be
on at once, two of one type included; with no section, local is on. The three extensions are
retired and a migration converts their settings. Four decisions shape the rest, and each was
taken against a cheaper alternative.

**Plan, then apply.** Once a Provider has said who someone is, `SignInService::plan()` decides
everything (which account, whether it is created, linked or refused, which groups it joins and
leaves, what its profile becomes) and changes nothing. `apply()` carries a plan out, and writes
only what changed. The admin's Test runs the real protocol and then calls `plan()` alone, so what
a test shows is by construction what a real sign-in would do: there is no second copy of the
rules for a test to drift from.

**The Account link lives in the account's own page.** Two keys of the user page's body,
`sso_provider` and `sso_subject`, tie an account to the one Provider it belongs to and to that
Provider's stable user ID. An account without them is local. They sit outside the User form and
join the keys no profile edit may set (`UserManager::updatableKeys()`, beside `activation_status`),
and only `AccountLinks` writes them, with one exception: the upgrade from `loginsso`
(`LoginExtensionsBecomeProviders`) writes them once, turning each account's `loginsso_id` into a
link while it converts the configuration.

**Failed sign-ins go to the Journal's two existing channels** (ADR-0025), with no table, setting
or purge task of their own. Every refusal and every wrong password is a `login.failed` audit row
(who, which Provider, the reason code, no attributes), kept 365 days, one row per attempt. A
refusal after the Provider has answered, or a technical failure, is also a diagnostic,
`login.refused.<reason>.<provider>`, holding the last step reached, the attributes returned and
what each was used as, the group mapping result and the raw error after scrubbing; it collapses
per Provider, reason and day, and is kept 14 days. Successful sign-ins and test runs write nothing.

**phpCAS is used, and confined.** It is the protocol's official PHP client, maintained, and
supports PHP 8.4. It was written for one request per process and a PHP session, so
`CasProvider` builds a `CAS_Client` for each call and never touches the static `phpCAS`
facade, opens a session of its own for the call (sending no cookie) and destroys it when the call
ends, makes phpCAS throw instead of `exit`, never lets it redirect by itself, throws away what it
prints, hands it the ticket from the request rather than letting it read `$_GET`, and always
gives it a CA bundle, the configured one or the system's.

## Considered Options

- **Keep the three extensions and make them better** — rejected. The blindness was the shared
  problem, and fixing it three times would have left three configurations, three feature sets and
  still one extension at a time. A wiki needing SAML would have needed a fourth.
- **A table of account links** — rejected. A link is a fact about one account and nothing else,
  and the user page already holds that account's facts, versioned (ADR-0003). A table would have
  brought a unique index, which is the one thing the page body cannot give: two first sign-ins of
  one person at the same instant could create two accounts. A `Locks` lock named after the
  Provider and the user ID, held while an account is linked or created, gives the same guarantee
  without the table. Lookups use `jsonExtract` on both keys, as `resolveTagFromEmail()` does.
- **A table of sign-in attempts** — rejected. It would have needed its own retention, purge task,
  screen and backup rule, all of which the Journal already has. One row per person in the
  diagnostic channel was rejected too: during a misconfiguration every employee is refused for the
  same reason, and one row each would use up the channel's 500 rows a day and hide real errors.
  The audit rows still name everyone refused; to see one person's attributes, they try again or
  the admin uses Test.
- **Recording successful sign-ins** — rejected by the project lead. It is a log of who was
  present when, kept for nothing the wiki does.
- **A test mode with its own logic** — rejected for plan-then-apply: a test that reimplements the
  decision can say yes where the real sign-in says no.
- **`ecphp/cas-lib`, which has no global state** — rejected. Its last release was in October 2024
  and it brings several new dependencies. A maintained library, confined, is a smaller risk than
  a clean one nobody releases.
- **Writing a CAS client** — rejected. The protocol is small, but its validation response,
  proxies and error codes are where hand-written clients go wrong, and nobody would maintain it.

## Consequences

- `/admin/sign-in` lists the Providers, edits, tests and switches them, and writes the file
  through `ConfigurationService::write`, like the other admin screens. It refuses to switch off,
  or delete, the last Provider still on, and a Provider with accounts attached can be switched off
  but not deleted. On replicas that do not share the file, the file is the deployment's to manage.
- An account belongs to exactly one place. Linking a local account clears its password; a linked
  account's names, email, mapped extra fields and password are read-only for everyone, admins
  included, and lost password and password change refuse it. Accounts on a Provider switched off
  lose their sign-in at the next request.
- A Provider sign-in lasts the remembered lifetime, 90 days renewed at half-life, as the
  extensions did. Ending someone's access means deleting the account or switching the Provider
  off, and the admin guide says so.
- The Journal writes every event to stderr with its whole context, so a refusal's attributes
  reach the server's log. They are personal data, and the admin guide says so. Secrets, tokens,
  passwords, assertions and cookies are written nowhere; attributes are size-limited.
- The round trip to a redirect Provider keeps its state in a signed cookie, `yw-sign-in`, living
  10 minutes and deleted when used, so any replica can finish a sign-in another one started. It is
  sent `SameSite=None; Secure` on https whatever `cookies_samesite` says, because SAML comes back
  with a cross-site POST; on plain http it falls back to `Lax`, and SAML then needs the wiki and
  the identity server to be same-site.
- A Provider type is one service implementing `ProviderType`. Core's five types register exactly
  the way an extension's does, which keeps the contract honest; an extension adds a protocol and
  cannot change the account rules.
- `ext-ldap` becomes a suggested PHP extension, and health checks report what keeps sign-in from
  working: no Provider on, broken entries, accounts on a Provider that is gone, LDAP without its
  module, and Providers reached over plain http.
- phpCAS's confinement is a list of rules in `CasProvider`, and whoever upgrades phpCAS has to
  check them again: the failure classification reads phpCAS's English messages.
