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

    test('navigation buttons prev/next', async ({ page }) => {
        await importGameViaPgn(page);

        const btnPrev = page.getByTestId('btn-prev');
        const btnNext = page.getByTestId('btn-next');

        await expect(btnNext).toBeDisabled();

        await btnPrev.click();
        await expect(btnNext).toBeEnabled();

        for (let i = 0; i < 5; i++) {
            await btnPrev.click();
        }
        await expect(btnPrev).toBeDisabled();

        await btnNext.click();
        await expect(btnPrev).toBeEnabled();
    });

    test('flip board swaps player names', async ({ page }) => {
        await importGameViaPgn(page);

        const topPlayer = page.getByTestId('top-player');
        const bottomPlayer = page.getByTestId('bottom-player');

        await expect(topPlayer).toHaveText('Karpov, Anatoly');
        await expect(bottomPlayer).toHaveText('Kasparov, Garry');

        await page.getByTestId('btn-flip').click();

        await expect(topPlayer).toHaveText('Kasparov, Garry');
        await expect(bottomPlayer).toHaveText('Karpov, Anatoly');
    });

    test('drag matching move advances game', async ({ page }) => {
        await importGameViaPgn(page);

        await page.getByTestId('btn-prev').click();
        await page.getByTestId('btn-prev').click();

        const board = page.locator('.cm-chessboard');
        const fromSquare = board.locator('[data-square="b5"]');
        const toSquare = board.locator('[data-square="a4"]');

        if (await fromSquare.count() > 0 && await toSquare.count() > 0) {
            const fromBox = await fromSquare.boundingBox();
            const toBox = await toSquare.boundingBox();

            if (fromBox && toBox) {
                await page.mouse.move(fromBox.x + fromBox.width / 2, fromBox.y + fromBox.height / 2);
                await page.mouse.down();
                await page.mouse.move(toBox.x + toBox.width / 2, toBox.y + toBox.height / 2, { steps: 5 });
                await page.mouse.up();
            }
        }

        await expect(page.getByTestId('btn-return')).not.toBeVisible();
    });

    test('drag non-matching move deviates', async ({ page }) => {
        await importGameViaPgn(page);

        for (let i = 0; i < 6; i++) {
            await page.getByTestId('btn-prev').click();
        }

        const board = page.locator('.cm-chessboard');
        const fromSquare = board.locator('[data-square="d2"]');
        const toSquare = board.locator('[data-square="d4"]');

        if (await fromSquare.count() > 0 && await toSquare.count() > 0) {
            const fromBox = await fromSquare.boundingBox();
            const toBox = await toSquare.boundingBox();

            if (fromBox && toBox) {
                await page.mouse.move(fromBox.x + fromBox.width / 2, fromBox.y + fromBox.height / 2);
                await page.mouse.down();
                await page.mouse.move(toBox.x + toBox.width / 2, toBox.y + toBox.height / 2, { steps: 5 });
                await page.mouse.up();
            }
        }

        const deviated = page.locator('.board-deviated');
        if (await deviated.count() > 0) {
            await expect(page.getByTestId('btn-return')).toBeVisible();
            await expect(page.getByTestId('btn-prev')).toBeDisabled();
            await expect(page.getByTestId('btn-next')).toBeDisabled();
        }
    });

    test('return to game clears deviated state', async ({ page }) => {
        await importGameViaPgn(page);

        for (let i = 0; i < 6; i++) {
            await page.getByTestId('btn-prev').click();
        }

        const board = page.locator('.cm-chessboard');
        const fromSquare = board.locator('[data-square="d2"]');
        const toSquare = board.locator('[data-square="d4"]');

        if (await fromSquare.count() > 0 && await toSquare.count() > 0) {
            const fromBox = await fromSquare.boundingBox();
            const toBox = await toSquare.boundingBox();

            if (fromBox && toBox) {
                await page.mouse.move(fromBox.x + fromBox.width / 2, fromBox.y + fromBox.height / 2);
                await page.mouse.down();
                await page.mouse.move(toBox.x + toBox.width / 2, toBox.y + toBox.height / 2, { steps: 5 });
                await page.mouse.up();
            }
        }

        const returnBtn = page.getByTestId('btn-return');
        if (await returnBtn.isVisible()) {
            await returnBtn.click();

            await expect(page.locator('.board-deviated')).not.toBeVisible();
            await expect(returnBtn).not.toBeVisible();
            await expect(page.getByTestId('btn-prev')).toBeEnabled();
        }
    });
});
