import {Page} from "@playwright/test";
import {replaceEditorText} from "./editor";

export const createPageWithContent = async (page: Page, tag: string, content: string) => {
    await page.goto(`/?${tag}`);

    await page.getByRole('link', { name: 'créer' }).click();
    await replaceEditorText(page, (value, additionalProperties) => additionalProperties.content, {content: content});
    await page.getByRole('button', { name: 'Sauver' }).first().click();
    await page.waitForLoadState();
}
