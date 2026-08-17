// editor-scheme.js -- the editors follow the viewer's Colour scheme (ADR-0020).
//
// Vditor brings its own themes and a `setTheme()` to switch between them, so it is told which
// one to wear at build time and told again whenever the reader changes their mind. ACE is not
// here: it has no theme file vendored at all, and its surface is painted from the same tokens
// as everything else (styles/yw-editor.css), so it follows the scheme without being asked.
//
// `window.ywScheme` is declared by the inline script CoreAssets puts at the top of the head,
// before first paint. It is read rather than depended on: an editor on a page whose head that
// script never reached should still open, in the light theme, rather than not open at all.

/** Which scheme is in force right now: the reader's choice, or their system's. */
export function currentScheme() {
  if (document.documentElement.dataset.theme) {
    return document.documentElement.dataset.theme
  }
  return window.matchMedia('(prefers-color-scheme: dark)').matches
    ? 'dark'
    : 'light'
}

/**
 * The three names Vditor wants, from the one we have.
 *
 * `theme` is its chrome, `contentTheme` the written text, `codeTheme` the highlighting inside
 * a code block -- three separate settings that must move together, or the toolbar goes dark
 * over a white page.
 */
export function vditorTheme(scheme = currentScheme()) {
  return scheme === 'dark'
    ? { theme: 'dark', content: 'dark', code: 'github-dark' }
    : { theme: 'classic', content: 'light', code: 'github' }
}

/**
 * The options an editor is built with, ready to spread into a Vditor configuration.
 *
 * `contentTheme` needs its path spelled out: Vditor fetches that stylesheet itself, and its
 * default address is a CDN -- which is exactly what `cdn:` is set to a local directory to
 * avoid everywhere else in this wiki.
 */
export function vditorThemeOptions(cdn) {
  const theme = vditorTheme()
  return {
    theme: theme.theme,
    preview: {
      theme: { current: theme.content, path: `${cdn}/dist/css/content-theme` },
      hljs: { style: theme.code },
    },
  }
}

/**
 * Keep one editor in step with the scheme for as long as it is on the page.
 *
 * Two sources, because there are two ways the answer changes: the wiki's own toggle (which
 * announces itself) and the machine's preference changing under a reader who is following it
 * (dusk, on a phone). Neither is worth a listener that outlives the editor -- but an editor
 * lives as long as its page here, and a boosted navigation replaces the page, so nothing is
 * unbound and nothing needs to be.
 */
export function followScheme(editor, cdn) {
  const apply = () => {
    const theme = vditorTheme()
    // the fourth argument is the same path as above: setTheme fetches the content stylesheet
    // again, and without it goes back to asking a CDN
    editor.setTheme(
      theme.theme,
      theme.content,
      theme.code,
      `${cdn}/dist/css/content-theme`,
    )
  }

  document.addEventListener('yw:scheme', apply)
  window
    .matchMedia('(prefers-color-scheme: dark)')
    .addEventListener('change', apply)
}
