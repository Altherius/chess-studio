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
