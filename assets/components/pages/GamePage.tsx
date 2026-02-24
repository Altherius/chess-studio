import React, { useState, useCallback, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Chess } from 'chess.js';
import Board from '../Board';
import MoveList from '../MoveList';
import Analysis from '../Analysis';
import { useStockfish } from '../../hooks/useStockfish';
import { ArrowLeft } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import type { Game } from '../../types/chess';
import { sanToFrench } from '../../lib/chess';
import { AlertError } from '../ui/alert';

const GamePage: React.FC = () => {
    const { id } = useParams<{ id: string }>();
    const [game, setGame] = useState<Game | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [chess] = useState(() => new Chess());
    const [currentMoveIndex, setCurrentMoveIndex] = useState(-1);
    const [position, setPosition] = useState(chess.fen());

    const { lines, isAnalyzing, analyze } = useStockfish();

    useEffect(() => {
        (async () => {
            try {
                const res = await fetch(`/api/games/${id}`);
                if (!res.ok) throw new Error('Partie introuvable');
                const data: Game = await res.json();

                chess.loadPgn(data.pgn);
                const history = chess.history();

                setGame(data);
                setCurrentMoveIndex(history.length - 1);
                setPosition(chess.fen());
                analyze(chess.fen());
            } catch (err: any) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        })();
    }, [id]);

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
        return tempChess.history().map(sanToFrench);
    })();

    useEffect(() => {
        if (!game) return;

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'ArrowLeft' && currentMoveIndex > 0) {
                handleMoveClick(currentMoveIndex - 1);
            } else if (e.key === 'ArrowRight' && currentMoveIndex < moves.length - 1) {
                handleMoveClick(currentMoveIndex + 1);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [game, currentMoveIndex, handleMoveClick, moves.length]);

    if (loading) {
        return (
            <div className="flex items-center justify-center py-20">
                <p className="text-muted-foreground">Chargement...</p>
            </div>
        );
    }

    if (error || !game) {
        return (
            <div className="text-center py-20">
                <AlertError message={error || 'Partie introuvable'} />
                <Button variant="outline" asChild>
                    <Link to="/games">Retour aux parties</Link>
                </Button>
            </div>
        );
    }

    return (
        <div>
            <div className="mb-4">
                <Button variant="ghost" size="sm" asChild>
                    <Link to="/games"><ArrowLeft className="inline h-4 w-4 mr-1" />Mes parties</Link>
                </Button>
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-5 items-start">
                <div>
                    <div className="text-sm font-medium text-muted-foreground mb-1">
                        {game.playerBlack ?? '?'}
                    </div>
                    <Card>
                        <CardContent className="p-4">
                            <Board position={position} />
                        </CardContent>
                    </Card>
                    <div className="text-sm font-medium text-muted-foreground mt-1 flex items-center justify-between">
                        <span>{game.playerWhite ?? '?'}</span>
                        {game.result && <span className="font-mono">{game.result}</span>}
                    </div>
                </div>
                <div className="space-y-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>Analyse</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Analysis
                                lines={lines}
                                isAnalyzing={isAnalyzing}
                                gameId={game.id}
                                fen={position}
                                currentMoveIndex={currentMoveIndex}
                                serverAnalyses={game.analyses}
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Coups</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <MoveList
                                moves={moves}
                                currentMoveIndex={currentMoveIndex}
                                onMoveClick={handleMoveClick}
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
};

export default GamePage;
