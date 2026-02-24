import { test, expect } from '@playwright/test';
import { loginAsUser, seedDatabase } from './helpers/auth';
import { importGameViaPgn } from './helpers/game';

test.describe('Game View', () => {
    test.beforeAll(async () => {
        await seedDatabase();
    });

    test.beforeEach(async ({ page }) => {
        await loginAsUser(page);
    });

    test('game page shows player names and board', async ({ page }) => {
        await importGameViaPgn(page);

        await expect(page.getByText('Kasparov, Garry')).toBeVisible();
        await expect(page.getByText('Karpov, Anatoly')).toBeVisible();
    });

    test('move list visible with expected moves', async ({ page }) => {
        await importGameViaPgn(page);

        await expect(page.getByText('e4')).toBeVisible();
        await expect(page.getByText('e5')).toBeVisible();
    });
});
