import { test, expect } from '@playwright/test';
import { loginAsUser, seedDatabase } from './helpers/auth';

test.describe('Authentication', () => {
    test.beforeAll(async () => {
        await seedDatabase();
    });

    test('successful login redirects to /games', async ({ page }) => {
        await loginAsUser(page);
        await expect(page).toHaveURL('/games');
    });

    test('invalid credentials show error', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('test@chess-studio.fr');
        await page.locator('#password').fill('WrongPassword1!');
        await page.getByRole('button', { name: 'Se connecter' }).click();

        await expect(page.getByText('Identifiants incorrects.')).toBeVisible();
    });

    test('logout returns to /login', async ({ page }) => {
        await loginAsUser(page);
        await page.getByRole('button', { name: /déconnexion/i }).click();
        await expect(page).toHaveURL('/login');
    });

    test('unauthenticated user redirected to /login', async ({ page }) => {
        await page.goto('/games');
        await expect(page).toHaveURL('/login');
    });
});
