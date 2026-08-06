import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

// The flash, and only the flash: `.alert` also matches the actions builder's Vue hint box,
// and Playwright's strict mode fails on two matches -- which reads as a product bug.
const FLASH = '[role="alert"]'

/**
 * The wiki's chrome, as a screen (ticket 30).
 *
 * `PageTitre`, `PageMenuHaut` and `PageRapideHaut` are `layout_*` configuration now, and the
 * squelette renders that configuration on every page. So the assertion that matters is not
 * that the form saves -- phpunit can see the service -- but that what was typed into it comes
 * back as the navbar of an *ordinary* page. A screen that writes config nothing reads would
 * pass every unit test.
 */
test('what an admin types into Layout becomes the navbar of every page', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  // the rail names the section it belongs to, and this screen as the current one
  await expect(page.locator('.yw-dashboard__sidebar')).toContainText(
    'Apparence',
  )
  await expect(page.locator('.yw-dashboard__link--current')).toContainText(
    'Mise en page',
  )

  // an empty title means the wiki's own name -- said by the placeholder, not by a second
  // field repeating `yeswiki_name`
  const title = page.locator('#yw-layout-title')
  await expect(title).toHaveValue('')
  await expect(title).toHaveAttribute('placeholder', /\S/)
  await title.fill('Le wiki du collectif')

  // add an entry: the row is cloned from the <template>, and its field names are renumbered
  // to its position -- two rows sharing an index is one row silently replacing the other
  const navbar = page.locator('[data-yw-layout-rows="navbar"]')
  const before = await navbar.locator('[data-yw-layout-row]').count()
  await page.locator('[data-yw-layout-add="navbar"]').click()
  const rows = navbar.locator('[data-yw-layout-row]')
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

  // ...and now the part no unit test can see: an ordinary page wears it
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

  // the seeded navbar already has one: "Menu exemple", with no link of its own, and the four
  // example pages indented under it (InstallationController::defaultLayout())
  await expect(
    page
      .locator('[data-yw-layout-rows="navbar"] .yw-layout__row--child')
      .first(),
  ).toBeVisible()

  await page.goto('/?PagePrincipale')
  const dropdown = page.locator('.yw-topnav .yw-dropdown').first()
  await expect(dropdown).toBeVisible()
  await expect(dropdown.locator('.yw-dropdown__menu a').first()).toHaveCount(1)
})

/**
 * The logo is picked, never typed.
 *
 * There was a URL box here beside the button. It was the wrong offer -- what goes in it is an
 * `api/files/…/download` address nobody composes by hand -- and the button beside it did
 * nothing at all, because the template rendered it without loading the module that binds the
 * click. Silent: no error anywhere, the button just did not open.
 */
test('the logo button opens the file rail, and Remove clears it', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  // the value lives in a hidden field; there is no text box to type an address into
  await expect(page.locator('#yw-layout-logo')).toHaveAttribute(
    'type',
    'hidden',
  )
  await expect(
    page.locator('input[name="layout_logo"][type="text"]'),
  ).toHaveCount(0)

  // nothing chosen yet, so neither the preview nor Remove is a control
  await expect(page.locator('[data-yw-layout-logo-preview]')).toBeHidden()
  await expect(page.locator('[data-yw-layout-logo-remove]')).toBeHidden()

  // the assertion the missing module would have failed: the button opens the rail
  await page.locator('[data-yw-file-picker-field="yw-layout-logo"]').click()
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()

  // set a value the way the picker does, and watch the card follow the field
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

/**
 * The bar at the top of this page follows what you type, and nothing is written.
 *
 * A preview rather than live saving, deliberately: saving rewrites `yeswiki.config.php`,
 * which invalidates the compiled container — a cost paid by every visitor, not by the person
 * editing — and it would put a half-typed menu entry on the public site with no undo. Same
 * split the Preset screen draws between trying one on and making it the wiki's.
 */
test('the top bar previews what is being typed, and saves nothing until Save', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  const chrome = page.locator('#yw-layout-chrome')
  await expect(chrome).not.toContainText('Titre provisoire')

  await page.locator('#yw-layout-title').fill('Titre provisoire')
  // the real navbar of this very page, re-rendered from the form by the server
  await expect(chrome.locator('.navbar-brand')).toContainText(
    'Titre provisoire',
    { timeout: 10000 },
  )

  // the slider moves the custom property straight on <html>: no round trip, because a
  // laggy slider is a broken slider
  await page.locator('[data-yw-layout-height]').fill('96')
  await page.locator('[data-yw-layout-height]').dispatchEvent('input')
  await expect(page.locator('[data-yw-layout-height-value]')).toHaveText('96px')
  const applied = await page.evaluate(() =>
    getComputedStyle(document.documentElement)
      .getPropertyValue('--yw-navbar-height')
      .trim(),
  )
  expect(applied).toBe('96px')

  // ...and none of it was saved: another page still wears the wiki's real chrome
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
  // inline on <html>, which is what makes it win over any stylesheet without !important
  await expect(page.locator('html')).toHaveAttribute(
    'style',
    /--yw-navbar-height:\s*88px/,
  )

  // The bar itself, to within its 1px bottom border. This said `>= 88` first and passed over
  // a bar that was really 101px: the min-height sat on the inner container while the bar kept
  // its own padding, so the number meant nothing anyone could measure.
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

  // ...and everything that parks below the bar follows it. `--sticky-toolbar-top` used to be
  // a hard-coded 46px, so a taller bar simply covered the dashboard rail and the editor
  // toolbars. It is derived from this setting now.
  await page.goto('/?dashboard/activity')
  const railTop = await page.evaluate(() =>
    Math.round(
      document.querySelector('.yw-dashboard__sidebar').getBoundingClientRect()
        .top,
    ),
  )
  expect(railTop, 'the rail clears the bar instead of hiding under it').toBe(88)
})

