// The autocomplete is a combobox a keyboard drives on its own (end-users 21).
import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

test('arrows move through the suggestions and Enter picks the active one', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/menus&menu=MenuNavigation')
  const field = page.locator('.yw-menu-row__link').first()
  await field.focus()
  await field.fill('')
  await field.pressSequentially('Saisir')

  const list = page.locator('[role="listbox"].yw-suggestions')
  await expect(list).toBeVisible()
  await expect(field).toHaveAttribute('role', 'combobox')
  await expect(field).toHaveAttribute('aria-expanded', 'true')
  const options = list.locator('[role="option"]')
  expect(await options.count()).toBeGreaterThan(1)
  await expect(
    page.locator('[aria-live="polite"]', { hasText: /suggestion/ }).first(),
  ).toBeAttached()

  await field.press('ArrowDown')
  await field.press('ArrowDown')
  const second = (await options.nth(1).textContent()) ?? ''
  await expect(options.nth(1)).toHaveAttribute('aria-selected', 'true')
  await expect(field).toHaveAttribute(
    'aria-activedescendant',
    (await options.nth(1).getAttribute('id')) ?? '',
  )
  await expect(field).toBeFocused()

  await field.press('Enter')
  await expect(field).toHaveValue(second)
  await expect(list).toHaveCount(0)
  await expect(field).toHaveAttribute('aria-expanded', 'false')

  await field.press('ArrowUp')
  await expect(list).toBeVisible()
  await field.press('Escape')
  await expect(list).toHaveCount(0)
  await field.press('Escape')
  await expect(field).toHaveValue('')
})
