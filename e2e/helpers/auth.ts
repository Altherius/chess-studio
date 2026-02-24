import { Page } from '@playwright/test';

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';

export async function seedDatabase() {
    const res = await fetch(`${BASE_URL}/api/test/seed`, { method: 'POST' });
    if (!res.ok) {
        throw new Error(`Failed to seed database: ${res.status}`);
    }
}

export async function loginAsUser(page: Page) {
    await page.goto('/login');
    await page.locator('#email').fill('test@chess-studio.fr');
    await page.locator('#password').fill('TestPass1!');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.waitForURL('/games');
}

export async function loginAsAdmin(page: Page) {
    await page.goto('/login');
    await page.locator('#email').fill('admin@chess-studio.fr');
    await page.locator('#password').fill('AdminPass1!');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.waitForURL('/games');
}
