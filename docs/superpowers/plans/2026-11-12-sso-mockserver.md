# Sign-in tests against MockServer: implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The OIDC, SAML and CAS sign-in specs run against MockServer instead of LemonLDAP::NG, and a new spec covers the providers' error cases.

**Architecture:** A generic `yeswiki-mock` service (MockServer 9.2.0) joins the development stack and CI. A generic TypeScript helper drives MockServer's REST API. A sign-in helper builds on it: it plays the identity provider with MockServer's native OIDC and SAML providers, plain expectations for CAS, and one-shot higher-priority expectations for failures. OpenLDAP stays for the LDAP provider. LemonLDAP::NG is removed last, once nothing uses it.

**Tech Stack:** Playwright 1.61 (TypeScript, `fetch`), MockServer `mockserver/mockserver:9.2.0`, Docker Compose, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-11-12-sso-mockserver-design.md`. Read it first: section 3 lists what MockServer does and its limits, and the plan relies on them.

## Global Constraints

- Image: `mockserver/mockserver:9.2.0`, exactly. The 5.x images have no OIDC, SAML or binary support.
- Service name `yeswiki-mock`, container `${COMPOSE_PROJECT_NAME:-yeswiki}-mock`, port variable `YESWIKI_MOCK_PORT` (default `8087`), network alias `auth.yeswiki.test`.
- Identity provider address in the specs: `IDP = 'http://auth.yeswiki.test'` (no port). Control plane: `process.env.YESWIKI_MOCKSERVER_URL || 'http://yeswiki-mock'`.
- OIDC client: `wiki-oidc` / `wiki-oidc-secret`, `enforceClientAuthentication: true`.
- Keys: `tests/fixtures/idp/{oidc,saml}.{key,crt}`, sent with every `PUT`.
- No new npm or Composer dependency. No product code change: a case that fails because of the product is reported, not hidden.
- LDAP stays on OpenLDAP (`yeswiki-ldap`, `docker/openldap/seed.ldif` unchanged).
- Code style: the repository's ESLint and Prettier (`yarn lint-js`, `yarn lint-format`). Comments and test names follow the existing specs (plain sentences, saying what is true).
- Push nothing before Task 5: until then, CI still starts LemonLDAP::NG and not the mock.

## How to run the sign-in specs locally

The stack must run with the mock (Task 1 adds it). From `docker/`:

```sh
docker compose up -d
docker compose exec -e YESWIKI_TEST_BASE_URL='http://wiki.yeswiki.test/?' yeswiki-app bash tests/e2e/reset.sh
docker compose exec -e PLAYWRIGHT_BROWSERS_PATH=0 -e YESWIKI_BASE_URL=http://wiki.yeswiki.test -e YESWIKI_TEST_BASE_URL='http://wiki.yeswiki.test/?' \
  yeswiki-app npx playwright test tests/e2e/tests/<spec>
```

Below, **SIGN-IN RUN `<spec>`** means these three commands with that spec file.

## Review Focus

1. **An override or an identity left over from a previous test.** If an expected failure happens before the mock is reached, a one-shot override is never consumed and would fail the next test. Every describe that uses the mock resets it in `beforeEach` (Tasks 2 to 4). The errors spec also asserts, after each case, that a plain sign-in of marie works again once the mock is reset (Task 4, Step 1).
2. **The wiki served on a port (CI: `wiki.yeswiki.test:8080`).** The SAML ACS and entity ID the mock emits must equal what the wiki computes, port included, or every SAML sign-in fails with a destination or audience error. `idp.ts` builds them from the same `YESWIKI_BASE_URL` value the specs use (Task 2). The SAML metadata test pins the format.
3. **A CAS service URL that already has a query** (`?user/sign-in/cas/callback`). The ticket must be appended with `&`, not `?`. The template branches on it (Task 2); the CAS sign-in tests exercise it.
4. **The mock not running.** Every sign-in spec would fail with a connection error at the first `fetch`. `mockReady()` in `beforeAll` fails at once and names the control-plane URL and `docker compose up yeswiki-mock` (Task 1).
5. **A person without an attribute** (`nomail`). The mock must not invent a `mail` or an `email` claim. `oidcSignsIn` leaves both out, and the `nomail` test keeps proving that the refusal names the attribute (Task 2).

---

### Task 1: MockServer in the development stack, with the generic helper

**Files:**
- Modify: `docker/docker-compose.yml` (add `yeswiki-mock`; `yeswiki-sso` stays until Task 5)
- Modify: `docker/.env.example` (add `#YESWIKI_MOCK_PORT=8087`; the existing `#YESWIKI_SSO_PORT=8087` line becomes `#YESWIKI_SSO_PORT=8088` so the two do not collide until Task 5)
- Modify: `docker/docker-compose.yml`, the `yeswiki-sso` port default: `8088`
- Create: `tests/fixtures/idp/README.md`, plus `oidc.key`, `oidc.crt`, `saml.key`, `saml.crt` moved with `git mv` from `docker/lemonldap-ng/test-keys/`
- Create: `tests/e2e/helpers/mockserver.ts`

