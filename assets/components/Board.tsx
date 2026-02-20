import React, { useEffect, useRef } from 'react';

interface BoardProps {
    position: string;
}

const Board: React.FC<BoardProps> = ({ position }) => {
    const boardRef = useRef<HTMLDivElement>(null);
    const chessboardRef = useRef<any>(null);

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

    return (
        <div className="flex justify-center">
            <div ref={boardRef} className="w-full max-w-[500px]" />
        </div>
    );
};

export default Board;
