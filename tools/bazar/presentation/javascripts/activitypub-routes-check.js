/** Whether a url answers with the JSON document the wiki serves there, proving the web server routes it to the wiki. */
async function servesWikiJson(url, accept, isExpected) {
  try {
    const response = await fetch(url, {
      headers: { Accept: accept },
      credentials: 'omit',
    })
    return response.ok && isExpected(await response.json())
  } catch {
    return false
  }
}

/** Shows the warning when the web server does not pass WebFinger or the actor on to the wiki. */
async function checkRoutes(alert) {
  const { webfinger, actor } = alert.dataset
  if (new URL(actor).origin !== window.location.origin) return
  const results = {
    webfinger: await servesWikiJson(
      webfinger,
      'application/jrd+json, application/json',
      (body) => typeof body?.subject === 'string',
    ),
    actor: await servesWikiJson(
      actor,
      'application/activity+json',
      (body) => body?.id === actor,
    ),
  }
  Object.entries(results).forEach(([route, works]) => {
    alert.querySelector(`[data-route="${route}"]`).hidden = works
  })
  alert.hidden = results.webfinger && results.actor
}

document.querySelectorAll('.activitypub-routes-check').forEach(checkRoutes)