**Interfaces:**
- Produces, in `tests/e2e/helpers/mockserver.ts`:
  - `export const MOCKSERVER: string`
  - `export type Expectation = Record<string, unknown>`: MockServer's expectation JSON, as sent to `PUT /mockserver/expectation`
  - `export const mockReady = async (): Promise<void>`
  - `export const mockReset = async (): Promise<void>`
  - `export const mockExpect = async (...expectations: Expectation[]): Promise<void>`
  - `export const mockClear = async (id: string): Promise<void>`
  - `export const mockPut = async (path: string, body: object): Promise<void>`

- [ ] **Step 1: Add the service.** Use the block of spec section 5.1 verbatim (`MOCKSERVER_SERVER_PORT: 80`, `127.0.0.1:${YESWIKI_MOCK_PORT:-8087}:80`, alias `auth.yeswiki.test`). Remove the alias `auth.yeswiki.test` from `yeswiki-sso`, since two services cannot share it, and move its port default to `8088`. From here until Task 3, the specs still written for the portal (`sign-in-providers`, `sign-in-admin`, `sign-in-upgrade`) fail in the development stack; Tasks 2 and 3 port them. Update the comment at the top of the file: "an HTTP mock for the tests" in place of "an SSO portal".

- [ ] **Step 2: Check the stack.**

Run: `docker compose -f docker/docker-compose.yml config --quiet && docker compose -f docker/docker-compose.yml up -d yeswiki-mock && curl -s -o /dev/null -w '%{http_code}\n' -X PUT http://localhost:8087/mockserver/status`
Expected: no output from `config`, then `200`.

- [ ] **Step 3: Move the keys.** Use `git mv docker/lemonldap-ng/test-keys/{oidc,saml}.{key,crt} tests/fixtures/idp/` and `git rm docker/lemonldap-ng/test-keys/oidc.pub`. Rewrite the README from the existing one: the keys sign the ID tokens and SAML assertions of the mock identity provider (`tests/e2e/helpers/idp.ts` sends them to MockServer), they are public and protect nothing, and they are regenerated with `openssl req -x509 -newkey rsa:2048 -nodes -keyout <name>.key -out <name>.crt -days 36500 -subj /CN=yeswiki-test-<name>`. `docker/lemonldap-ng/conf/lmConf-1.json` holds its own copy of the keys, so the portal keeps working until Task 5. Delete `docker/lemonldap-ng/test-keys/README.md`.

- [ ] **Step 4: Write `mockserver.ts`.** Each function is a `fetch` with method `PUT` to `${MOCKSERVER}/mockserver/<name>` and a JSON body: `status`, `reset`, `expectation` (sends the array), `clear` (body `{ id }`, query `type=EXPECTATIONS`), and `mockPut(path)` to `${MOCKSERVER}${path}`. A non-2xx answer throws `new Error(\`MockServer refused ${path}: ${status} ${await response.text()}\`)`. `mockReady` retries `status` for at most 10 s; on a connection error it throws `MockServer is not reachable at ${MOCKSERVER}: start it with \`docker compose up -d yeswiki-mock\``. The file's doc comment says what the helper is for (any spec that needs an HTTP service to answer as it says), and that the specs share one mock, which relies on `workers: 1` and `fullyParallel: false` in `playwright.config.ts`.

- [ ] **Step 5: Lint.**

Run: `docker compose -f docker/docker-compose.yml exec yeswiki-app yarn eslint tests/e2e/helpers/mockserver.ts && docker compose -f docker/docker-compose.yml exec yeswiki-app node_modules/.bin/prettier --check tests/e2e/helpers/mockserver.ts tests/fixtures/idp/README.md docker/docker-compose.yml`
Expected: no errors.

