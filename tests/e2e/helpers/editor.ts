import {Page} from "@playwright/test";

/**
 * Replace the text in the editor using a callback function.
 * The callback function does not have access to the page context.
 * So only use the value passed as argument and the browser api.
 */
export const replaceEditorText = async (page: Page, callback: Function, additionalProperties: object = null) => {
    await page.waitForLoadState();

    await page.evaluate(({callbackStr, additionalPropertiesStr}) => {
        const additionalProperties = JSON.parse(additionalPropertiesStr);
        const callback = new Function('return ' + callbackStr)();
        const editor = window['aceditor-body'].editor;
        const value = editor.getValue();
        const newValue = callback(value, additionalProperties);
        editor.setValue(newValue);
    }, {callbackStr: callback.toString(), additionalPropertiesStr: JSON.stringify(additionalProperties)});
}
