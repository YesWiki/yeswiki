import {Page} from "@playwright/test";
import {replaceEditorTextCallback, replaceEditorTextNewContent, saveEditor} from "./editor";
import {errorShouldBe} from "./alert";

export const createPageWithContent = async (page: Page, tag: string, content: string) => {
    await page.goto(`/?${tag}`);

    await page.getByRole('link', { name: 'créer' }).click();
    await replaceEditorTextNewContent(page, content);
    // saveEditor, not click + waitForLoadState: the latter waits on the CURRENT document's
    // load state, so if the navigation has not started yet it resolves immediately and the
    // caller carries on against the pre-save page. Same race as ticket 25's defect 9.
    await saveEditor(page);
}

export const removePage = async (page: Page, tag: string) => {
    await page.goto(`/?${tag}`);
    await page.locator('.footer').getByRole('link', { name: 'Supprimer' }).click();
    await page.locator('#YesWikiModal.modal.in').getByRole('button', { name: 'Supprimer' }).click();
    await errorShouldBe(page, `La page ${tag} a définitivement été supprimée`)
}