- [ ] **Step 6: Commit.**

```bash
git add docker/docker-compose.yml docker/.env.example tests/fixtures/idp tests/e2e/helpers/mockserver.ts docker/lemonldap-ng/test-keys
git commit -m "test(sso): a MockServer in the development stack, and a helper to drive it"
```

---

### Task 2: The mock identity provider, and the providers spec against it

**Files:**
- Create: `tests/e2e/helpers/idp.ts`
- Modify: `tests/e2e/helpers/sso.ts`
- Modify: `tests/e2e/tests/sign-in-providers.spec.ts`

**Interfaces:**
- Consumes: `mockPut`, `mockExpect`, `mockReset`, `mockReady` (Task 1).
- Produces, in `tests/e2e/helpers/idp.ts`:
  - `export const IDP = 'http://auth.yeswiki.test'`
  - `export const WIKI: string`, which is `(process.env.YESWIKI_BASE_URL || 'http://wiki.yeswiki.test').replace(/\/$/, '')`. The specs import it in place of their own `WIKI` constants.
  - `export type Person = { login: string; firstName?: string; lastName?: string; email?: string; groups?: string[] }`
  - `export type RedirectType = 'oidc' | 'saml' | 'cas'`
  - `export type IdpFlag = 'tamperedSignature' | 'issueExpiredToken' | 'wrongIssuer' | 'expiredAssertion' | 'wrongAudience'`
  - `export type IdpOptions = { providerId?: string; flags?: IdpFlag[]; clientSecret?: string }`
  - `export const oidcSignsIn = async (person: Person, options?: IdpOptions): Promise<void>`
  - `export const samlSignsIn = async (person: Person, options?: IdpOptions): Promise<void>`
  - `export const casSignsIn = async (person: Person): Promise<void>`
  - `export const idpSignsIn = async (type: RedirectType, person: Person, options?: IdpOptions): Promise<void>`
- Produces, in `tests/e2e/helpers/sso.ts`:
  - `export const returnFromProvider = async (page: Page): Promise<void>`
  - `IDP` and `WIKI` re-exported. `PORTAL`, `PORTAL_HOST` and `signInAtPortal` are removed in Task 3, once nothing imports them.

- [ ] **Step 1: Point the providers at the mock.** In `sso.ts`, set `PROVIDERS.oidc.connection.discovery_url` to `${IDP}/.well-known/openid-configuration`, `PROVIDERS.saml.connection.metadata_url` to `${IDP}/saml/metadata`, and `PROVIDERS.cas.connection.server_url` to `${IDP}/cas`. Update the file's doc comment to say that the identity provider is MockServer, configured by `idp.ts`, and that the directory is OpenLDAP. In `sign-in-providers.spec.ts`:
  - rename the describe to `` `${type} against the mock identity provider` `` and add `await mockReset()` to its `beforeEach`;
  - add `test.beforeAll(mockReady)` at file level;
  - change `signInWith(page, type, login)` to `signInWith(page, type, person: Person)`: for LDAP it types `person.login`, as before; for a redirect type it calls `await idpSignsIn(type, person)`, clicks the provider's button, then calls `await returnFromProvider(page)`;
  - in "test mode signs nobody in", call `idpSignsIn(type, PEOPLE.marie)` for a redirect type before clicking the test link, and replace `signInAtPortal` in `enterTestCredentials` with `returnFromProvider`;
  - in "a return visit refreshes the profile", keep `setDirectoryValue` for `ldap` only; for the other types the second sign-in uses `{ ...PEOPLE.jean, lastName: 'Martin-Durand' }`;
  - in "explicit endpoints complete on the provider callback", use `${IDP}/authorize`, `${IDP}/token` and `${IDP}/userinfo`;
  - in "two providers at once each link their own account", `through(provider, person)` calls `samlSignsIn(person, { providerId: provider })` before its click, and `returnFromProvider` in place of `signInAtPortal`. Remove the comment about the portal remembering the last sign-in.

- [ ] **Step 2: Run the spec to see it fail.**

Run: SIGN-IN RUN `sign-in-providers.spec.ts`
Expected: does not compile (`idp.ts` does not exist), or, once a stub exports the names, the OIDC, SAML and CAS tests fail because the mock answers 404. The LDAP and "local accounts" tests pass.

