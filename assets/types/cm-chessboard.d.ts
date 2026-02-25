declare module 'cm-chessboard' {
    export const FEN: {
        start: string;
        empty: string;
    };

    export const COLOR: {
        white: 'w';
        black: 'b';
    };

    export const INPUT_EVENT_TYPE: {
        moveInputStarted: 'moveInputStarted';
        validateMoveInput: 'validateMoveInput';
        moveInputFinished: 'moveInputFinished';
        moveInputCanceled: 'moveInputCanceled';
    };

    export interface MoveInputEvent {
        type: string;
        squareFrom: string;
        squareTo: string;
        piece: string;
    }

    export class Chessboard {
        constructor(element: HTMLElement, config?: Record<string, any>);
        setPosition(fen: string, animated?: boolean): void;
        getPosition(): string;
        setOrientation(color: 'w' | 'b'): void;
        getOrientation(): 'w' | 'b';
        enableMoveInput(callback: (event: MoveInputEvent) => boolean): void;
        disableMoveInput(): void;
        destroy(): void;
    }
}
