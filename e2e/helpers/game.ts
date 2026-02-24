import { Page } from '@playwright/test';

export const SAMPLE_PGN = `[Event "Tournoi Test"]
[White "Kasparov, Garry"]
[Black "Karpov, Anatoly"]
[Result "1-0"]

1. e4 e5 2. Nf3 Nc6 3. Bb5 a6 1-0`;

export async function importGameViaPgn(page: Page) {
    await page.goto('/games/import');
    await page.getByText('Coller un PGN').click();
    await page.locator('textarea').fill(SAMPLE_PGN);
    await page.getByRole('button', { name: 'Importer la partie' }).click();
    await page.waitForURL(/\/games\/\d+/);
}