- [ ] **Step 3: Write `oidcSignsIn` and `samlSignsIn`.** Both read their keys once from `tests/fixtures/idp/` (resolve them from `import.meta.url`, as `sso.ts` resolves its helpers).
  - `oidcSignsIn` calls `mockPut('/mockserver/oidc', …)` with:
    - `issuer: IDP`, `clientId: 'wiki-oidc'`, `clientSecret: options.clientSecret ?? 'wiki-oidc-secret'`, `enforceClientAuthentication: true`, `subject: person.login`;
    - `privateKeyPem` and `certificatePem` from `oidc.key` and `oidc.crt`, `keyId: 'yeswiki-test-oidc'`, `scopes: ['openid', 'profile', 'email']`;
    - one key `true` per flag;
    - `additionalClaims` built from the person: `uid` = login; `mail` and `email` = `person.email`; `givenName` and `given_name` = `person.firstName`; `sn` and `family_name` = `person.lastName`; `groups` = `person.groups`. A claim whose value is `undefined` is left out.
  - `samlSignsIn` calls `mockPut('/mockserver/saml', …)` with:
    - `idpEntityId: ${IDP}/saml/metadata`;
    - `spEntityId: ${WIKI}/?user/sign-in/${id}/metadata` and `assertionConsumerServiceUrl: ${WIKI}/?user/sign-in/${id}/callback`, where `id = options.providerId ?? 'saml-a'`;
    - `subjectNameId: person.login`, `nameIdFormat: 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified'`;
    - `signingCertificatePem` and `signingPrivateKeyPem` from `saml.crt` and `saml.key`;
    - one key `true` per flag;
    - `attributes`, an object of strings: `uid`, `mail`, `givenName`, `sn`, and `groups` = `person.groups?.[0]`. The comment says MockServer's SAML attributes take one value each, so only the first group is sent. Undefined values are left out.

- [ ] **Step 4: Write `casSignsIn`.** It calls `mockExpect` with three expectations:
  1. id `cas.login`: `GET /cas/login`, `httpResponseTemplate` of type `VELOCITY`. It answers 302 with `Location` = `service` + (`&` when `service` contains `?`, `?` otherwise) + `ticket=ST-<login>`. Write `<login>` into the template from TypeScript; `service` comes from `$request.queryStringParameters.service[0]`. The template that worked in the spike:

     ```
     #set($s = $request.queryStringParameters.service[0])#set($sep = "?")#if($s.contains("?"))#set($sep = "&")#end{"statusCode":302,"headers":{"Location":["$s${sep}ticket=ST-LOGIN"]}}
     ```

  2. id `cas.validate`: `GET /cas/p3/serviceValidate` with `queryStringParameters: { ticket: ['ST-<login>'] }`. It answers 200 `application/xml` with `<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas"><cas:authenticationSuccess><cas:user>LOGIN</cas:user><cas:attributes>…</cas:attributes></cas:authenticationSuccess></cas:serviceResponse>`. The attributes are `uid` and `name` (both the login), `mail`, `givenName`, `sn`, and one `cas:groups` element per group, each present only when the person has it. Escape the values for XML.
  3. id `cas.unknown-ticket`: `GET /cas/p3/serviceValidate` at `priority: -1`. It answers `<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas"><cas:authenticationFailure code="INVALID_TICKET">unknown ticket</cas:authenticationFailure></cas:serviceResponse>`.

  `idpSignsIn` dispatches on `type`.

- [ ] **Step 5: Write `returnFromProvider(page)`** in `sso.ts`: `await page.waitForURL((url) => url.origin === new URL(WIKI).origin, { waitUntil: 'domcontentloaded' })`. The browser goes through the 302 or the self-submitting form on its own. Its doc comment says that.

- [ ] **Step 6: Run the spec to see it pass.**

Run: SIGN-IN RUN `sign-in-providers.spec.ts`
Expected: every test passes, for the four types and for "local accounts beside a provider".

- [ ] **Step 7: Lint and commit.**

Run: `docker compose -f docker/docker-compose.yml exec yeswiki-app sh -c "yarn eslint tests/e2e && node_modules/.bin/prettier --check 'tests/e2e/**/*.ts'"`
Expected: no errors.

```bash
git add tests/e2e/helpers/idp.ts tests/e2e/helpers/sso.ts tests/e2e/tests/sign-in-providers.spec.ts
git commit -m "test(sso): the providers spec signs in through a mock identity provider"
```

