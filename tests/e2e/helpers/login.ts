import { APIRequestContext, expect, Page } from '@playwright/test'

export const ADMIN_USERNAME = 'WikiAdmin'
export const ADMIN_PASSWORD = 'WikiAdminPassword'

/** Sign in over the API, for specs that never open a browser. */
export const loginRequest = async (
  request: APIRequestContext,
  username: string,
  password: string,
) => {
  const res = await request.post('/?api/login', {
    form: {
      username,
      password,
    },
  })
  expect(res.ok()).toBeTruthy()
}

export const login = async (page: Page, username: string, password: string) => {
  await loginRequest(page.request, username, password)
}

export const logout = async (page: Page) => {
  await page.context().clearCookies()
}
