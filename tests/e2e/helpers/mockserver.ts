/**
 * MockServer (`yeswiki-mock` in docker/docker-compose.yml), for any spec that needs an HTTP service to answer as it says:
 * the spec tells it what to answer through its REST API, then the wiki under test calls it.
 *
 * Every spec shares the one mock. That holds because the suite runs one test at a time (`workers: 1` and
 * `fullyParallel: false` in playwright.config.ts): a spec that configures the mock resets it before each test.
 */
export const MOCKSERVER = (
  process.env.YESWIKI_MOCKSERVER_URL || 'http://yeswiki-mock'
).replace(/\/$/, '')

/** An expectation as MockServer's REST API takes it: what request it matches, and what it answers. */
export type Expectation = Record<string, unknown>

const put = async (path: string, body?: unknown): Promise<void> => {
  const response = await fetch(`${MOCKSERVER}${path}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  if (!response.ok) {
    throw new Error(
      `MockServer refused ${path}: ${response.status} ${await response.text()}`,
    )
  }
}

/** Waits until the mock answers, for at most 10 seconds. */
export const mockReady = async (): Promise<void> => {
  const until = Date.now() + 10_000
  for (;;) {
    try {
      await put('/mockserver/status')

      return
    } catch (error) {
      if (Date.now() > until) {
        throw new Error(
          `MockServer is not reachable at ${MOCKSERVER}: start it with \`docker compose up -d yeswiki-mock\` (${error})`,
        )
      }
      await new Promise((resolve) => setTimeout(resolve, 500))
    }
  }
}

/** Forgets every expectation and every request received. */
export const mockReset = (): Promise<void> => put('/mockserver/reset')

export const mockExpect = (...expectations: Expectation[]): Promise<void> =>
  put('/mockserver/expectation', expectations)

/** Forgets the expectation with this ID. */
export const mockClear = (id: string): Promise<void> =>
  put('/mockserver/clear?type=EXPECTATIONS', { id })

/** Configures one of MockServer's ready-made services, like `/mockserver/oidc` or `/mockserver/saml`. */
export const mockPut = (path: string, body: object): Promise<void> =>
  put(path, body)
