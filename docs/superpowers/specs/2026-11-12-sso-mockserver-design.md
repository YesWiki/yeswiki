# Sign-in tests against MockServer instead of LemonLDAP::NG

Status: design, approved in conversation. Branch: `feat/sso`.

Decisions are marked **(decided)** when the project lead made them and **(author's call)** when the author of this document made them.

## 1. Intent

The `feat/sso` branch tests its OIDC, SAML and CAS providers end to end against a real LemonLDAP::NG portal (`yeswiki-sso` in `docker/docker-compose.yml` and in `.github/workflows/e2e.yml`). That makes the happy paths easy to test, but:

1. only one server is tested, and a regression found on another server (Keycloak, ADFS, Shibboleth...) cannot easily be reproduced as a test;
2. the portal is a heavy development dependency for a subset of the features;
3. error cases (a tampered or expired token, a provider that refuses, an endpoint that fails) are hard to produce.

This work replaces the portal with [MockServer](https://www.mock-server.com/) (`mockserver/mockserver`), adapts the existing sign-in specs to it, and adds a spec of error cases.

Success looks like this: every existing sign-in spec passes against MockServer, LemonLDAP::NG is gone from the repository and from CI, and a new error case — or a regression seen on a given server — is one row in a table.

## 2. Scope

### In scope (decided)

- MockServer replaces LemonLDAP::NG for OIDC, SAML and CAS, in the development stack and in CI.
- The existing specs `sign-in-providers`, `sign-in-admin` and `sign-in-upgrade` are ported to it.
- A new spec covers the error cases listed in section 7.

### Out of scope

- **LDAP stays on OpenLDAP (decided).** MockServer only speaks HTTP for the purposes of this work. Its binary (raw TCP) expectations were evaluated and rejected: they match whole messages byte for byte, are under-documented, were reworked in 9.0.0 (October 2026), have no documented TLS, and would test a recording of libldap's bytes rather than a directory. `yeswiki-ldap` and `docker/openldap/seed.ldif` are unchanged; LDAP error cases stay in `LdapProviderTest`.
- The PHPUnit tests of the providers, which already use canned answers, are unchanged.
- The product code is unchanged. If a case of section 7 reveals a behaviour that differs from the expected reason, it is reported as a bug, not absorbed into the test.
- The historical documents `docs/superpowers/{specs,plans}/2026-11-10-sso*` are not rewritten. The mentions of LemonLDAP::NG in the user documentation (`docs/en/sign-in-providers.md`, `docs/fr/connexion-sso.md`, `UPGRADE.md`) name compatible real servers, not the test tooling, and stay.

## 3. Evidence: what MockServer 9.2.0 does (spike, 2026-11-12)

A throwaway spike ran `mockserver/mockserver:9.2.0` on the development stack's network and drove the real `OidcProvider` and `SamlProvider` from the `yeswiki-sso-app` container, with the branch's test keys. Nothing of it is kept. Findings:

| What | Result |
| --- | --- |
| OIDC (`PUT /mockserver/oidc`) | `/authorize` answers 302 to `redirect_uri` with `code` and the `state` it was given. The ID token is signed with the key we supply (`privateKeyPem` + `certificatePem`, stable `keyId`) and carries the `nonce` of the authorize request. Discovery, PKCE S256 (really checked: a wrong verifier gets `invalid_grant`), userinfo with multi-valued claims (`additionalClaims`). `OidcProvider::complete()` returned the identity. |
| OIDC errors | `tamperedSignature`, `issueExpiredToken`, `wrongIssuer` and a wrong secret with `enforceClientAuthentication` (`invalid_client`) each give the expected `ProviderFailure`. |
| SAML (`PUT /mockserver/saml`) | The HTTP-Redirect AuthnRequest is accepted, through `metadata_url` as through `idp_*`. The **Assertion** is signed, `Destination` is the ACS, `InResponseTo` is the AuthnRequest ID, RelayState is echoed. php-saml in strict mode with `wantAssertionsSigned` accepts it. |
| SAML errors | `expiredAssertion`, `wrongAudience`, `tamperedSignature` each give the expected `ProviderFailure`. |
| Overrides | The native endpoints are ordinary expectations of priority 0. An expectation of priority 10 with `remainingTimes: 1` wins once, then the native endpoint answers again. Tried: `access_denied` on `/authorize` (Velocity template echoing `state`), 500 on `/token`, an unsigned SAML status failure on `/saml/sso` (refused as `ProviderRejected`). |
| Identity switch | A new `PUT` replaces the identity (marie, then jean). |
| CAS | No native support. Plain expectations work: a 302 templated from `service` to `service` + `ticket`, a `serviceValidate` XML per ticket with multi-valued attributes, a low-priority `authenticationFailure` fallback. |
| Port 80 | `MOCKSERVER_SERVER_PORT=80` works, so addresses keep no port. |

Limits found, which shape the design:

1. **SAML attributes are single-valued**: `attributes` is a `Map<String, String>` (an array is refused with HTTP 400).
2. **One OIDC and one SAML provider at a time**: the native expectations have fixed IDs (`oidc.token`, `saml.sso`...), and the SAML ACS and audience come from the configuration, not from the AuthnRequest. A second `PUT` replaces the first.
3. **One identity per configuration**: the test chooses who signs in by configuring the mock before the click; there is no login form.
4. The image is distroless (no shell, no curl): a compose healthcheck cannot probe it.

## 4. Approach (decided)

- **A1** — The native OIDC and SAML providers of MockServer, overridden by higher-priority one-shot expectations for the errors they have no flag for. CAS uses plain expectations. Rejected: plain expectations only, with responses signed by the specs (would re-implement an identity provider: capturing nonces and request IDs, signing, chaining steps).
- **B1** — The specs talk to MockServer's REST API through a small TypeScript helper on `fetch`, with no new dependency. Rejected: the `mockserver-client` npm package (a dependency for little gain, and the OIDC and SAML providers are documented on the REST and Java sides).

## 5. Infrastructure

### 5.1 Development stack (`docker/docker-compose.yml`)

- The `yeswiki-sso` service is removed. A **generic** service takes its place **(decided)**, so that other parts of the code can use the same mock later:

  ```yaml
  # An HTTP mock for the tests: each spec configures what it answers (tests/e2e/helpers/mockserver.ts).
  # auth.yeswiki.test is the identity provider of the sign-in specs, same-site with the wiki.
  yeswiki-mock:
    image: mockserver/mockserver:9.2.0
    container_name: ${COMPOSE_PROJECT_NAME:-yeswiki}-mock
    environment:
      MOCKSERVER_SERVER_PORT: 80
    ports:
      - "127.0.0.1:${YESWIKI_MOCK_PORT:-8087}:80"
    networks:
      default:
        aliases:
          - auth.yeswiki.test
  ```

- `auth.yeswiki.test` is only a network alias of `yeswiki-mock`, the one the sign-in specs use. Another feature adds its own alias or uses `yeswiki-mock` directly.
- No healthcheck (distroless image): the helpers wait for `/mockserver/status`.
- The mock starts empty. Every spec that needs it configures it. `http://localhost:8087/mockserver/dashboard` shows the active expectations and the requests received.
- `YESWIKI_SSO_PORT` becomes `YESWIKI_MOCK_PORT` in `docker/.env.example`, `docker/README.md` and the comment at the top of the compose file.
- `yeswiki-ldap` is unchanged.

### 5.2 CI (`.github/workflows/e2e.yml`)

- The step that starts the identity servers keeps OpenLDAP as it is. The LemonLDAP::NG `docker run` becomes:

  ```sh
  docker run -d --name yeswiki-mock -p 80:1080 mockserver/mockserver:9.2.0
  ```

  and its wait loop calls `curl -X PUT http://yeswiki-mock/mockserver/status`.
- `/etc/hosts` gains `yeswiki-mock` beside `wiki.yeswiki.test auth.yeswiki.test yeswiki-ldap`.
- The shared Docker network `yeswiki-sso` was there for the portal to reach the directory; it is removed, and OpenLDAP runs with `-p 1389:1389` alone.
- The failure dump prints `docker logs yeswiki-mock` instead of `yeswiki-sso`.
- The separate "Run the sign-in suite" step stays, under `wiki.yeswiki.test`, with an updated reason in its comment: the SAML ACS configured in the mock comes from `YESWIKI_BASE_URL`, and SAML's POST callback needs the wiki and `auth.yeswiki.test` to be same-site for its Lax cookie. The comment about ports registered in `lmConf-1.json` goes.
- The `kind` job keeps excluding `sign-in-` (the cluster has no mock).

### 5.3 Keys

- `docker/lemonldap-ng/` is removed entirely.
- `oidc.key`, `oidc.crt`, `saml.key` and `saml.crt` move to `tests/fixtures/idp/`, with their README rewritten: MockServer signs the ID tokens and SAML assertions with them; they are public and protect nothing. `oidc.pub` is not used any more and is removed.
- The specs send these keys to the mock with every `PUT` **(author's call)**: MockServer's own keys are fresh on every `PUT`, and a `kid` that changes between two tests would make any caching of the key set unpredictable.

### 5.4 Documentation

- `docker/README.md`: the services table (`yeswiki-mock`: MockServer, an HTTP mock the specs configure; `auth.yeswiki.test` inside the stack, the sign-in specs' identity provider; dashboard at `http://localhost:8087/mockserver/dashboard`) and the "sign-in servers" section: the directory stays as described; the `lmConf-2.json` procedure is replaced by the dashboard and one `curl` example that configures an OIDC provider signing marie in, for trying the sign-in by hand.
- The ports listed under "Running several stacks side by side" use `YESWIKI_MOCK_PORT`.

## 6. Test helpers

### 6.1 `tests/e2e/helpers/mockserver.ts` — generic

Nothing in it is about sign-in.

- `MOCKSERVER`: the control plane, `process.env.YESWIKI_MOCKSERVER_URL` or `http://yeswiki-mock`.
- `mockReady()`: waits until `PUT /mockserver/status` answers.
- `mockReset()`: `PUT /mockserver/reset`.
- `mockExpect(expectation)`: `PUT /mockserver/expectation`.
- `mockClear(id)`: clears the expectation with this ID.
- `mockPut(path, body)`: for the native endpoints (`/mockserver/oidc`, `/mockserver/saml`).

Every call throws an error that carries MockServer's status and body when it refuses a configuration.

### 6.2 `tests/e2e/helpers/idp.ts` — the sign-in specs' identity provider

- `IDP = 'http://auth.yeswiki.test'`. The OIDC `issuer` is set to it explicitly rather than left to the `Host` header.
- `idpSignsIn(type, person, options?)` dispatches to:
  - `oidcSignsIn(person, options)`: `PUT /mockserver/oidc` with the keys of `tests/fixtures/idp/`, `clientId: 'wiki-oidc'`, `clientSecret: 'wiki-oidc-secret'`, `enforceClientAuthentication: true`, `subject` = the login. `additionalClaims` carries both the directory names (`uid`, `mail`, `givenName`, `sn`, `groups`) and the standard OIDC ones (`email`, `given_name`, `family_name`), as the portal released them: the converted loginsso provider reads the standard names. A claim the person lacks is left out (`nomail` has neither `mail` nor `email`).
  - `samlSignsIn(person, options)`: `PUT /mockserver/saml` with the keys, `idpEntityId: ${IDP}/saml/metadata`, `spEntityId` and `assertionConsumerServiceUrl` built from `YESWIKI_BASE_URL` and `options.providerId` (default `saml-a`), `subjectNameId` = the login, unspecified NameID format, and the attributes `uid`, `mail`, `givenName`, `sn`, `groups`. `groups` carries the person's **first** group only, with a comment pointing at limit 1 of section 3.
  - `casSignsIn(person)`: three expectations — `cas.login`, a 302 templated (Velocity) from `service` to `service` plus `ticket=ST-<login>` (`&` when the service already has a query, `?` otherwise); `cas.validate`, the `authenticationSuccess` XML for that ticket on `/cas/p3/serviceValidate`, with `uid`, `name` (= the login, which the converted logincas provider reads), `mail`, `givenName`, `sn` and every group; and a fallback of lower priority answering `authenticationFailure` with the code `INVALID_TICKET`.
  - `options.flags` passes the native error flags (`tamperedSignature`, `issueExpiredToken`, `wrongIssuer`, `expiredAssertion`, `wrongAudience`), and `options.clientSecret` replaces the OIDC secret the mock expects.
- One-shot failures, expectations of priority 10 with `remainingTimes: 1`:
  - `idpDenies(type)`: OIDC, `/authorize` answers 302 to `redirect_uri` with `error=access_denied` and the `state` given; SAML, `/saml/sso` answers an auto-submitting form posting an unsigned Response with the status `Responder` / `AuthnFailed`, no `InResponseTo` and `Destination` = the ACS; CAS, `cas.validate` answers `authenticationFailure` (`INVALID_TICKET`).
  - `idpEndpointFails(path, failure)`: `failure` is `{ status, body }` or `'drop'` (`httpError` with `dropConnection: true`).

### 6.3 `tests/e2e/helpers/sso.ts`

- `PORTAL` and `PORTAL_HOST` go; `IDP` comes from `idp.ts`. `PROVIDERS` point at the native endpoints: `discovery_url: ${IDP}/.well-known/openid-configuration`, `metadata_url: ${IDP}/saml/metadata`, `server_url: ${IDP}/cas`.
- `signInAtPortal` is replaced by `returnFromProvider(page)`: the browser goes through the 302 or the auto-submitted form by itself; the helper waits until it is back on the wiki's host.
- `PEOPLE` stays the single description of marie, jean and nomail, for the mock as for the directory.
- `LDAP_URI`, `PEOPLE_BASE`, `GROUPS_BASE`, `bindAs` and `setDirectoryValue` stay, for LDAP.

### 6.4 Isolation

Every `describe` of the sign-in specs that uses the mock calls `mockReset()` in its `beforeEach`, so no identity and no override outlives its test. The specs share one mock: this relies on `workers: 1` and `fullyParallel: false` in `playwright.config.ts`, which `mockserver.ts` states in a comment.

## 7. Specs

### 7.1 Ported

**`sign-in-providers.spec.ts`**

- The describe becomes `` `${type} against the mock identity provider` `` and resets the mock before each test.
- `signInWith(page, type, person)`: for a redirect type, `idpSignsIn(type, person)`, then the provider's button, then `returnFromProvider(page)`. LDAP is unchanged (the wiki's own form).
- "a return visit refreshes the profile": LDAP keeps `setDirectoryValue`; the other types sign in the second time as `{ ...PEOPLE.jean, lastName: 'Martin-Durand' }`.
- "test mode signs nobody in": the person is configured in the mock before the click on the test link.
- "explicit endpoints complete on the provider callback": `${IDP}/authorize`, `${IDP}/token`, `${IDP}/userinfo`.
- "two providers at once each link their own account": `samlSignsIn(marie, { providerId: 'saml-a' })` before going through saml-a, `samlSignsIn(jean, { providerId: 'saml-b' })` before saml-b, then `samlSignsIn(marie, { providerId: 'saml-b' })` for the refused takeover. The comment about the portal remembering who signed in goes.
- The other tests (`no_account` refusal, `nomail`, mapped groups, nothing in the journal on success, SAML metadata, local accounts beside a provider) keep their assertions. Marie's first group is `wiki-editors`, so the SAML group mapping test still applies.

**`sign-in-admin.spec.ts`**: the visitor's sign-in in "adds an OIDC provider, and a blank secret keeps the stored one" calls `idpSignsIn('oidc', PEOPLE.marie)` before the click. With `enforceClientAuthentication`, it still proves that the stored secret is sent.

**`sign-in-upgrade.spec.ts`**

- loginsso: the Doryphore configuration uses `${IDP}/authorize`, `${IDP}/token` and `${IDP}/userinfo`; `oidcSignsIn(PEOPLE.marie)` before the click. The checks on `redirect_uri` and on the single callback stay; the comment about the portal escaping the slashes of the query is checked against what MockServer sends and kept or removed accordingly.
- logincas: the converted `https://auth.yeswiki.test:443/cas` is still replaced by `${IDP}/cas` (the mock serves plain http); `casSignsIn(PEOPLE.marie)` before the click.
- loginldap: unchanged.

**`sign-in-extension.spec.ts`**: does not use the portal; unchanged.

### 7.2 New: `sign-in-provider-errors.spec.ts`

A table of cases, each `{ type, name, arrange, reason, detail }`. A regression seen on a real server is added as a row whose comment names the server. Each case:

1. sets the provider section of its type and resets the mock;
2. runs `arrange` (it configures the mock for marie, with a flag, an override or a configuration change);
3. signs marie in through the provider's button;
4. checks that the refused page names the provider and that nobody is signed in; that a `login.failed` row has `reason`; that the diagnostic `login.refused.<reason>.<id>` has a `context.error` containing `detail`; and that the journal holds neither the client secret, nor a SAML response or assertion, nor a private key.

| Type | Case | Arrange | Reason |
| --- | --- | --- | --- |
| OIDC | tampered ID token signature | flag `tamperedSignature` | `invalid_response` |
| OIDC | expired ID token | flag `issueExpiredToken` | `invalid_response` |
| OIDC | wrong issuer | flag `wrongIssuer` | `invalid_response` |
| OIDC | the person refuses | `idpDenies('oidc')` | `provider_rejected` |
| OIDC | token endpoint fails | `idpEndpointFails('/token', { status: 500, body: 'boom' })` | `invalid_response` |
| OIDC | token endpoint drops the connection | `idpEndpointFails('/token', 'drop')` | `provider_unreachable` |
| OIDC | secret refused | the mock expects another `clientSecret` | `provider_rejected` |
| OIDC | discovery unreachable | `idpEndpointFails('/.well-known/openid-configuration', 'drop')` | `provider_unreachable` |
| SAML | tampered signature | flag `tamperedSignature` | `invalid_response` |
| SAML | expired assertion | flag `expiredAssertion` | `invalid_response` |
| SAML | wrong audience | flag `wrongAudience` | `invalid_response` |
| SAML | status failure | `idpDenies('saml')` | `provider_rejected` |
| SAML | metadata unreachable | `idpEndpointFails('/saml/metadata', 'drop')` | `provider_unreachable` |
| CAS | ticket refused | `idpDenies('cas')` | `provider_rejected` |
| CAS | ticket validation drops the connection | `idpEndpointFails('/cas/p3/serviceValidate', 'drop')` | `provider_unreachable` |

The OIDC and SAML reasons were observed in the spike; the CAS and network ones are read from the `FailureReason` each provider throws (`CasProvider`, `OidcProvider::call()`, `OidcDiscovery`, `SamlProvider::identityProvider()`). The `detail` of each row is the stable part of the message the provider writes (for example `Signature verification failed`, `access_denied`, `invalid_client`).

Where a failure happens before the person leaves the wiki (discovery, metadata), there is no trip to the mock: the refusal is shown on the way out, and the case checks the same things.

One more case, outside the table: an administrator tests an OIDC provider whose ID token signature is tampered with. The test screen shows the technical detail to the administrator, nobody is signed in, and the administrator is still the one signed in.

## 8. Verification

- `docker compose -f docker/docker-compose.yml config` is valid.
- The sign-in specs pass locally against the compose stack: `npx playwright test tests/e2e/tests/sign-in-` with `YESWIKI_BASE_URL` set to the wiki under `wiki.yeswiki.test`.
- The PHPUnit tests under `tests/src/Identity/SignIn/` still pass.
- `grep -ri lemonldap` finds only the user documentation and the historical documents of section 2.
