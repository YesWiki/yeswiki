/* The trail of {{breadcrumb}}, kept per tab in sessionStorage rather than in a server session (replicas 15). */

const YW_BREADCRUMBS_KEY = 'yw-breadcrumbs'

const ywReadTrail = () => {
  try {
    const stored = JSON.parse(
      window.sessionStorage.getItem(YW_BREADCRUMBS_KEY) || '[]',
    )
    return Array.isArray(stored)
      ? stored.filter((tag) => typeof tag === 'string')
      : []
  } catch {
    return []
  }
}

const ywWriteTrail = (trail) => {
  try {
    window.sessionStorage.setItem(YW_BREADCRUMBS_KEY, JSON.stringify(trail))
    return true
  } catch {
    return false
  }
}

ywInitEach('[data-yw-breadcrumbs]', (list) => {
  const current = list.getAttribute('data-yw-breadcrumbs-current') || ''
  const max = Number(list.getAttribute('data-yw-breadcrumbs-max')) || 4
  const href = list.getAttribute('data-yw-breadcrumbs-href') || ''
  const active = list.querySelector('.yw-breadcrumb__active')
  if (!current || !active) return

  const trail = ywReadTrail().filter((tag) => tag !== current)
  trail.push(current)
  const kept = trail.slice(-max)
  ywWriteTrail(kept)

  kept.slice(0, -1).forEach((tag) => {
    const item = document.createElement('li')
    const link = document.createElement('a')
    link.href = href.replace('__YW_BREADCRUMB_TAG__', encodeURIComponent(tag))
    link.textContent = tag
    item.appendChild(link)
    list.insertBefore(item, active)
  })
})
