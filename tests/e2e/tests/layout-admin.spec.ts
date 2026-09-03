import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { editorReady } from '../helpers/editor'

test.beforeEach(async () => {
  resetEnv()
})

const FLASH = '[role="alert"]'

/** The wiki's chrome, as a screen (ticket 30). */
test('what an admin types into Layout becomes the navbar of every page', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  await expect(page.locator('.yw-dashboard__sidebar')).toContainText(
    'Apparence',
  )
  await expect(page.locator('.yw-dashboard__link--current')).toContainText(
    'Mise en page',
  )

  const title = page.locator('#yw-layout-title')
  await expect(title).toHaveValue('')
  await expect(title).toHaveAttribute('placeholder', /\S/)
  await title.fill('Le wiki du collectif')

  const navbar = page.locator('[data-yw-menu-rows="navbar"]')
  const before = await navbar.locator('[data-yw-menu-row]').count()
  await page.locator('[data-yw-menu-add="navbar"]').click()
  const rows = navbar.locator('[data-yw-menu-row]')
  await expect(rows).toHaveCount(before + 1)

  const added = rows.last()
  await added.locator('input[name$="[label]"]').fill('Le forum')
  await added.locator('input[name$="[link]"]').fill('https://forum.example')
  await expect(added.locator('input[name$="[label]"]')).toHaveAttribute(
    'name',
    `navbar[${before}][label]`,
  )

  await page.locator('.yw-layout__save button[type="submit"]').click()
  await expect(page.locator(FLASH)).toContainText(/enregistr/i)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('.navbar-brand').first()).toContainText(
    'Le wiki du collectif',
  )
  await expect(page.locator('.yw-topnav')).toContainText('Le forum')
  await expect(
    page.locator('.yw-topnav a[href="https://forum.example"]'),
  ).toBeVisible()
})

test('a dropdown is an entry with children under it', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  await expect(
    page.locator('[data-yw-menu-rows="navbar"] .yw-menu-row--child').first(),
  ).toBeVisible()

  await page.goto('/?PagePrincipale')
  const dropdown = page.locator('.yw-topnav .yw-dropdown').first()
  await expect(dropdown).toBeVisible()
  await expect(dropdown.locator('.yw-dropdown__menu a').first()).toHaveCount(1)
})

/** The logo is picked, never typed. */
test('the logo button opens the file rail, and Remove clears it', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  await expect(page.locator('#yw-layout-logo')).toHaveAttribute(
    'type',
    'hidden',
  )
  await expect(
    page.locator('input[name="layout_logo"][type="text"]'),
  ).toHaveCount(0)

  await expect(page.locator('[data-yw-layout-logo-preview]')).toBeHidden()
  await expect(page.locator('[data-yw-layout-logo-remove]')).toBeHidden()

  await page.locator('[data-yw-file-picker-field="yw-layout-logo"]').click()
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()

  await page.evaluate(() => {
    const field = document.getElementById('yw-layout-logo') as HTMLInputElement
    field.value = 'files/probe-logo.png'
    field.dispatchEvent(new Event('change', { bubbles: true }))
  })
  await expect(page.locator('[data-yw-layout-logo-preview]')).toBeVisible()
  await expect(page.locator('[data-yw-layout-logo-remove]')).toBeVisible()

  await page.locator('[data-yw-layout-logo-remove]').click()
  await expect(page.locator('#yw-layout-logo')).toHaveValue('')
  await expect(page.locator('[data-yw-layout-logo-preview]')).toBeHidden()
  await expect(page.locator('[data-yw-layout-logo-remove]')).toBeHidden()
})

/** The bar at the top of this page follows what you type, and nothing is written. */
test('the top bar previews what is being typed, and saves nothing until Save', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  const chrome = page.locator('#yw-layout-chrome')
  await expect(chrome).not.toContainText('Titre provisoire')

  await page.locator('#yw-layout-title').fill('Titre provisoire')
  await expect(chrome.locator('.navbar-brand')).toContainText(
    'Titre provisoire',
    { timeout: 10000 },
  )

  await page.locator('[data-yw-layout-height]').fill('96')
  await page.locator('[data-yw-layout-height]').dispatchEvent('input')
  await expect(page.locator('[data-yw-layout-height-value]')).toHaveText('96px')
  const applied = await page.evaluate(() =>
    getComputedStyle(document.documentElement)
      .getPropertyValue('--yw-navbar-height')
      .trim(),
  )
  expect(applied).toBe('96px')

  await page.goto('/?PagePrincipale')
  await expect(
    page.locator('#yw-layout-chrome .navbar-brand'),
  ).not.toContainText('Titre provisoire')
  const saved = await page.evaluate(() =>
    document.documentElement.style
      .getPropertyValue('--yw-navbar-height')
      .trim(),
  )
  expect(saved, 'the height is the saved one, not the one dragged to').not.toBe(
    '96px',
  )
})

