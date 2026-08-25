import { expect, test } from '@playwright/test'
import { setPageContent } from '../helpers/page'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

/**
 * Single-binary 06: the same request, many times, against one process.
 *
 * Every other spec issues a request once, and a leak like `nbactionmail` climbing, a `panel_shape`
 * stack handed on dirty, or `provenance` stuck on `import` is invisible to all of them. The bug is
 * in request two. Coherent-core 45 proved this against a simulated worker; these run against a
 * real one whenever YESWIKI_TEST_RUNTIME=binary, and against php-fpm otherwise -- where they still
 * hold, because a fresh process per request is the answer a worker has to match.
 *
 * Each leak is a named test on purpose: a failure has to say which one.
 */

/** How many times is enough. Two catches a counter; ten catches a stack that grows slowly. */
const TIMES = 10

/** Fetch a page as raw HTML, which is what a leak shows up in, without a render getting in the way. */
const fetchTimes = async (
  request: {
    get: (
      url: string,
    ) => Promise<{ text: () => Promise<string>; status: () => number }>
  },
  path: string,
  times = TIMES,
) => {
  const bodies: string[] = []
  for (let n = 0; n < times; n += 1) {
    const response = await request.get(path)
    expect(response.status(), `request ${n + 1} to ${path}`).toBeLessThan(400)
    bodies.push(await response.text())
  }

  return bodies
}

/** What changes between two renders for reasons that are not a leak: tokens, nonces, timings. */
const withoutTheExpectedNoise = (html: string) =>
  html
    .replace(/name="_?csrf[^"]*"\s+value="[^"]*"/gi, 'csrf')
    .replace(/nonce="[^"]*"/gi, 'nonce')
    .replace(/\?v=[0-9a-f.]+/gi, '?v')
    .replace(
      /[0-9]{4}-[0-9]{2}-[0-9]{2}[ T][0-9]{2}:[0-9]{2}:[0-9]{2}/g,
      'when',
    )

test.describe('a worker serves request two the way it served request one', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  })

  test('the contact form is the same form every time it is rendered', async ({
    page,
    request,
  }) => {
    await setPageContent(page, 'TestWorkerContact', '{{contact}}')

    const bodies = await fetchTimes(request, '/?TestWorkerContact')
    const counters = bodies.map(
      (html) => html.match(/name="nbactionmail"\s+value="([^"]*)"/)?.[1] ?? '',
    )

    // `nbactionmail` comes from MailFormCounter::next(), which is exactly the shape of state that
    // used to be a static and climbed for the life of the process.
    expect(
      new Set(counters).size,
      `the contact form's counter climbed across requests: ${counters.join(', ')}`,
    ).toBe(1)
    expect(counters[0], 'no contact form was rendered at all').not.toBe('')
  })

  test('an entry list gets the same DOM ids every time it is rendered', async ({
    page,
    request,
  }) => {
    await setPageContent(page, 'TestWorkerList', '{{entrylist}}')

    const bodies = await fetchTimes(request, '/?TestWorkerList')
    const ids = bodies.map((html) =>
      (html.match(/id="[^"]*"/g) ?? []).filter((id) => /\d/.test(id)).join(','),
    )

    expect(
      new Set(ids).size,
      'the entry list handed out different DOM ids on later requests, so an id counter outlived a request',
    ).toBe(1)
  })

  test('nested panels do not inherit the shape stack of the request before', async ({
    page,
    request,
  }) => {
    await setPageContent(
      page,
      'TestWorkerPanels',
      '{{panel title="outer"}}\n{{panel title="inner"}}\ncontent\n{{end elem="panel"}}\n{{end elem="panel"}}',
    )

    const bodies = await fetchTimes(request, '/?TestWorkerPanels')
    const shapes = bodies.map((html) => (html.match(/yw-panel/g) ?? []).length)

    expect(
      new Set(shapes).size,
      `the panel stack grew across requests: ${shapes.join(', ')}`,
    ).toBe(1)
    expect(shapes[0], 'no panel was rendered at all').toBeGreaterThan(0)
  })

  // The one the ticket asks for specifically: a render abandoned part-way must not leave the
  // stack it was building behind for the next visitor.
  test('a render abandoned mid-page leaves nothing behind for the next one', async ({
    page,
    request,
  }) => {
    await setPageContent(
      page,
      'TestWorkerAborted',
      '{{panel title="outer"}}\n{{panel title="inner"}}\ncontent\n{{end elem="panel"}}\n{{end elem="panel"}}',
    )

    const before = (await fetchTimes(request, '/?TestWorkerAborted', 1))[0]

    // Abort part-way through a render. Playwright's request context has no abort, so the page
    // navigates and is stopped: the server is mid-response when the connection goes.
    await page
      .goto('/?TestWorkerAborted', { waitUntil: 'commit' })
      .catch(() => {})
    await page.evaluate(() => window.stop()).catch(() => {})

    const after = (await fetchTimes(request, '/?TestWorkerAborted', 1))[0]

    expect(
      withoutTheExpectedNoise(after),
      'the request after an aborted render was served differently',
    ).toBe(withoutTheExpectedNoise(before))
  })

  test('an import does not leave provenance set for the save that follows it', async ({
    page,
    request,
  }) => {
    await setPageContent(page, 'TestWorkerProvenance', '{{entrylist}}')

    const before = await fetchTimes(request, '/?TestWorkerProvenance', 2)
    expect(
      withoutTheExpectedNoise(before[1]),
      'two ordinary requests already differ, so this test cannot say anything about imports',
    ).toBe(withoutTheExpectedNoise(before[0]))

    // Whatever an import sets, an ordinary request afterwards must not see it. The importer is
    // reached through its own route; a wiki without it skips rather than failing for the wrong
    // reason.
    const importResponse = await request.get('/?api/imports')
    if (importResponse.status() >= 400) {
      test.skip(true, 'this wiki has no importer route to dirty the state with')
    }

    const after = (await fetchTimes(request, '/?TestWorkerProvenance', 1))[0]
    expect(
      withoutTheExpectedNoise(after),
      'the request after an import was served differently, so provenance outlived the import',
    ).toBe(withoutTheExpectedNoise(before[0]))
  })

  test('two visitors with different Accept-Language do not get each other language', async ({
    request,
  }) => {
    const french = await request.get('/?PagePrincipale', {
      headers: { 'Accept-Language': 'fr-FR,fr;q=0.9' },
    })
    const spanish = await request.get('/?PagePrincipale', {
      headers: { 'Accept-Language': 'es-ES,es;q=0.9' },
    })
    const frenchAgain = await request.get('/?PagePrincipale', {
      headers: { 'Accept-Language': 'fr-FR,fr;q=0.9' },
    })

    const langOf = async (response: { text: () => Promise<string> }) =>
      (await response.text()).match(/<html[^>]*\blang="([^"]*)"/i)?.[1] ?? ''

    const first = await langOf(french)
    const second = await langOf(spanish)
    const third = await langOf(frenchAgain)

    expect(first, 'no lang attribute to compare').not.toBe('')
    expect(
      second,
      'the Spanish visitor was served the French visitor language',
    ).not.toBe(first)
    expect(
      third,
      'the French visitor was served the Spanish visitor language',
    ).toBe(first)
  })
})
