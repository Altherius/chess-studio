const STANDARD_HEADERS = new Set([
    'Event', 'Site', 'Date', 'Round', 'White', 'Black', 'Result',
]);

export function sanitizePgn(pgn: string): string {
    return pgn
        .replace(/^\[(\w+)\s+"(?:[^"\\]|\\.)*"\]\s*$/gm, (line, tag) =>
            STANDARD_HEADERS.has(tag) ? line : ''
        )
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

export const PIECE_TO_FRENCH: Record<string, string> = {
    N: 'C',
    B: 'F',
    R: 'T',
    Q: 'D',
    K: 'R',
};

export function sanToFrench(san: string): string {
    return san.replace(/[NBRQK]/g, (ch) => PIECE_TO_FRENCH[ch] ?? ch);
}

export function shortenOpening(name: string | null): string {
    if (!name) return '';
    const colonIndex = name.indexOf(' : ');
    if (colonIndex !== -1) {
        const afterColon = name.indexOf(',', colonIndex);
        return afterColon === -1 ? name : name.substring(0, afterColon);
    }
    const secondComma = name.indexOf(',', name.indexOf(',') + 1);
    return secondComma === -1 ? name : name.substring(0, secondComma);
}
