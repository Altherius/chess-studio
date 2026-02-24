import { test, expect } from '@playwright/test';
import { loginAsUser, seedDatabase } from './helpers/auth';

test.describe('Profile', () => {
    test.beforeEach(async () => {
        await seedDatabase();
    });

    test.beforeEach(async ({ page }) => {
        await loginAsUser(page);
    });

    test('successful password change', async ({ page }) => {
        await page.goto('/profile');

        await page.locator('#currentPassword').fill('TestPass1!');
        await page.locator('#newPassword').fill('NewStrong1!');
        await page.locator('#confirmPassword').fill('NewStrong1!');
        await page.getByRole('button', { name: 'Modifier le mot de passe' }).click();

        await expect(page.getByText('Mot de passe modifié avec succès.')).toBeVisible();
    });

    test('wrong current password shows error', async ({ page }) => {
        await page.goto('/profile');

        await page.locator('#currentPassword').fill('WrongPass1!');
        await page.locator('#newPassword').fill('NewStrong1!');
        await page.locator('#confirmPassword').fill('NewStrong1!');
        await page.getByRole('button', { name: 'Modifier le mot de passe' }).click();

        await expect(page.getByText('Le mot de passe actuel est incorrect.')).toBeVisible();
    });
});