---

### Task 3: The admin and upgrade specs against the mock

**Files:**
- Modify: `tests/e2e/tests/sign-in-admin.spec.ts`
- Modify: `tests/e2e/tests/sign-in-upgrade.spec.ts`
- Modify: `tests/e2e/helpers/sso.ts` (remove `PORTAL`, `PORTAL_HOST` and `signInAtPortal`)

**Interfaces:**
- Consumes: `IDP`, `WIKI`, `idpSignsIn`, `oidcSignsIn`, `casSignsIn` (Task 2); `returnFromProvider` (Task 2); `mockReady` and `mockReset` (Task 1).

- [ ] **Step 1: Port `sign-in-admin.spec.ts`.**
  - Add `mockReset()` to the describe's `beforeEach` and `test.beforeAll(mockReady)`.
  - In "adds an OIDC provider, and a blank secret keeps the stored one", call `await idpSignsIn('oidc', PEOPLE.marie)` before the visitor's click, and replace `signInAtPortal` with `returnFromProvider(visitor)`.
  - The comment "the portal only answers a client that presents the stored secret" becomes "the mock identity provider refuses a client that does not present the stored secret".

- [ ] **Step 2: Port `sign-in-upgrade.spec.ts`.**
  - Replace its `WIKI` with the import.
  - loginsso: the Doryphore `auth_options` use `${IDP}/authorize`, `${IDP}/token` and `${IDP}/userinfo`, and the `authorize_url` expectation and the `waitForRequest` predicate use `${IDP}/authorize`. Call `await oidcSignsIn(PEOPLE.marie)` before `page.goto('/?user')`, and replace `signInAtPortal` with `returnFromProvider(page)`.
  - logincas: `cas_host: new URL(IDP).host`. The expected converted URL is `https://${new URL(IDP).host}:443/cas`, and the replacement `server_url` is `${IDP}/cas`. The "NOT EXERCISED" comment says "the mock serves CAS on plain http only". `signInThroughPortal` becomes `signInThroughProvider(browser, label, type: RedirectType)`: it calls `idpSignsIn(type, PEOPLE.marie)`, then the click, then `returnFromProvider`.
  - Add `mockReset()` to a `beforeEach` of the describe and `test.beforeAll(mockReady)`.
  - Update the file's doc comment: "against the mock identity provider and the directory of the development stack".
  - The comment "the portal escapes the slashes of the query it was given" stays only if MockServer does it too: log one `request.url()` during Step 3, and keep or remove the comment according to what you see. The predicate already handles both forms.

- [ ] **Step 3: Run both specs.**

Run: SIGN-IN RUN `sign-in-admin.spec.ts`, then SIGN-IN RUN `sign-in-upgrade.spec.ts`
Expected: every test passes.

- [ ] **Step 4: Remove the portal helpers.** Delete `PORTAL`, `PORTAL_HOST` and `signInAtPortal` from `sso.ts`. `grep -rn "PORTAL\|signInAtPortal" tests/e2e` must find nothing.

- [ ] **Step 5: Lint, run the four sign-in specs, commit.**

Run: the lint command of Task 2, Step 7. Then SIGN-IN RUN `sign-in-`.
Expected: no lint error. Every sign-in spec passes, `sign-in-extension` included.

```bash
git add tests/e2e
git commit -m "test(sso): the admin and upgrade specs sign in through the mock identity provider"
```

---

### Task 4: Failures on demand, and the errors spec

**Files:**
- Modify: `tests/e2e/helpers/idp.ts` (add `idpDenies` and `idpEndpointFails`)
- Create: `tests/e2e/tests/sign-in-provider-errors.spec.ts`

**Interfaces:**
- Consumes: Task 2's `idp.ts` and `sso.ts` exports; `journal`, `JournalRow`, `setProviders`, `sectionFor`, `PROVIDER_IDS`, `PROVIDERS`, `PEOPLE`, `PASSWORD` from `sso.ts`; `login`, `ADMIN_USERNAME`, `ADMIN_PASSWORD` from `helpers/login`.
- Produces, in `tests/e2e/helpers/idp.ts`:
  - `export const idpDenies = async (type: RedirectType, providerId?: string): Promise<void>`
  - `export type EndpointFailure = { status: number; body?: string } | 'drop'`
  - `export const idpEndpointFails = async (path: string, failure: EndpointFailure): Promise<void>`