test('the bar height is saved, and beats what a stylesheet says', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  await page.locator('[data-yw-layout-height]').fill('88')
  await page.locator('[data-yw-layout-height]').dispatchEvent('change')
  await page.locator('.yw-layout__save button[type="submit"]').click()
  await expect(page.locator(FLASH)).toContainText(/enregistr/i)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('html')).toHaveAttribute(
    'style',
    /--yw-navbar-height:\s*88px/,
  )

  const barHeight = await page.evaluate(() =>
    Math.round(
      document.querySelector('#yw-topnav').getBoundingClientRect().height,
    ),
  )
  expect(
    barHeight,
    'the number on the slider is the height of the bar',
  ).toBeLessThanOrEqual(90)
  expect(
    barHeight,
    'the number on the slider is the height of the bar',
  ).toBeGreaterThanOrEqual(88)

  await page.goto('/?dashboard')
  const railTop = await page.evaluate(() =>
    Math.round(
      document.querySelector('.yw-dashboard__sidebar').getBoundingClientRect()
        .top,
    ),
  )
  expect(railTop, 'the rail clears the bar instead of hiding under it').toBe(88)
})

/** Leaving with unsaved changes asks first — and typing does not. */
test('leaving with unsaved changes asks, and typing does not', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  let asked = 0
  page.on('dialog', (dialog) => {
    asked += 1
    dialog.dismiss()
  })

  await page.locator('#yw-layout-title').fill('Un titre pas encore enregistré')
  await expect(page.locator('#yw-layout-chrome .navbar-brand')).toContainText(
    'Un titre pas encore enregistré',
    { timeout: 10000 },
  )
  expect(asked, 'the preview must not ask whether you want to leave').toBe(0)

  await page
    .locator('.yw-dashboard__sidebar a[href*="admin/preset"]')
    .first()
    .click()
  await expect.poll(() => asked, { timeout: 10000 }).toBe(1)

  await expect(page).toHaveURL(/admin\/layout/)
  await expect(page.locator('#yw-layout-title')).toHaveValue(
    'Un titre pas encore enregistré',
  )
})

test('saving clears the guard, so leaving afterwards asks nothing', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  let asked = 0
  page.on('dialog', (dialog) => {
    asked += 1
    dialog.accept()
  })

  await page.locator('#yw-layout-title').fill('Titre enregistré')
  await page.locator('.yw-layout__save button[type="submit"]').click()
  await expect(page.locator(FLASH)).toContainText(/enregistr/i)

  await page
    .locator('.yw-dashboard__sidebar a[href*="admin/preset"]')
    .first()
    .click()
  await expect(page).toHaveURL(/admin\/preset/)
  expect(asked, 'nothing is unsaved once it is saved').toBe(0)
})

/** The two chrome pages left are written here, and nothing links out to a third. */
test('the banner and the footer are the whole of the chrome pages', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  for (const tag of ['PageHeader', 'PageFooter']) {
    await expect(page.locator(`input[name="tag"][value="${tag}"]`)).toHaveCount(
      1,
    )
    await editorReady(page, `content_${tag}`)
  }

  await expect(page.locator('input[name="tag"]')).toHaveCount(2)
  await expect(
    page.locator('a[href*="PageMenu"]'),
    'the side menu is retired: nothing on this screen offers it',
  ).toHaveCount(0)
})

/** What is typed into the banner here is what every page shows. */
test('the banner written on the Layout screen lands on every page', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  await editorReady(page, 'content_PageHeader')
  await page.evaluate(() => {
    window['ywEditors']['content_PageHeader'].setValue('=== Le bandeau ===')
  })
  await page
    .locator(
      'form:has(input[name="tag"][value="PageHeader"]) button[type="submit"]',
    )
    .click()
  await expect(page.locator(FLASH)).toContainText(/enregistr/i)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('#yw-header')).toContainText('Le bandeau')
})

/** Two structural choices: which side the menu is on, and which side of it the banner is. */
test('the menu can become a side column, and the banner can lead it', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  await editorReady(page, 'content_PageHeader')
  await page.evaluate(() => {
    window['ywEditors']['content_PageHeader'].setValue('=== Le bandeau ===')
  })
  await page
    .locator(
      'form:has(input[name="tag"][value="PageHeader"]) button[type="submit"]',
    )
    .click()
  await expect(page.locator(FLASH)).toContainText(/enregistr/i)

  await page
    .locator('input[name="layout_navbar_position"][value="left"]')
    .check()
  await expect(page.locator('html')).toHaveAttribute('data-yw-navbar', 'left')

  await page
    .locator('input[name="layout_header_position"][value="before"]')
    .check()
  await page.locator('.yw-layout__save button[type="submit"]').click()
  await expect(page.locator(FLASH)).toContainText(/enregistr/i)

  await page.setViewportSize({ width: 1280, height: 800 })
  await page.goto('/?PagePrincipale')

  await expect(page.locator('html')).toHaveAttribute('data-yw-navbar', 'left')

  const column = await page.locator('#yw-topnav').boundingBox()
  expect(
    Math.round(column.x),
    'the menu is a column against the left edge',
  ).toBe(0)
  expect(column.width, 'and it is a column, not a bar').toBeLessThan(400)

  const bannerFirst = await page.evaluate(
    () =>
      !!(
        document
          .querySelector('#yw-header')
          .compareDocumentPosition(document.querySelector('#yw-topnav')) &
        Node.DOCUMENT_POSITION_FOLLOWING
      ),
  )
  expect(bannerFirst, 'the banner comes before the menu').toBe(true)
})

