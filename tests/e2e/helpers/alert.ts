import {expect, Page} from "@playwright/test";

export const errorShouldBe = async (
    page: Page,
    content: string
) => {
    const locator = page.locator(".toast-message .alert");
    await expect(locator).toBeVisible();
    await expect(locator).toContainText(content);
};