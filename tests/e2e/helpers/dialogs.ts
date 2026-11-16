import { Page } from '@playwright/test'

/** Answers every confirmation from now on, the browser's own and the wiki's dialog (end-users 31), and returns how many have been asked, across pages. */
export const answerConfirmations = async (
  page: Page,
  accept: boolean,
): Promise<() => number> => {
  let asked = 0
  page.on('dialog', (dialog) => {
    asked += 1
    ;(accept ? dialog.accept() : dialog.dismiss()).catch(() => {})
  })
  await page.exposeFunction('ywConfirmWasAsked', () => {
    asked += 1
  })
  const answerTheWikisDialog = (answer: boolean) => {
    const scope = window as unknown as {
      ywConfirmAnswerPatched?: boolean
      ywConfirmWasAsked?: () => void
    }
    if (scope.ywConfirmAnswerPatched) return
    scope.ywConfirmAnswerPatched = true
    const showModal = HTMLDialogElement.prototype.showModal
    HTMLDialogElement.prototype.showModal = function (this: HTMLDialogElement) {
      showModal.call(this)
      if (!this.classList.contains('yw-dialog')) return
      scope.ywConfirmWasAsked?.()
      setTimeout(() => this.close(answer ? 'confirm' : 'cancel'))
    }
  }
  await page.addInitScript(answerTheWikisDialog, accept)
  await page.evaluate(answerTheWikisDialog, accept)

  return () => asked
}
