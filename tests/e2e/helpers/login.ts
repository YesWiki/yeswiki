import {expect, Page} from "@playwright/test";

export const ADMIN_USERNAME = 'WikiAdmin';
export const ADMIN_PASSWORD = 'WikiAdminPassword';

export const login = async (page: Page, username: string, password: string) => {
    await page.goto('/');
    await page.locator('#yw-topnav .yw-topnav-fast-access').getByRole('button', { name: 'Se connecter' }).click();
    await page.locator('#LoginModal [name="name"]').fill(username);
    await page.locator('#LoginModal [name="password"]').fill(password);
    await page.locator('#LoginModal form button').click();

    // Ensure that the login was successful
    await expect(page.locator('#yw-topnav .yw-topnav-fast-access').getByRole('button', { name: 'Mes options' })).toContainText(username);
}
