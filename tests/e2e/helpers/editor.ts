import {Page} from "@playwright/test";

/**
 * Replace the text in the editor using a callback function.
 * The callback function does not have access to the page context.
 * So only use the value passed as argument and the browser api.
 */
export const replaceEditorTextCallback = async (page: Page, callback: Function, additionalProperties: object = null) => {
    await page.waitForLoadState();

    // Since ticket 16 an internal link is htmx-boosted, so reaching the editor by clicking
    // "Éditer la page" swaps the body rather than loading a document: waitForLoadState()
    // resolves before ywInitEach has built the editor, and window['aceditor-body'] is
    // still undefined. Wait for the instance itself rather than for the navigation.
    await page.waitForFunction(() => window['aceditor-body']?.editor !== undefined, null, {timeout: 15000});

    await page.evaluate(({callbackStr, additionalPropertiesStr}) => {
        const additionalProperties = JSON.parse(additionalPropertiesStr);
        const callback = new Function('return ' + callbackStr)();
        const editor = window['aceditor-body'].editor;
        const value = editor.getValue();
        const newValue = callback(value, additionalProperties);
        editor.setValue(newValue);
    }, {callbackStr: callback.toString(), additionalPropertiesStr: JSON.stringify(additionalProperties)});
}


export const replaceEditorTextNewContent = async (page: Page, newContent: string) => {
    await replaceEditorTextCallback(page, (value, additionalProperties) => additionalProperties.content, {content: newContent});
}

/**
 * Click "Sauver" and wait for the save to actually land.
 *
 * This is ticket 25's defect 9, root-caused. The editor form carries hx-boost="false"
 * (ticket 25 defect 5), so saving is a real form POST and a full navigation -- and
 * Playwright's `click()` does not wait for a navigation it triggers. Asserting straight
 * after the click therefore starts against the OLD document and survives only because
 * `expect`'s 5s retry usually outlasts the round trip.
 *
 * Usually. On a cold opcache the whole edit/save path is compiled during that request:
 * `example.spec.ts` runs in ~4s warm and was measured at 9.6s cold, so the assertion window
 * expires while the browser is still showing the pre-save page. That is exactly the reported
 * symptom -- "it receives the pre-save content" -- and it is why the failure only ever
 * appeared in full-suite runs after code changes, never in isolation.
 *
 * The fix is to wait for the round trip rather than to raise the timeout: what the test is
 * about is whether the save worked, never how fast the server was.
 */
export const saveEditor = async (page: Page) => {
    const saved = page.waitForResponse(
        (response) => response.request().method() === 'POST',
        {timeout: 30000}
    );
    await page.getByRole('button', {name: 'Sauver'}).first().click();
    await saved;
    await page.waitForLoadState();
}
