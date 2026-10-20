import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { seedResources } from '../helpers/bazar'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

/** Cards of entries: where their button sits, and where it opens the entry. */

const showCards = async (page: Page, tag: string, action: string) => {
  await seedResources(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const saved = await page.request.post(`/?api/pages/${tag}`, {
    form: { body: action },
  })
  expect(saved.ok()).toBeTruthy()
  await page.goto(`/?${tag}`)
}

test('the button sits under the title, at the foot of every card', async ({
  page,
}) => {
  await showCards(
    page,
    'CartesBoutons',
    '{{entrylist id="4" template="card" displayfields="subtitle=bf_url,cta=entry"}}',
  )

  const cards = page.locator('.yw-item--card')
  await expect(cards).toHaveCount(2)
  const gaps = []
  for (const card of await cards.all()) {
    const box = (await card.boundingBox())!
    const title = (await card.locator('.yw-item__title').boundingBox())!
    const button = (await card.locator('.yw-item__cta').boundingBox())!
    expect(button.y).toBeGreaterThanOrEqual(title.y + title.height)
    gaps.push(Math.round(box.y + box.height - (button.y + button.height)))
  }
  expect(gaps[0]).toBe(gaps[1])
})

test('a list told to use a pop-up opens the entry in one', async ({ page }) => {
  await showCards(
    page,
    'CartesPopup',
    '{{entrylist id="4" template="card" displayfields="cta=entry" entrydisplay="modal"}}',
  )

  await page
    .locator('.yw-item--card', { hasText: 'Framasoft' })
    .locator('.yw-item__cta')
    .click()

  const modal = page.locator('#yw-modal-remote')
  await expect(modal).toHaveClass(/yw-modal--open/)
  await expect(modal.locator('.yw-modal__title')).toHaveText('Framasoft')
  await expect(modal.locator('.yw-modal__body')).toContainText('framasoft.org')
  await expect(page).toHaveURL(/CartesPopup/)
})
