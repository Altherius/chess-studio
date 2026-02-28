import React, { useState, useCallback, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Chess } from 'chess.js';
import Board from '../Board';
import MoveList from '../MoveList';
import Analysis from '../Analysis';
import { useStockfish } from '@/hooks/useStockfish';
import { ArrowLeft, ChevronLeft, ChevronRight, ArrowUpDown, Download, Pencil } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import type { Game } from '@/types/chess';
import { sanToFrench, sanitizePgn, shortenOpening } from '@/lib/chess';
import { AlertError } from '../ui/alert';

const GamePage: React.FC = () => {
    const { id } = useParams<{ id: string }>();
    const [game, setGame] = useState<Game | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [chess] = useState(() => new Chess());
    const [currentMoveIndex, setCurrentMoveIndex] = useState(-1);
    const [position, setPosition] = useState(chess.fen());
    const [orientation, setOrientation] = useState<'w' | 'b'>('w');
    const [deviated, setDeviated] = useState(false);
    const [deviatedFen, setDeviatedFen] = useState<string | null>(null);

    const { lines, isAnalyzing, analyze } = useStockfish();

    useEffect(() => {
        (async () => {
            try {
                const res = await fetch(`/api/games/${id}`);
                if (!res.ok) throw new Error('Partie introuvable');
                const data: Game = await res.json();

                chess.loadPgn(sanitizePgn(data.pgn));
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
        setDeviated(false);
        setDeviatedFen(null);

        chess.reset();
        if (game?.pgn) {
            chess.loadPgn(sanitizePgn(game.pgn));
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
        tempChess.loadPgn(sanitizePgn(game.pgn));
        return tempChess.history().map(sanToFrench);
    })();

    const handlePrevMove = useCallback(() => {
        if (deviated || currentMoveIndex < 0) return;
        handleMoveClick(currentMoveIndex - 1);
    }, [deviated, currentMoveIndex, handleMoveClick]);

    const handleNextMove = useCallback(() => {
        if (deviated || currentMoveIndex >= moves.length - 1) return;
        handleMoveClick(currentMoveIndex + 1);
    }, [deviated, currentMoveIndex, moves.length, handleMoveClick]);

    const handleFlip = useCallback(() => {
        setOrientation(prev => prev === 'w' ? 'b' : 'w');
    }, []);

    const handleMoveInput = useCallback((from: string, to: string): boolean => {
        const currentFen = deviatedFen ?? position;
        const tempChess = new Chess(currentFen);

        let move;
        try {
            move = tempChess.move({ from, to, promotion: 'q' });
        } catch {
            return false;
        }
        if (!move) return false;

        if (!deviated && game?.pgn) {
            const gameChess = new Chess();
            gameChess.loadPgn(sanitizePgn(game.pgn));
            const fullHistory = gameChess.history({ verbose: true });
            const nextMove = fullHistory[currentMoveIndex + 1];

            if (nextMove && nextMove.from === from && nextMove.to === to) {
                handleMoveClick(currentMoveIndex + 1);
                return true;
            }
        }

        const newFen = tempChess.fen();
        setDeviated(true);
        setDeviatedFen(newFen);
        setPosition(newFen);
        analyze(newFen);
        return true;
    }, [deviatedFen, position, deviated, game, currentMoveIndex, handleMoveClick, analyze]);

    const handleReturnToGame = useCallback(() => {
        setDeviated(false);
        setDeviatedFen(null);

        chess.reset();
        if (game?.pgn) {
            chess.loadPgn(sanitizePgn(game.pgn));
        }
        const history = chess.history();

        chess.reset();
        for (let i = 0; i <= currentMoveIndex; i++) {
            chess.move(history[i]);
        }

        setPosition(chess.fen());
        analyze(chess.fen());
    }, [chess, game, currentMoveIndex, analyze]);

    const handleDownloadPgn = useCallback(() => {
        if (!game) return;
        const blob = new Blob([game.pgn], { type: 'application/x-chess-pgn' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const white = game.playerWhite ?? 'Inconnu';
        const black = game.playerBlack ?? 'Inconnu';
        a.download = `${white} - ${black}.pgn`;
        a.click();
        URL.revokeObjectURL(url);
    }, [game]);

    useEffect(() => {
        if (!game) return;

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'ArrowLeft') {
                handlePrevMove();
            } else if (e.key === 'ArrowRight') {
                handleNextMove();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [game, handlePrevMove, handleNextMove]);

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

    const topPlayer = orientation === 'w' ? game.playerBlack : game.playerWhite;
    const topElo = orientation === 'w' ? game.blackElo : game.whiteElo;
    const bottomPlayer = orientation === 'w' ? game.playerWhite : game.playerBlack;
    const bottomElo = orientation === 'w' ? game.whiteElo : game.blackElo;

    return (
        <div>
            <div className="mb-4 flex items-center gap-2">
                <Button variant="ghost" size="sm" asChild>
                    <Link to="/games"><ArrowLeft className="inline h-4 w-4 mr-1" />Mes parties</Link>
                </Button>
                {game?.isOwner && (
                    <Button variant="outline" size="sm" asChild>
                        <Link to={`/games/${id}/edit`}><Pencil className="inline h-4 w-4 mr-1" />Modifier</Link>
                    </Button>
                )}
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-5 items-start">
                <div>
                    <Card>
                        <CardContent className="p-4">
                            <MoveList
                                moves={moves}
                                currentMoveIndex={currentMoveIndex}
                                onMoveClick={handleMoveClick}
                            />
                            <div className="border-b mb-3" />
                            <div className="text-sm font-medium text-muted-foreground mb-1" data-testid="top-player">
                                {topPlayer ?? '?'}{topElo != null && ` (${topElo})`}
                            </div>
                            <Board
                                position={position}
                                orientation={orientation}
                                onMoveInput={handleMoveInput}
                                deviated={deviated}
                            />
                            <div className="text-sm font-medium text-muted-foreground mt-1 flex items-center justify-between">
                                <span data-testid="bottom-player">
                                    {bottomPlayer ?? '?'}{bottomElo != null && ` (${bottomElo})`}
                                </span>
                                {game.result && <span className="font-mono">{game.result}</span>}
                            </div>
                            <div className="flex items-center justify-center gap-2 mt-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handlePrevMove}
                                    disabled={deviated || currentMoveIndex < 0}
                                    data-testid="btn-prev"
                                    title="Coup précédent"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleNextMove}
                                    disabled={deviated || currentMoveIndex >= moves.length - 1}
                                    data-testid="btn-next"
                                    title="Coup suivant"
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleFlip}
                                    data-testid="btn-flip"
                                    title="Retourner l'échiquier"
                                >
                                    <ArrowUpDown className="h-4 w-4" />
                                </Button>
                                {deviated && (
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={handleReturnToGame}
                                        data-testid="btn-return"
                                    >
                                        Revenir à la partie
                                    </Button>
                                )}
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                className="w-full mt-3"
                                onClick={handleDownloadPgn}
                            >
                                <Download className="h-4 w-4 mr-2" />
                                Télécharger le PGN
                            </Button>
                        </CardContent>
                    </Card>
                </div>
                <div className="space-y-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>Analyse</CardTitle>
                            {game.openingName && (
                                <p className="text-sm text-muted-foreground">
                                    {shortenOpening(game.openingName)}
                                </p>
                            )}
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
                </div>
            </div>
        </div>
    );
};

export default GamePage;
