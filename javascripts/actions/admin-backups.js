/* The backups screen is drawn by the server and kept up to date by htmx; this only sends the update on once the backup before it is done, and reloads the page after a restore. */

ywInitEach('form[data-backups-autosubmit]', (form) => form.submit())

ywInitEach('[data-backups-reload]', (element) => {
  window.setTimeout(
    () => window.location.reload(),
    Number(element.dataset.backupsReload) || 2000,
  )
})
