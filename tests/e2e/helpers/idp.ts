import { readFileSync } from 'fs'
import { mockExpect, mockPut } from './mockserver'

/**
 * The identity provider of the sign-in specs: MockServer's own OpenID Connect and SAML providers, and plain expectations for
 * CAS. There is no login form: a spec says who the provider signs in before the browser goes there, then the provider sends
 * the browser straight back to the wiki, signing what it sends with the keys of tests/fixtures/idp.
 *
 * auth.yeswiki.test is the mock's name for the wiki and the browser (docker/docker-compose.yml): it shares a site with
 * wiki.yeswiki.test, so SAML's POST callback carries the wiki's Lax cookie.
 */
export const IDP = 'http://auth.yeswiki.test'

/** The wiki as the identity provider knows it: CI serves it on a port, the development stack on the bare name. */
export const WIKI = (
  process.env.YESWIKI_BASE_URL || 'http://wiki.yeswiki.test'
).replace(/\/$/, '')

export type Person = {
  login: string
  firstName?: string
  lastName?: string
  email?: string
  groups?: string[]
}

export type RedirectType = 'oidc' | 'saml' | 'cas'

/** MockServer's ready-made faults: the provider signs in the person, but what it sends is wrong in this way. */
export type IdpFlag =
  | 'tamperedSignature'
  | 'issueExpiredToken'
  | 'wrongIssuer'
  | 'expiredAssertion'
  | 'wrongAudience'

export type IdpOptions = {
  /** The SAML provider's identifier in `sso_providers`, which its entity ID and callback are made of: `saml-a` by default. */
  providerId?: string
  flags?: IdpFlag[]
  /** The secret the OpenID Connect provider expects of the wiki, when it is not the one the wiki has. */
  clientSecret?: string
}

const key = (name: string): string =>
  readFileSync(new URL(`../../fixtures/idp/${name}`, import.meta.url), 'utf8')

/** The values that are set, so that a person without an email gets no email claim rather than an empty one. */
const defined = (
  values: Record<string, string | string[] | undefined>,
): Record<string, string | string[]> =>
  Object.fromEntries(
    Object.entries(values).filter(([, value]) => value !== undefined),
  ) as Record<string, string | string[]>

const flagged = (flags: IdpFlag[] = []): Record<string, boolean> =>
  Object.fromEntries(flags.map((flag) => [flag, true]))

/** The provider releases the directory's names, which PROVIDERS reads, and the standard ones, which converted loginsso providers read. */
export const oidcSignsIn = async (
  person: Person,
  options: IdpOptions = {},
): Promise<void> => {
  await mockPut('/mockserver/oidc', {
    issuer: IDP,
    clientId: 'wiki-oidc',
    clientSecret: options.clientSecret ?? 'wiki-oidc-secret',
    enforceClientAuthentication: true,
    subject: person.login,
    privateKeyPem: key('oidc.key'),
    certificatePem: key('oidc.crt'),
    keyId: 'yeswiki-test-oidc',
    scopes: ['openid', 'profile', 'email'],
    additionalClaims: defined({
      uid: person.login,
      mail: person.email,
      email: person.email,
      givenName: person.firstName,
      given_name: person.firstName,
      sn: person.lastName,
      family_name: person.lastName,
      groups: person.groups,
    }),
    ...flagged(options.flags),
  })
}

/** The SAML provider in charge, for one service provider of the wiki at a time: its entity ID and callback are fixed here. */
export const samlSignsIn = async (
  person: Person,
  options: IdpOptions = {},
): Promise<void> => {
  const id = options.providerId ?? 'saml-a'
  await mockPut('/mockserver/saml', {
    idpEntityId: `${IDP}/saml/metadata`,
    spEntityId: `${WIKI}/?user/sign-in/${id}/metadata`,
    assertionConsumerServiceUrl: `${WIKI}/?user/sign-in/${id}/callback`,
    subjectNameId: person.login,
    nameIdFormat: 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
    signingCertificatePem: key('saml.crt'),
    signingPrivateKeyPem: key('saml.key'),
    // MockServer's SAML attributes take one value each: only the first group is sent
    attributes: defined({
      uid: person.login,
      mail: person.email,
      givenName: person.firstName,
      sn: person.lastName,
      groups: person.groups?.[0],
    }),
    ...flagged(options.flags),
  })
}

const xml = (text: string): string =>
  text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')

const CAS = 'http://www.yale.edu/tp/cas'

/** Where a CAS client validates a ticket: /serviceValidate for CAS 2.0, /p3/serviceValidate for 3.0. */
export const CAS_VALIDATE = '/cas/(p3/)?serviceValidate'

/** What a CAS server answers to a ticket it does not know. */
export const CAS_UNKNOWN_TICKET = `<cas:serviceResponse xmlns:cas="${CAS}"><cas:authenticationFailure code="INVALID_TICKET">unknown ticket</cas:authenticationFailure></cas:serviceResponse>`

/**
 * A CAS server signing the person in: the login page sends the browser back to the service with a ticket, and the validation
 * of that ticket names the person. It also releases `name`, the user ID of the logincas servers Doryphore spoke to.
 */
export const casSignsIn = async (person: Person): Promise<void> => {
  const ticket = `ST-${person.login}`
  const attributes = Object.entries(
    defined({
      uid: person.login,
      name: person.login,
      mail: person.email,
      givenName: person.firstName,
      sn: person.lastName,
      groups: person.groups,
    }),
  )
    .flatMap(([name, value]) =>
      (Array.isArray(value) ? value : [value]).map(
        (one) => `<cas:${name}>${xml(one)}</cas:${name}>`,
      ),
    )
    .join('')
  await mockExpect(
    {
      id: 'cas.login',
      httpRequest: { method: 'GET', path: '/cas/login' },
      httpResponseTemplate: {
        templateType: 'VELOCITY',
        // the wiki's service address already has a query (?user/sign-in/cas/callback): the ticket follows it with &
        template: `#set($s = $request.queryStringParameters.service[0])#set($sep = "?")#if($s.contains("?"))#set($sep = "&")#end{"statusCode":302,"headers":{"Location":["$s\${sep}ticket=${ticket}"]}}`,
      },
    },
    {
      id: 'cas.validate',
      httpRequest: {
        method: 'GET',
        path: CAS_VALIDATE,
        queryStringParameters: { ticket: [ticket] },
      },
      httpResponse: {
        statusCode: 200,
        headers: { 'content-type': ['application/xml'] },
        body: `<cas:serviceResponse xmlns:cas="${CAS}"><cas:authenticationSuccess><cas:user>${xml(person.login)}</cas:user><cas:attributes>${attributes}</cas:attributes></cas:authenticationSuccess></cas:serviceResponse>`,
      },
    },
    {
      id: 'cas.unknown-ticket',
      priority: -1,
      httpRequest: { method: 'GET', path: CAS_VALIDATE },
      httpResponse: {
        statusCode: 200,
        headers: { 'content-type': ['application/xml'] },
        body: CAS_UNKNOWN_TICKET,
      },
    },
  )
}

/** Tells the identity provider of this type to sign `person` in, the next time the wiki sends the browser to it. */
export const idpSignsIn = (
  type: RedirectType,
  person: Person,
  options: IdpOptions = {},
): Promise<void> =>
  type === 'oidc'
    ? oidcSignsIn(person, options)
    : type === 'saml'
      ? samlSignsIn(person, options)
      : casSignsIn(person)
