import { test, expect } from '@playwright/test';
import { seedDatabase } from './helpers/auth';

test.describe('Change Password (forced)', () => {
    test.beforeEach(async () => {
        await seedDatabase();
    });

    test('mustChangePassword user redirected to /change-password', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('newuser@chess-studio.fr');
        await page.locator('#password').fill('TempPass1!');
        await page.getByRole('button', { name: 'Se connecter' }).click();

        await expect(page).toHaveURL('/change-password');
    });

    test('valid new password redirects to /games', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('newuser@chess-studio.fr');
        await page.locator('#password').fill('TempPass1!');
        await page.getByRole('button', { name: 'Se connecter' }).click();
        await page.waitForURL('/change-password');

        await page.locator('#password').fill('NewStrong1!');
        await page.locator('#confirmPassword').fill('NewStrong1!');
        await page.getByRole('button', { name: 'Changer le mot de passe' }).click();

        await expect(page).toHaveURL('/games');
    });

    test('weak password shows error', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('newuser@chess-studio.fr');
        await page.locator('#password').fill('TempPass1!');
        await page.getByRole('button', { name: 'Se connecter' }).click();
        await page.waitForURL('/change-password');

        await page.locator('#password').fill('weak');
        await page.locator('#confirmPassword').fill('weak');
        await page.getByRole('button', { name: 'Changer le mot de passe' }).click();

        await expect(page.getByText('au moins 8 caractères')).toBeVisible();
    });
});