- [ ] **Step 1: Write the spec.** One `test.describe('a provider that fails', …)`. Its `beforeEach` calls `forgetAccounts(PEOPLE.marie.email)` and `mockReset()`; `test.beforeAll(mockReady)`. The cases form a table:

```ts
type Case = {
  type: RedirectType
  name: string
  arrange: () => Promise<void> // configures the mock, for marie
  reason: string // the FailureReason value
  detail: string // the stable part of the diagnostic's context.error
}
```

The 15 rows of spec section 7.2, in order, with these `detail` values:

| Case | `arrange` | `reason` | `detail` |
| --- | --- | --- | --- |
| oidc tampered ID token signature | `oidcSignsIn(marie, { flags: ['tamperedSignature'] })` | `invalid_response` | `Signature verification failed` |
| oidc expired ID token | flag `issueExpiredToken` | `invalid_response` | `the ID token has expired` |
| oidc wrong issuer | flag `wrongIssuer` | `invalid_response` | `the ID token has the issuer` |
| oidc the person refuses | `oidcSignsIn(marie)` + `idpDenies('oidc')` | `provider_rejected` | `access_denied` |
| oidc token endpoint fails | `oidcSignsIn(marie)` + `idpEndpointFails('/token', { status: 500, body: 'boom' })` | `invalid_response` | `the token endpoint answered something unreadable` |
| oidc token endpoint drops the connection | `oidcSignsIn(marie)` + `idpEndpointFails('/token', 'drop')` | `provider_unreachable` | `the token endpoint could not be reached` |
| oidc secret refused | `oidcSignsIn(marie, { clientSecret: 'another-secret' })` | `provider_rejected` | `invalid_client` |
| oidc discovery unreachable | `oidcSignsIn(marie)` + `idpEndpointFails('/.well-known/openid-configuration', 'drop')` | `provider_unreachable` | `the discovery document at` |
| saml tampered signature | `samlSignsIn(marie, { flags: ['tamperedSignature'] })` | `invalid_response` | `Signature validation failed` |
| saml expired assertion | flag `expiredAssertion` | `invalid_response` | `Could not validate timestamp: expired` |
| saml wrong audience | flag `wrongAudience` | `invalid_response` | `Invalid audience` |
| saml status failure | `samlSignsIn(marie)` + `idpDenies('saml')` | `provider_rejected` | `was not Success` |
| saml metadata unreachable | `samlSignsIn(marie)` + `idpEndpointFails('/saml/metadata', 'drop')` | `provider_unreachable` | `could not be fetched` |
| cas ticket refused | `casSignsIn(marie)` + `idpDenies('cas')` | `provider_rejected` | `the CAS server refused the ticket: INVALID_TICKET` |
| cas ticket validation drops the connection | `casSignsIn(marie)` + `idpEndpointFails('/cas/p3/serviceValidate', 'drop')` | `provider_unreachable` | `could not be reached to validate the ticket` |

The comment above the table says how to add a regression: one row, with a comment naming the server and the version it was seen on.

Each case is a test named `` `${type}: ${name}` ``. It:
1. calls `setProviders(sectionFor(type))`;
2. notes the latest Journal ID (as `latestRow` does in the providers spec);
3. calls `arrange()`;
4. goes to `/?user` and clicks the provider's button (`PROVIDERS[type].label`);
5. waits for `page.waitForURL((url) => url.origin === new URL(WIKI).origin)`, because the refusal may be shown before the browser leaves the wiki;
6. asserts:

```ts
await expect(page.locator('h1')).toBeVisible()
await expect(page.locator('body')).toContainText(String(PROVIDERS[type].label))
const rows = journal('login.%').filter((row) => Number(row.id) > before)
const id = PROVIDER_IDS[type]
expect(rows.find((row) => row.action === 'login.failed')?.context).toMatchObject({ provider: id, reason })
const diagnostic = rows.find((row) => row.action === `login.refused.${reason}.${id}`)
expect(String(diagnostic?.context.error)).toContain(detail)
expect(JSON.stringify(rows)).not.toMatch(/wiki-oidc-secret|SAMLResponse|saml2?p?:Assertion|BEGIN [A-Z ]*KEY/)
await page.goto('/?user')
await expect(page.locator('.yw-account-guest__card')).toBeVisible()
// the mock answers again as it should: the failure was this sign-in's alone
await mockReset()
await idpSignsIn(type, PEOPLE.marie)
await page.locator('.yw-account-guest__card .yw-sign-in__provider', { hasText: String(PROVIDERS[type].label) }).click()
await returnFromProvider(page)
await page.goto('/?user')
await expect(page.locator('.yw-account-guest__card')).toHaveCount(0)
```

