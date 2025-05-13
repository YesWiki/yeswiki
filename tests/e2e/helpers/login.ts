import {expect, Page} from "@playwright/test";

export const ADMIN_USERNAME = 'WikiAdmin';
export const ADMIN_PASSWORD = 'WikiAdminPassword';

export const login = async (page: Page, username: string, password: string) => {
    const res = await page.request.post('/?api/login', {
        form: {
            username,
            password
        }
    });
    expect(res.ok()).toBeTruthy();
}

export const logout = async (page: Page) => {
    await page.context().clearCookies(); // Force logout
}
