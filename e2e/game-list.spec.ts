import { test, expect } from '@playwright/test';
import { loginAsUser, seedDatabase } from './helpers/auth';
import { importGameViaPgn } from './helpers/game';

test.describe('Game List', () => {
    test.beforeAll(async () => {
        await seedDatabase();
    });

    test.beforeEach(async ({ page }) => {
        await loginAsUser(page);
    });

    test('shows imported games', async ({ page }) => {
        await importGameViaPgn(page);
        await page.goto('/games');

        await expect(page.getByText('Kasparov, Garry')).toBeVisible();
        await expect(page.getByText('Karpov, Anatoly')).toBeVisible();
    });

    test('filter by player name', async ({ page }) => {
        await importGameViaPgn(page);
        await page.goto('/games');

        await page.getByPlaceholder('Joueur').fill('Kasparov');
        await page.waitForTimeout(500);

        await expect(page.getByText('Kasparov, Garry').first()).toBeVisible();
    });

    test('empty state for fresh user', async ({ page }) => {
        await seedDatabase();
        await loginAsUser(page);
        await page.goto('/games');

        await expect(page.getByText('Aucune partie')).toBeVisible();
    });
});