/**
 * Leaving with unsaved changes asks first — and typing does not.
 *
 * Both halves matter. The guard is worth more here than on most screens, because the live
 * preview makes the page *look* as though a change has taken effect while nothing is written.
 * But the preview is itself an htmx POST of this very form, so a guard that treated every
 * htmx request as a departure would pop a confirm dialog on every keystroke — which is worse
 * than having no guard at all.
 */
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

  // typing fires the live preview, repeatedly. None of it is a departure.
  await page.locator('#yw-layout-title').fill('Un titre pas encore enregistré')
  await expect(page.locator('#yw-layout-chrome .navbar-brand')).toContainText(
    'Un titre pas encore enregistré',
    { timeout: 10000 },
  )
  expect(asked, 'the preview must not ask whether you want to leave').toBe(0)

  // ...but going somewhere else is. Internal links load through htmx, which never fires
  // beforeunload, so the guard has to catch this on `htmx:confirm` instead.
  await page
    .locator('.yw-dashboard__sidebar a[href*="admin/preset"]')
    .first()
    .click()
  await expect.poll(() => asked, { timeout: 10000 }).toBe(1)

  // dismissed, so the click was cancelled and the screen is still here with the change on it
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

test('the three chrome pages that are still pages are linked, not replaced', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  // PageHeader, PageMenu and PageFooter hold arbitrary wiki content, so this screen's job for
  // them is being the place you remember they exist.
  //
  // Scoped to the card. `a[href*="PageHeader"]` also matches the pencil now sitting on the
  // banner itself, and on a wiki whose banner is empty that pencil is inside a header the
  // theme collapses -- so the unscoped selector resolved to a hidden element and read as this
  // screen having lost its links.
  for (const tag of ['PageHeader', 'PageMenu', 'PageFooter']) {
    await expect(
      page.locator(`.yw-dashboard__links a[href*="${tag}"]`).first(),
    ).toBeVisible()
  }
})

/**
 * Moving a menu entry moves its submenu with it.
 *
 * The rows are a flat list, and a move used to swap a row with the one next to it -- so
 * moving a parent left its children where they were, and whichever entry landed above them
 * adopted them. The list still read as valid, nothing complained, and the menu came out
 * rearranged in a way nobody asked for.
 */
test('moving an entry takes its submenu with it', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  const rows = page.locator(
    '[data-yw-layout-rows="navbar"] [data-yw-layout-row]',
  )
  // the seeded navbar: a plain entry, then "Menu exemple" with its children indented under it
  const labels = () =>
    rows.evaluateAll((list) =>
      list.map((row) => ({
        label: row.querySelector('.yw-layout__label')?.value,
        child: row.classList.contains('yw-layout__row--child'),
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

  // send the parent up, over whatever group is above it
  await rows.nth(parentIndex).locator('[data-yw-layout-move="-1"]').click()

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

/**
 * The indent button points the way the row can actually go, and the link field suggests the
 * wiki's own pages rather than leaving the name to be remembered and typed.
 */
test('the indent button turns around, and links suggest pages', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/layout')

  const rows = page.locator(
    '[data-yw-layout-rows="navbar"] [data-yw-layout-row]',
  )
  const topLevel = rows.filter({
    hasNot: page.locator('.yw-layout__row--child'),
  })
  const second = rows.nth(1)
  const indent = second.locator('[data-yw-layout-indent]')
  const arrow = () => indent.locator('use').getAttribute('href')

  const wasChild = await second.evaluate((row) =>
    row.classList.contains('yw-layout__row--child'),
  )
  expect(await arrow()).toContain(wasChild ? '#arrow-left' : '#arrow-right')
  await indent.click()
  expect(
    await arrow(),
    'the arrow turns around: it shows the move that is now available',
  ).toContain(wasChild ? '#arrow-right' : '#arrow-left')

  // page suggestions on the link field, from the wiki's own pages
  const link = rows.first().locator('.yw-layout__link')
  await link.click()
  await link.fill('Page')
  const suggestions = rows.first().locator('.yw-suggestions')
  await expect(suggestions).toBeVisible()
  await expect(suggestions.locator('button').first()).toContainText(/Page/)

  // it follows the field, not the row. The shared dropdown is `width: 100%` of its positioned
  // ancestor, which here is the whole row -- it came out 893px over a 350px field, wider than
  // the row itself.
  const width = await rows.first().evaluate((row) => ({
    field: row.querySelector('.yw-layout__link').getBoundingClientRect().width,
    list: row.querySelector('.yw-suggestions').getBoundingClientRect().width,
  }))
  expect(width.list).toBeLessThan(width.field + 20)
  await suggestions.locator('button').first().click()
  await expect(link, 'picking a suggestion fills the field').toHaveValue(/Page/)
  await expect(topLevel.first()).toBeVisible()
})
