declare module 'cm-chessboard' {
    export const FEN: {
        start: string;
        empty: string;
    };

    export class Chessboard {
        constructor(element: HTMLElement, config?: Record<string, any>);
        setPosition(fen: string, animated?: boolean): void;
        getPosition(): string;
        destroy(): void;
    }
}
