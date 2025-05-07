import {Page} from "@playwright/test";
import {replaceEditorTextCallback, replaceEditorTextNewContent} from "./editor";
import {errorShouldBe} from "./alert";

export const createPageWithContent = async (page: Page, tag: string, content: string) => {
    await page.goto(`/?${tag}`);

    await page.getByRole('link', { name: 'créer' }).click();
    await replaceEditorTextNewContent(page, content);
    await page.getByRole('button', { name: 'Sauver' }).first().click();
    await page.waitForLoadState();
}

export const removePage = async (page: Page, tag: string) => {
    await page.goto(`/?${tag}`);
    await page.getByRole('link', { name: 'Supprimer' }).click();
    await page.locator('#YesWikiModal.modal.in').getByRole('button', { name: 'Supprimer' }).click();
    await errorShouldBe(page, `La page ${tag} a définitivement été supprimée`)
}
