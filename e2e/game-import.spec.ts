import { test, expect } from '@playwright/test';
import { loginAsUser, seedDatabase } from './helpers/auth';
import { SAMPLE_PGN } from './helpers/game';

test.describe('Game Import', () => {
    test.beforeAll(async () => {
        await seedDatabase();
    });

    test.beforeEach(async ({ page }) => {
        await loginAsUser(page);
    });

    test('PGN paste redirects to game page', async ({ page }) => {
        await page.goto('/games/import');
        await page.getByText('Coller un PGN').click();
        await page.locator('textarea').fill(SAMPLE_PGN);
        await page.getByRole('button', { name: 'Importer la partie' }).click();

        await expect(page).toHaveURL(/\/games\/\d+/);
    });

    test('invalid PGN shows error', async ({ page }) => {
        await page.goto('/games/import');
        await page.getByText('Coller un PGN').click();
        await page.locator('textarea').fill('1. e4 e5 2. Zz9 *');
        await page.getByRole('button', { name: 'Importer la partie' }).click();

        await expect(page.getByText('is not valid')).toBeVisible();
    });

    test('file upload succeeds', async ({ page }) => {
        await page.goto('/games/import');
        await page.getByText('Importer un fichier').click();

        const fileInput = page.locator('input[type="file"]');
        await fileInput.setInputFiles({
            name: 'game.pgn',
            mimeType: 'text/plain',
            buffer: Buffer.from(SAMPLE_PGN),
        });

        await page.getByRole('button', { name: 'Importer la partie' }).click();
        await expect(page).toHaveURL(/\/games\/\d+/);
    });

    test('manual form succeeds', async ({ page }) => {
        await page.goto('/games/import');
        await page.getByText('Saisie manuelle').click();

        await page.locator('textarea').fill('1. e4 e5 2. Nf3 Nc6 *');
        await page.getByRole('button', { name: 'Importer la partie' }).click();

        await expect(page).toHaveURL(/\/games\/\d+/);
    });

    test('manual form with French notation succeeds', async ({ page }) => {
        await page.goto('/games/import');
        await page.getByText('Saisie manuelle').click();

        await page.locator('textarea').fill('1. e4 e5 2. Cf3 Cc6 *');
        await page.getByRole('button', { name: 'Importer la partie' }).click();

        await expect(page).toHaveURL(/\/games\/\d+/);
    });
});
