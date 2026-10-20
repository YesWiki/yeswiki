import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

/** A Mermaid block in a page is drawn as a diagram. */

const DIAGRAM = '<div class="mermaid">\ngraph TD;\n  Graine-->Arbre;\n</div>'

test('a page with a Mermaid block shows the diagram', async ({ page }) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))

  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const saved = await page.request.post('/?api/pages/DiagrammeMermaid', {
    form: { body: DIAGRAM },
  })
  expect(saved.ok()).toBeTruthy()

  await page.goto('/?DiagrammeMermaid')

  const diagram = page.locator('.mermaid svg')
  await expect(diagram).toBeVisible()
  await expect(diagram).toContainText('Graine')
  await expect(diagram).toContainText('Arbre')
  expect(errors).toEqual([])
})
