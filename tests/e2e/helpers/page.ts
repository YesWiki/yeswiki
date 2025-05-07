import {Page} from "@playwright/test";
import {replaceEditorTextCallback, replaceEditorTextNewContent} from "./editor";

export const createPageWithContent = async (page: Page, tag: string, content: string) => {
    await page.goto(`/?${tag}`);

    await page.getByRole('link', { name: 'créer' }).click();
    await replaceEditorTextNewContent(page, content);
    await page.getByRole('button', { name: 'Sauver' }).first().click();
    await page.waitForLoadState();
}
