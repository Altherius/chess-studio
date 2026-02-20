import React, { useState, useCallback } from 'react';
import { Chess } from 'chess.js';
import Board from './Board';
import MoveList from './MoveList';
import Analysis from './Analysis';
import GameImport from './GameImport';
import { useStockfish } from '../hooks/useStockfish';
import { Card, CardHeader, CardTitle, CardContent } from './ui/card';
import type { Game } from '../types/chess';

const App: React.FC = () => {
    const [game, setGame] = useState<Game | null>(null);
    const [chess] = useState(() => new Chess());
    const [currentMoveIndex, setCurrentMoveIndex] = useState(-1);
    const [position, setPosition] = useState(chess.fen());

    const { lines, isAnalyzing, analyze } = useStockfish();

    const handleImport = useCallback(async (pgn: string) => {
        try {
            const response = await fetch('/api/games/import', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pgn }),
            });

            if (!response.ok) throw new Error('Import failed');

            const data = await response.json();

            chess.loadPgn(pgn);
            const history = chess.history();

            setGame({ ...data, pgn });
            setCurrentMoveIndex(history.length - 1);
            setPosition(chess.fen());

            analyze(chess.fen());
        } catch (err) {
            console.error('Failed to import game:', err);
        }
    }, [chess, analyze]);

    const handleMoveClick = useCallback((moveIndex: number) => {
        chess.reset();
        if (game?.pgn) {
            chess.loadPgn(game.pgn);
        }
        const history = chess.history();

        chess.reset();
        for (let i = 0; i <= moveIndex; i++) {
            chess.move(history[i]);
        }

        setCurrentMoveIndex(moveIndex);
        setPosition(chess.fen());
        analyze(chess.fen());
    }, [chess, game, analyze]);

    const moves = (() => {
        if (!game?.pgn) return [];
        const tempChess = new Chess();
        tempChess.loadPgn(game.pgn);
        return tempChess.history();
    })();

    return (
        <div className="mx-auto max-w-[1400px] p-5">
            <header className="text-center py-5 border-b border-border mb-8">
                <h1 className="text-3xl font-bold">Chess Studio</h1>
            </header>

            {!game ? (
                <GameImport onImport={handleImport} />
            ) : (
                <div className="grid grid-cols-[1fr_400px_300px] gap-5 items-start">
                    <Card>
                        <CardContent className="p-4">
                            <Board position={position} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {game.playerWhite ?? '?'} vs {game.playerBlack ?? '?'}
                                {game.result && ` - ${game.result}`}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <MoveList
                                moves={moves}
                                currentMoveIndex={currentMoveIndex}
                                onMoveClick={handleMoveClick}
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Analyse</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Analysis
                                lines={lines}
                                isAnalyzing={isAnalyzing}
                                gameId={game.id}
                            />
                        </CardContent>
                    </Card>
                </div>
            )}
        </div>
    );
};

export default App;
