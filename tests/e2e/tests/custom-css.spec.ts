import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

/**
 * The wiki's own stylesheet, as a screen (ticket 30).
 *
 * Everything here is invisible to phpunit: that the route renders, that the form saves, and
 * above all that what was typed comes back as a `<link>` on an ordinary page. The last one
 * is the whole point of the move -- `PageCss` was inlined into every page because `/raw`
 * serves `text/plain`, and a file that is written but never linked would look identical in
 * every unit test.
 */
test('an admin writes the wiki stylesheet and it reaches an ordinary page', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  await page.goto('/?admin/custom-css')

  // ACE takes the textarea over (ticket 30): the textarea stays as the field the form
  // posts, hidden behind an editor in `ace/mode/css`
  const editor = page.locator('.yw-custom-css__ace')
  await expect(editor).toBeVisible()
  const box = page.locator('#custom_css')
  await expect(box).toBeHidden()

  // Start from an empty stylesheet -- the migrated `PageCss` is in there, and it opens with
  // a `/* ... */` block. Emptied through ACE, not by assigning to the textarea: the textarea
  // is downstream of the editor, so clearing it leaves the comment in ACE, the click below
  // lands the cursor inside it, and the rule gets typed *into a comment* -- saved, linked,
  // and inert. Which is exactly how this read as a product bug the first time it ran.
  await page.evaluate(() => {
    window['ace']
      .edit(document.querySelector('.yw-custom-css__ace'))
      .setValue('')
  })
  await expect(box).toHaveValue('')

  // the rail names the section it belongs to
  await expect(page.locator('.yw-dashboard__sidebar')).toContainText(
    'Apparence',
  )
  await expect(page.locator('.yw-dashboard__link--current')).toContainText(
    'CSS',
  )

  // typed into ACE, which is what a person uses -- and which must reach the textarea
  await page.locator('.yw-custom-css__ace .ace_content').click()
  await page.keyboard.type('.e2e-probe { color: rgb(1, 2, 3); }')
  await expect(box).toHaveValue(/e2e-probe/, { timeout: 5000 })

  // CSS highlighting, not wiki highlighting: `color` is a property token
  await expect(
    page.locator('.yw-custom-css__ace .ace_support.ace_type').first(),
  ).toBeVisible()

  await page.locator('button[type="submit"]').click()

  // it survives the round trip
  await expect(page.locator('#custom_css')).toHaveValue(/e2e-probe/)

  // ...and an ordinary page links it, as a file rather than inlined
  await page.goto('/?PagePrincipale')
  const link = page.locator(
    'link[rel="stylesheet"][href*="custom/styles/custom.css"]',
  )
  await expect(link).toHaveCount(1)

  // the browser actually applied it: the rule is live, not merely present
  await page.evaluate(() => {
    const probe = document.createElement('div')
    probe.className = 'e2e-probe'
    document.body.appendChild(probe)
  })
  await expect
    .poll(async () =>
      page.locator('.e2e-probe').evaluate((el) => getComputedStyle(el).color),
    )
    .toBe('rgb(1, 2, 3)')

  // emptying the box removes the stylesheet rather than leaving a link to nothing
  await page.goto('/?admin/custom-css')
  await page.evaluate(() => {
    // clearing through ACE, the way the box is actually emptied
    const host = document.querySelector('.yw-custom-css__ace')
    window['ace'].edit(host).setValue('')
  })
  await page.locator('button[type="submit"]').click()
  await page.goto('/?PagePrincipale')
  await expect(
    page.locator('link[href*="custom/styles/custom.css"]'),
  ).toHaveCount(0)
})

/**
 * The stylesheet screen, twice over: it has to be dark when the reader is, and it has to still
 * be there after you leave it and come back.
 *
 * Both failed for the same shape of reason and neither is visible to phpunit. ACE injects its
 * own theme -- a white ground and `blue` keywords -- as a `<style>` at runtime, so the screen
 * that builds an editor has to declare the bundle that repaints it, and this screen declared
 * only `yw-admin.css`. And `admin-custom-css.js` set the editor up at module top level: an ES
 * module is evaluated once per *document*, so after an hx-boost navigation away and back the
 * module was already in the browser's map, never re-ran, and the stylesheet showed as a bare
 * textarea.
 */
test('the stylesheet editor is dark when the reader is, and survives leaving the screen', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  // chosen the way a reader chooses it, on an ordinary page, so what is asserted below is the
  // stored choice arriving on an admin screen rather than a fixture
  await page.goto('/?PagePrincipale')
  await page.locator('.yw-topnav-tools').hover()
  await page.locator('[data-yw-scheme-set="dark"]').click()

  await page.goto('/?admin/custom-css')
  const editor = page.locator('.yw-custom-css__ace')
  await expect(editor).toBeVisible()

  /** A token as the browser resolves it here, in the same units a computed style comes back in. */
  const token = (property: string) =>
    page.evaluate((name) => {
      const probe = document.createElement('span')
      probe.style.color = `var(${name})`
      document.body.appendChild(probe)
      const value = getComputedStyle(probe).color
      probe.remove()
      return value
    }, property)

  // the ground: ACE's injected `.ace-tm` rule says white, and ours has one class more
  await expect
    .poll(() => editor.evaluate((el) => getComputedStyle(el).backgroundColor))
    .toBe(await token('--yw-surface-raised'))

  // ...and the text on it. Repainting the surface without the tokens would leave `blue`
  // keywords on a near-black ground, which is worse than not doing it at all.
  //
  // A rule is typed in first: what the wiki holds here is the migrated `PageCss`, which opens
  // with a comment and may have nothing else in it -- so there was no property token to read a
  // colour off, and the assertion timed out waiting for an element rather than failing on a
  // colour.
  await page.evaluate(() => {
    window['ace']
      .edit(document.querySelector('.yw-custom-css__ace'))
      .setValue('.probe { color: red; }')
  })
  await expect
    .poll(() =>
      page
        .locator('.yw-custom-css__ace .ace_support.ace_type')
        .first()
        .evaluate((el) => getComputedStyle(el).color),
    )
    .toBe(await token('--yw-primary'))

  // Away to another admin screen and back, through the rail -- which is an hx-boost
  // navigation, i.e. a new body inside the same document.
  await page.locator('.yw-dashboard__sidebar a[href*="admin/layout"]').click()
  await expect(page.locator('#custom_css')).toHaveCount(0)
  await page
    .locator('.yw-dashboard__sidebar a[href*="admin/custom-css"]')
    .click()

  await expect(
    page.locator('.yw-custom-css__ace'),
    'the editor did not come back: the module ran once for the whole document',
  ).toBeVisible()
  await expect(page.locator('#custom_css')).toBeHidden()

  // and it is a working editor on arrival, not an empty box: what the wiki holds is in it
  await page.locator('.yw-custom-css__ace .ace_content').click()
  await page.keyboard.type('/* back */')
  await expect(page.locator('#custom_css')).toHaveValue(/back/, {
    timeout: 5000,
  })
})

test('the stylesheet screen is refused to someone who is not an admin', async ({
  page,
}) => {
  await page.goto('/?admin/custom-css')
  await expect(page.locator('#custom_css')).toHaveCount(0)
})
