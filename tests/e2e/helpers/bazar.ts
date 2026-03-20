import {Locator} from "@playwright/test";

export const checkCheckbox = async (parent: Locator, label: string) => {
    await parent.locator('.bazar-checkbox-cols > .checkbox').filter({
        hasText: label
    }).click();
}
