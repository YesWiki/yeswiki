import { expect, Page } from '@playwright/test'

/** Saves an override the way the templates screen's own form does, at any path, so no test writes a file a replica would not see (ticket 18). */
export const saveOverride = async (
  page: Page,
  path: string,
  source: string,
) => {
  await page.goto('/?admin/custom-templates')
  const token = await page
    .locator('input[name="csrf-token"]')
    .first()
    .inputValue()
  const res = await page.request.post('/?admin/custom-templates', {
    form: { 'csrf-token': token, file: path, contents: source },
  })
  expect(res.ok()).toBeTruthy()
}

/** What the wiki holds for an override, as the templates screen opens it; '' when there is none. */
export const readOverride = async (
  page: Page,
  path: string,
): Promise<string> => {
  await page.goto(`/?admin/custom-templates&file=${encodeURIComponent(path)}`)
  const source = page.locator('#yw-template-source')

  return (await source.count()) === 0 ? '' : source.inputValue()
}