/** The pencil on the navbar covers nothing on the navbar. */
test('the chrome pencils cover no control an admin needs', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?PagePrincipale')

  await page.locator('#yw-topnav').hover()
  await expect(page.locator('#yw-topnav .yw-chrome-edit')).toBeVisible()

  const covered = await page.evaluate(() => {
    const pencil = document.querySelector('#yw-topnav .yw-chrome-edit')
    const hits = (selector: string) => {
      const element = document.querySelector(selector)
      if (!element) return null
      const box = element.getBoundingClientRect()
      const at = document.elementFromPoint(
        box.left + box.width / 2,
        box.top + box.height / 2,
      )
      return at && pencil?.contains(at) ? selector : null
    }
    return [
      hits('.yw-topnav-tools'),
      hits('.yw-topnav-fast-access .yw-avatar'),
      hits('.yw-page-actions'),
    ].filter(Boolean)
  })

  expect(covered, 'the navbar pencil is sitting on top of these').toEqual([])

  await expect(
    page.locator('#yw-header .yw-chrome-edit'),
    'the banner is edited on the Layout screen, not through a pencil',
  ).toHaveCount(0)

  const edges = await page.evaluate(() => ({
    pencil: document
      .querySelector('#yw-topnav .yw-chrome-edit')
      .getBoundingClientRect().right,
    cluster: document.querySelector('.yw-page-actions').getBoundingClientRect()
      .right,
  }))
  expect(
    Math.round(edges.pencil),
    'the pencil hangs on the right, on the cluster line',
  ).toBe(Math.round(edges.cluster))

  await page.locator('#yw-topnav .yw-chrome-edit').click()
  await expect(page).toHaveURL(/admin\/layout/)
})

/** Moving a menu entry moves its submenu with it. */
test('moving an entry takes its submenu with it', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  const rows = page.locator('[data-yw-menu-rows="navbar"] [data-yw-menu-row]')
  const labels = () =>
    rows.evaluateAll((list) =>
      list.map((row) => ({
        label: row.querySelector('.yw-menu-row__label')?.value,
        child: row.classList.contains('yw-menu-row--child'),
      })),
    )

  const before = await labels()
  const parentIndex = before.findIndex(
    (row, index) => !row.child && before[index + 1]?.child,
  )
  expect(
    parentIndex,
    'the seeded navbar must have a submenu to move',
  ).toBeGreaterThan(-1)
  const children = before
    .slice(parentIndex + 1)
    .filter((row, index, all) => all.slice(0, index + 1).every((r) => r.child))
    .map((row) => row.label)
  expect(children.length).toBeGreaterThan(0)

  await rows.nth(parentIndex).locator('[data-yw-menu-move="-1"]').click()

  const after = await labels()
  const movedTo = after.findIndex(
    (row) => row.label === before[parentIndex].label,
  )
  expect(movedTo, 'the parent moved up').toBeLessThan(parentIndex)
  expect(
    after
      .slice(movedTo + 1, movedTo + 1 + children.length)
      .map((row) => row.label),
    'its submenu came along, in order',
  ).toEqual(children)
  expect(
    after
      .slice(movedTo + 1, movedTo + 1 + children.length)
      .every((row) => row.child),
    'and they are still submenu entries',
  ).toBe(true)
})

/** The indent button points the way the row can actually go, and the link field suggests the wiki's own pages rather than leaving the name to be remembered and typed. */
test('the indent button turns around, and links suggest pages', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  const rows = page.locator('[data-yw-menu-rows="navbar"] [data-yw-menu-row]')
  const topLevel = rows.filter({
    hasNot: page.locator('.yw-menu-row--child'),
  })
  const second = rows.nth(1)
  const indent = second.locator('[data-yw-menu-indent]')
  const arrow = () => indent.locator('use').getAttribute('href')

  const wasChild = await second.evaluate((row) =>
    row.classList.contains('yw-menu-row--child'),
  )
  expect(await arrow()).toContain(wasChild ? '#arrow-left' : '#arrow-right')
  await indent.click()
  expect(
    await arrow(),
    'the arrow turns around: it shows the move that is now available',
  ).toContain(wasChild ? '#arrow-right' : '#arrow-left')

  const link = rows.first().locator('.yw-menu-row__link')
  await link.click()
  await link.fill('Page')
  const suggestions = rows.first().locator('.yw-suggestions')
  await expect(suggestions).toBeVisible()
  await expect(suggestions.locator('button').first()).toContainText(/Page/)

  const width = await rows.first().evaluate((row) => ({
    field: row.querySelector('.yw-menu-row__link').getBoundingClientRect()
      .width,
    list: row.querySelector('.yw-suggestions').getBoundingClientRect().width,
  }))
  expect(width.list).toBeLessThan(width.field + 20)
  await suggestions.locator('button').first().click()
  await expect(link, 'picking a suggestion fills the field').toHaveValue(/Page/)
  await expect(topLevel.first()).toBeVisible()
})
