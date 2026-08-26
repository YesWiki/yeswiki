import { expect, test } from '@playwright/test'
import {
  ApiProbe,
  apiProbes,
  isPublic,
  refusedByGate,
  send,
} from '../helpers/api'
import { ADMIN_PASSWORD, ADMIN_USERNAME, loginRequest } from '../helpers/login'

/** Every `/api` route, taken from what `/api` says about itself. */
test.describe.configure({ timeout: 180_000 })

const gets = (probes: ApiProbe[]) => probes.filter((p) => p.method === 'GET')

const attributable = (probes: ApiProbe[]) => probes.filter((p) => !p.shadowed)

const noneOf = (failures: string[]) => expect(failures.sort()).toEqual([])

test('no two routes answer the same request', async ({ request }) => {
  const probes = await apiProbes(request)

  noneOf(probes.filter((p) => p.shadowed).map((p) => p.label))
})

test('the gate refuses a signed-out caller every route that is not public', async ({
  request,
}) => {
  const probes = attributable(await apiProbes(request)).filter(
    (p) => !isPublic(p),
  )
  expect(probes.length).toBeGreaterThan(30)

  const reached: string[] = []
  for (const probe of probes) {
    const response = await send(request, probe)
    if (!(await refusedByGate(response))) {
      reached.push(`${probe.label} -> ${response.status()}`)
    }
  }

  noneOf(reached)
})

test('the gate refuses a route that declares nobody, admin included', async ({
  request,
}) => {
  const probes = attributable(await apiProbes(request)).filter(
    (p) => p.acl.length === 0,
  )
  test.skip(probes.length === 0, 'every route declares an ACL')

  await loginRequest(request, ADMIN_USERNAME, ADMIN_PASSWORD)

  const reached: string[] = []
  for (const probe of probes) {
    const response = await send(request, probe)
    if (!(await refusedByGate(response))) {
      reached.push(`${probe.label} -> ${response.status()}`)
    }
  }

  noneOf(reached)
})

test('the gate lets an admin through to every route that declares an ACL', async ({
  request,
}) => {
  const probes = gets(attributable(await apiProbes(request))).filter(
    (p) => p.acl.length > 0,
  )
  await loginRequest(request, ADMIN_USERNAME, ADMIN_PASSWORD)

  const refused: string[] = []
  for (const probe of probes) {
    const response = await send(request, probe)
    if (await refusedByGate(response)) {
      refused.push(probe.label)
    }
  }

  noneOf(refused)
})

test('no route answers a signed-out caller with a server error', async ({
  request,
}) => {
  const probes = gets(attributable(await apiProbes(request)))

  const broken: string[] = []
  for (const probe of probes) {
    const response = await send(request, probe)
    if (response.status() >= 500) {
      broken.push(`${probe.label} -> ${response.status()}`)
    }
  }

  noneOf(broken)
})

test('no route answers an admin with a server error', async ({ request }) => {
  const probes = gets(attributable(await apiProbes(request)))
  await loginRequest(request, ADMIN_USERNAME, ADMIN_PASSWORD)

  const broken: string[] = []
  for (const probe of probes) {
    const response = await send(request, probe)
    if (response.status() >= 500) {
      broken.push(`${probe.label} -> ${response.status()}`)
    }
  }

  noneOf(broken)
})
