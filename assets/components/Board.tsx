import React, { useEffect, useRef, useState } from 'react';
import type { Chessboard as ChessboardType, MoveInputEvent } from 'cm-chessboard';

interface BoardProps {
    position: string;
    orientation?: 'w' | 'b';
    onMoveInput?: (from: string, to: string) => boolean;
    deviated?: boolean;
}

const Board: React.FC<BoardProps> = ({ position, orientation = 'w', onMoveInput, deviated = false }) => {
    const boardRef = useRef<HTMLDivElement>(null);
    const chessboardRef = useRef<ChessboardType | null>(null);
    const onMoveInputRef = useRef(onMoveInput);
    onMoveInputRef.current = onMoveInput;
    const [boardReady, setBoardReady] = useState(false);

    useEffect(() => {
        if (!boardRef.current) return;

        let mounted = true;

        (async () => {
            try {
                const { Chessboard, FEN } = await import('cm-chessboard');

                if (!mounted || !boardRef.current) return;

                if (!chessboardRef.current) {
                    chessboardRef.current = new Chessboard(boardRef.current, {
                        position: position || FEN.start,
                        assetsUrl: '/build/cm-chessboard/',
                        style: {
                            cssClass: 'default',
                        },
                        responsive: true,
                    });
                    setBoardReady(true);
                }
            } catch (err) {
                console.error('Failed to load chessboard:', err);
            }
        })();

        return () => {
            mounted = false;
        };
    }, []);

    useEffect(() => {
        if (chessboardRef.current && position) {
            chessboardRef.current.setPosition(position, true);
        }
    }, [position]);

    useEffect(() => {
        if (!chessboardRef.current) return;
        chessboardRef.current.setOrientation(orientation);
    }, [orientation, boardReady]);

    useEffect(() => {
        const board = chessboardRef.current;
        if (!board) return;

        if (!onMoveInputRef.current) {
            board.disableMoveInput();
            return;
        }

        const enableInput = () => {
            import('cm-chessboard').then(({ INPUT_EVENT_TYPE }) => {
                board.enableMoveInput((event: MoveInputEvent) => {
                    if (event.type === INPUT_EVENT_TYPE.moveInputStarted) {
                        return true;
                    }
                    if (event.type === INPUT_EVENT_TYPE.validateMoveInput) {
                        return onMoveInputRef.current?.(event.squareFrom, event.squareTo) ?? false;
                    }
                    if (event.type === INPUT_EVENT_TYPE.moveInputFinished) {
                        enableInput();
                    }
                    return true;
                });
            });
        };

        enableInput();

        return () => {
            board.disableMoveInput();
        };
    }, [boardReady]);

    return (
        <div className={`flex justify-center${deviated ? ' board-deviated' : ''}`}>
            <div ref={boardRef} className="w-full max-w-[500px]" />
        </div>
    );
};

export default Board;
