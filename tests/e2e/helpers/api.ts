import { APIRequestContext, APIResponse, expect } from '@playwright/test'

export type ApiParam = {
  name: string
  optional: boolean
  default: string | number | boolean | null
  pattern: string | null
}

export type ApiRoute = {
  path: string
  url: string
  methods: string[]
  acl: string
  params: ApiParam[]
  order: number
}

/** One route as the sweep calls it: a single method, a concrete URL. */
export type ApiProbe = {
  method: string
  path: string
  url: string
  acl: string[]
  label: string
  /** An earlier route answers this URL too, so nothing here can be pinned on `path`. */
  shadowed: boolean
}

const PROBE = 'PlaywrightApiProbe'

/** The body YesWikiRuntime::accessRefused() sends an unauthorised `/api` caller. */
const GATE_REFUSAL = 'Not enough access rights'

const anchored = (pattern: string): RegExp | null => {
  try {
    return new RegExp(`^(?:${pattern})$`)
  } catch {
    return null
  }
}

/** A value the router accepts for `param`, so the request reaches the ACL gate. */
const valueFor = (param: ApiParam): string => {
  if (param.pattern === null) {
    return PROBE
  }

  const matcher = anchored(param.pattern)

  return matcher === null
    ? PROBE
    : ([PROBE, `${PROBE}.zip`, '1', '0', 'a'].find((c) => matcher.test(c)) ??
        PROBE)
}

const placeholders = (path: string) => path.replace(/\{!(\w+)\}/g, '{$1}')

const fill = (route: ApiRoute): string =>
  route.params.reduce(
    (path, param) => path.replace(`{${param.name}}`, valueFor(param)),
    placeholders(route.path),
  )

/** The route's path as Symfony matches it, so overlapping routes can be spotted. */
const shape = (route: ApiRoute): RegExp => {
  const source = (param: ApiParam) =>
    `(?:${param.pattern !== null ? param.pattern : '[^/]+'})`

  const params = [...route.params]
  const optional: ApiParam[] = []
  let path = placeholders(route.path)

  while (params.length > 0) {
    const last = params[params.length - 1]
    const segment = `/{${last.name}}`
    if (!last.optional || !path.endsWith(segment)) {
      break
    }
    optional.unshift(last)
    params.pop()
    path = path.slice(0, -segment.length)
  }

  const required = path
    .split(/(\{\w+\})/)
    .map((part) => {
      const name = part.match(/^\{(\w+)\}$/)?.[1]
      const param = params.find((p) => p.name === name)

      return param === undefined
        ? part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
        : source(param)
    })
    .join('')

  const tail = optional.reduceRight(
    (inner, param) => `(?:/${source(param)}${inner})?`,
    '',
  )

  return new RegExp(`^${required}${tail}$`)
}

/** What `/api` says about itself, one entry per (route, method) pair. */
export const apiProbes = async (
  request: APIRequestContext,
): Promise<ApiProbe[]> => {
  const response = await request.get('/?api&output=json')
  expect(response.ok()).toBeTruthy()

  const { routes } = (await response.json()) as { routes: ApiRoute[] }
  expect(routes.length).toBeGreaterThan(0)

  const shapes = routes.map((route) => ({ route, shape: shape(route) }))

  return routes.flatMap((route) => {
    const path = fill(route)

    return route.methods.map((method) => ({
      method,
      path: route.path,
      url: `/?${path.replace(/^\//, '')}`,
      acl: route.acl === '' ? [] : route.acl.split(', '),
      label: `${method} ${route.path}`,
      shadowed: shapes.some(
        (other) =>
          other.route.order < route.order &&
          other.shape.test(path) &&
          other.route.methods.includes(method),
      ),
    }))
  })
}

/** A route anyone may call. The rest answer a signed-out caller through the gate. */
export const isPublic = (probe: ApiProbe): boolean =>
  probe.acl.includes('public')

export const send = (request: APIRequestContext, probe: ApiProbe) =>
  request.fetch(probe.url, { method: probe.method, failOnStatusCode: false })

/** Refused by the ACL gate, as opposed to by the controller's own checks. */
export const refusedByGate = async (response: APIResponse): Promise<boolean> =>
  response.status() === 401 && (await response.text()) === GATE_REFUSAL