One more test, `oidc: an administrator's test shows what failed`:
1. `setProviders(sectionFor('oidc', { enabled: false }))`;
2. `login(page, ADMIN_USERNAME, ADMIN_PASSWORD)`;
3. `oidcSignsIn(marie, { flags: ['tamperedSignature'] })`;
4. click `a[href*="admin/sign-in/oidc/test"]` on `/?admin/sign-in`, then `returnFromProvider(page)`.

It asserts that the body contains `Signature verification failed`, that `accountWithEmail(PEOPLE.marie.email)` is `null`, and that `/?user` still shows `ADMIN_USERNAME` with no guest card.

- [ ] **Step 2: Run the spec to see it fail.**

Run: SIGN-IN RUN `sign-in-provider-errors.spec.ts`
Expected: the cases that use `idpDenies` or `idpEndpointFails` fail on the missing exports. The flag and secret cases may already pass.

- [ ] **Step 3: Write `idpEndpointFails(path, failure)`.** It sends one expectation with id `fails ${path}`, `priority: 10`, `times: { remainingTimes: 1 }` and `httpRequest: { path }`. For `'drop'` it uses `httpError: { dropConnection: true }`; otherwise `httpResponse: { statusCode: failure.status, body: failure.body ?? '' }`.

- [ ] **Step 4: Write `idpDenies(type, providerId = 'saml-a')`.** It sends one expectation with `priority: 10` and `times: { remainingTimes: 1 }`:
  - **oidc**: `GET /authorize`, a Velocity template answering 302 to `$request.queryStringParameters.redirect_uri[0]` + `&error=access_denied&error_description=refused&state=` + `$request.queryStringParameters.state[0]`. The wiki's callback always has a query (`?user/sign-in/<id>/callback`), hence the `&`.
  - **saml**: `/saml/sso` (any method). It answers 200 `text/html; charset=utf-8` with `<body onload="document.forms[0].submit()">` and a form posting `SAMLResponse` to `${WIKI}/?user/sign-in/${providerId}/callback`. The response is base64 of an unsigned `samlp:Response` with:
    - `ID="_refused"`, `Version="2.0"`, `IssueInstant` = now (ISO, no milliseconds);
    - `Destination` = that callback, no `InResponseTo`;
    - `saml:Issuer` = `${IDP}/saml/metadata`;
    - a `samlp:Status` with `StatusCode` `urn:oasis:names:tc:SAML:2.0:status:Responder`, a nested `urn:oasis:names:tc:SAML:2.0:status:AuthnFailed`, and `StatusMessage` `The user cancelled`.

    `tests/fixtures/saml/generate.php` writes the same document for `response-status-failure.xml`.
  - **cas**: `GET /cas/p3/serviceValidate`, the `authenticationFailure` body of `cas.unknown-ticket` (Task 2, Step 4).

- [ ] **Step 5: Run the spec to see it pass.**

Run: SIGN-IN RUN `sign-in-provider-errors.spec.ts`
Expected: the 16 tests pass. If a case gets a different `reason` than its row says, do not change the row. Stop and report the case, what the Journal holds, and the provider code that threw (spec section 2: no product change in this work).

- [ ] **Step 6: Lint, run every sign-in spec, commit.**

Run: the lint command of Task 2, Step 7. Then SIGN-IN RUN `sign-in-`.
Expected: no lint error; every sign-in spec passes.

```bash
git add tests/e2e/helpers/idp.ts tests/e2e/tests/sign-in-provider-errors.spec.ts
git commit -m "test(sso): what a person and an administrator see when a provider fails, case by case"
```

---

### Task 5: LemonLDAP::NG leaves the stack, CI and the docs

**Files:**
- Modify: `docker/docker-compose.yml` (remove `yeswiki-sso`)
- Delete: `docker/lemonldap-ng/`
- Modify: `docker/.env.example`, `docker/README.md`
- Modify: `.github/workflows/e2e.yml`

