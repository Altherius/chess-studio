import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsUser, seedDatabase } from './helpers/auth';

test.describe('Admin Users', () => {
    test.beforeEach(async () => {
        await seedDatabase();
    });

    test('admin sees user list', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/users');

        await expect(page.getByText('test@chess-studio.fr')).toBeVisible();
    });

    test('admin creates a new user', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/users/create');

        await page.locator('#email').fill('created@chess-studio.fr');
        await page.locator('#lastName').fill('Dupont');
        await page.locator('#firstName').fill('Marie');
        await page.getByRole('button', { name: "Créer l'utilisateur" }).click();

        await expect(page.getByText('created@chess-studio.fr')).toBeVisible();
    });

    test('admin toggles active status', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/users');

        const row = page.locator('tr', { hasText: 'test@chess-studio.fr' });
        await row.getByRole('button', { name: /désactiver/i }).click();

        await expect(row.getByRole('button', { name: /activer/i })).toBeVisible();
    });

    test('regular user sees empty user list on /users', async ({ page }) => {
        await loginAsUser(page);
        await page.goto('/users');

        await expect(page.getByText('Aucun utilisateur.')).toBeVisible();
    });
});
