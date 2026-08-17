/** Which scheme is in force right now: the reader's choice, or their system's. */
export function currentScheme() {
  if (document.documentElement.dataset.theme) {
    return document.documentElement.dataset.theme
  }
  return window.matchMedia('(prefers-color-scheme: dark)').matches
    ? 'dark'
    : 'light'
}

/** The three names Vditor wants, from the one we have. */
export function vditorTheme(scheme = currentScheme()) {
  return scheme === 'dark'
    ? { theme: 'dark', content: 'dark', code: 'github-dark' }
    : { theme: 'classic', content: 'light', code: 'github' }
}

/** The options an editor is built with, ready to spread into a Vditor configuration. */
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

/** Keep one editor in step with the scheme for as long as it is on the page. */
export function followScheme(editor, cdn) {
  const apply = () => {
    const theme = vditorTheme()
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