- [ ] **Step 1: Remove the portal.**
  - Delete the `yeswiki-sso` service and its comment from `docker/docker-compose.yml`.
  - `git rm -r docker/lemonldap-ng`.
  - In `docker/.env.example`, delete the `#YESWIKI_SSO_PORT` line.

- [ ] **Step 2: Rewrite the docs of `docker/README.md`.**
  - Services table: replace the `yeswiki-sso` row with `yeswiki-mock`, described as "MockServer, an HTTP mock the specs configure; `auth.yeswiki.test` inside the stack, the identity provider of the sign-in specs", at <http://localhost:8087/mockserver/dashboard>.
  - "The sign-in servers": keep the directory paragraph. Replace the portal paragraph and the `lmConf-2.json` procedure with:
    - the mock starts empty;
    - the sign-in specs configure it (`tests/e2e/helpers/idp.ts`);
    - the dashboard shows what it was asked and what it answered;
    - this example, for signing marie in by hand through a provider whose `discovery_url` is `http://auth.yeswiki.test/.well-known/openid-configuration`:

    ```sh
    curl -X PUT http://localhost:8087/mockserver/oidc -d '{"issuer":"http://auth.yeswiki.test","clientId":"wiki-oidc","clientSecret":"wiki-oidc-secret","subject":"marie","additionalClaims":{"uid":"marie","mail":"marie.dupont@acme.example","givenName":"Marie","sn":"Dupont","groups":["wiki-editors","staff"]}}'
    ```

  - "Running several stacks side by side": `8087` is now the mock's port, and `YESWIKI_SSO_PORT=8187` becomes `YESWIKI_MOCK_PORT=8187`.

- [ ] **Step 3: Update CI.** In `.github/workflows/e2e.yml`, step "Start the identity servers":
  - `/etc/hosts` gains `yeswiki-mock`;
  - remove `docker network create yeswiki-sso`, and `--network yeswiki-sso` from the OpenLDAP run;
  - replace the LemonLDAP::NG `docker run` and its wait loop with `docker run -d --name yeswiki-mock -p 80:1080 mockserver/mockserver:9.2.0`, plus a 60 × 2 s loop on `curl --silent --fail --max-time 5 -X PUT http://yeswiki-mock/mockserver/status` that ends with `echo "the mock did not come up"; docker logs yeswiki-mock; exit 1`;
  - rewrite the step's comment: OpenLDAP for the LDAP provider, MockServer as the identity provider; `wiki.yeswiki.test` and `auth.yeswiki.test` stay same-site for SAML's POST callback.

  Elsewhere in the file:
  - "Run the sign-in suite": its comment gives spec section 5.2's reason (the ACS configured in the mock comes from `YESWIKI_BASE_URL`, and SAML's POST callback needs the two hosts same-site). Nothing about ports registered in `lmConf-1.json`.
  - "Dump the runtime log": `docker logs yeswiki-mock`.
  - The `kind` job's comment says "the mock and the directory" in place of "the identity servers".

- [ ] **Step 4: Verify.**

Run:
- `docker compose -f docker/docker-compose.yml config --quiet`, then `docker compose -f docker/docker-compose.yml up -d --remove-orphans`;
- SIGN-IN RUN `sign-in-`;
- `docker compose -f docker/docker-compose.yml exec yeswiki-app vendor/bin/phpunit tests/src/Identity/SignIn`;
- `grep -rli lemonldap --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git .`;
- `docker compose -f docker/docker-compose.yml exec yeswiki-app node_modules/.bin/prettier --check docker/README.md docker/docker-compose.yml .github/workflows/e2e.yml`.

Expected:
- no `config` output;
- every sign-in spec passes, and PHPUnit passes;
- the grep lists only `docs/en/sign-in-providers.md`, `docs/fr/connexion-sso.md`, `UPGRADE.md`, `docs/superpowers/specs/2026-11-10-sso-design.md`, `docs/superpowers/plans/2026-11-10-sso.md`, this plan, its spec, and `binary/go.sum` (an unrelated module name; check that it is not about the portal);
- Prettier reports no change.

- [ ] **Step 5: Commit.**

```bash
git add -A docker .github/workflows/e2e.yml
git commit -m "test(sso): LemonLDAP::NG leaves the stack and CI, MockServer plays the identity provider"
```

- [ ] **Step 6: Report the CI check.** CI runs on push only. Tell the user that the branch is ready to push and that the e2e workflow's "Run the sign-in suite" step is the first run of the mock in CI. Do not push.
