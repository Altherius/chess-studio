import React, { useMemo, useState, useEffect } from 'react';
import { Chess } from 'chess.js';
import { useMercure } from '../hooks/useMercure';
import { Button } from './ui/button';
import type { StockfishEvaluation, AnalysisResult } from '../types/chess';
import { sanToFrench } from '../lib/chess';

function uciToFrenchSan(fen: string, uciMoves: string[]): string[] {
    const chess = new Chess(fen);
    const result: string[] = [];

    for (const uci of uciMoves) {
        try {
            const move = chess.move({
                from: uci.slice(0, 2),
                to: uci.slice(2, 4),
                promotion: uci.length > 4 ? uci[4] : undefined,
            });
            const french = sanToFrench(move.san);
            result.push(french);
        } catch {
            break;
        }
    }

    return result;
}

interface AnalysisProps {
    lines: StockfishEvaluation[];
    isAnalyzing: boolean;
    gameId: number;
    fen: string;
    currentMoveIndex: number;
    serverAnalyses?: AnalysisResult[];
}

const scoreToPercent = (score: number | string): number => {
    if (typeof score === 'string' && score.startsWith('M')) {
        const mateIn = parseInt(score.substring(1));
        return mateIn > 0 ? 95 : 5;
    }
    const numScore = typeof score === 'number' ? score : parseFloat(score);
    const clamped = Math.max(-5, Math.min(5, numScore));
    return 50 + (clamped / 5) * 50;
};

const formatScore = (score: number | string): string => {
    if (typeof score === 'string' && score.startsWith('M')) {
        return `#${score.substring(1)}`;
    }
    const num = typeof score === 'number' ? score : parseFloat(score);
    return num >= 0 ? `+${num.toFixed(2)}` : num.toFixed(2);
};

const Analysis: React.FC<AnalysisProps> = ({ lines, isAnalyzing, gameId, fen, currentMoveIndex, serverAnalyses }) => {
    const topic = gameId ? `game/${gameId}/analysis` : null;
    const mercureUpdate = useMercure(topic);

    // Track server analysis state, updated by initial data + Mercure
    const [serverStatus, setServerStatus] = useState<'none' | 'running' | 'completed'>('none');
    const [completedAnalysis, setCompletedAnalysis] = useState<AnalysisResult | null>(null);

    // Initialize from server analyses on mount / when they change
    useEffect(() => {
        if (!serverAnalyses || serverAnalyses.length === 0) {
            setServerStatus('none');
            setCompletedAnalysis(null);
            return;
        }

        const completed = serverAnalyses.find((a) => a.status === 'completed');
        if (completed) {
            setServerStatus('completed');
            setCompletedAnalysis(completed);
            return;
        }

        const running = serverAnalyses.find((a) => a.status === 'running' || a.status === 'pending');
        if (running) {
            setServerStatus('running');
            return;
        }

        setServerStatus('none');
    }, [serverAnalyses]);

    // Handle Mercure updates
    useEffect(() => {
        if (!mercureUpdate) return;

        if (mercureUpdate.status === 'completed') {
            setServerStatus('completed');
            setCompletedAnalysis({
                id: mercureUpdate.analysisId,
                depth: 0,
                status: 'completed',
                evaluation: mercureUpdate.evaluation,
                bestMoves: mercureUpdate.bestMoves,
                createdAt: '',
            });
        } else if (mercureUpdate.status === 'running') {
            setServerStatus('running');
        } else if (mercureUpdate.status === 'failed') {
            setServerStatus('none');
        }
    }, [mercureUpdate]);

    const handleServerAnalysis = () => {
        setServerStatus('running');
        fetch(`/api/analysis/game/${gameId}`, { method: 'POST' })
            .catch(() => setServerStatus('none'));
    };

    const frenchLines = useMemo(() =>
        lines.map((line) => uciToFrenchSan(fen, line.pv.split(' ').slice(0, 6))),
        [lines, fen],
    );

    const best = lines[0] ?? null;

    // Server analysis data for the current move
    const serverMoveScore = completedAnalysis?.evaluation?.[currentMoveIndex] ?? null;
    const serverMoveBest = completedAnalysis?.bestMoves?.[currentMoveIndex] ?? null;
    const serverBestFrench = useMemo(() => {
        if (!serverMoveBest || !fen) return null;
        const moves = uciToFrenchSan(fen, [serverMoveBest]);
        return moves[0] ?? null;
    }, [serverMoveBest, fen]);

    return (
        <div>
            {/* Client-side analysis */}
            {best && (
                <>
                    <div className="h-6 bg-zinc-800 rounded overflow-hidden mb-3">
                        <div
                            className="h-full bg-zinc-100 transition-[width] duration-300"
                            style={{ width: `${scoreToPercent(best.score)}%` }}
                        />
                    </div>
                    <div className="text-xl font-bold text-center py-2">
                        {formatScore(best.score)}
                    </div>
                    <div className="text-sm text-muted-foreground text-center mb-3">
                        Profondeur : {best.depth}
                    </div>
                </>
            )}

            {lines.length > 0 && (
                <div className="space-y-2 mb-3">
                    {lines.map((line, i) => (
                        <div key={line.multipv} className="flex items-start gap-2 font-mono text-sm p-2 bg-accent rounded">
                            <span className="shrink-0 font-bold text-muted-foreground">{line.multipv}.</span>
                            <span className="shrink-0 font-bold min-w-[4rem] text-right">{formatScore(line.score)}</span>
                            <span className="text-muted-foreground truncate">
                                {frenchLines[i]?.join(' ')}
                            </span>
                        </div>
                    ))}
                </div>
            )}

            {isAnalyzing && (
                <p className="text-center text-muted-foreground">Analyse en cours...</p>
            )}

            {/* Server-side deep analysis */}
            <div className="mt-4 pt-4 border-t border-border">
                <h3 className="text-sm font-semibold mb-3">Analyse profonde (serveur)</h3>

                {serverStatus === 'completed' && serverMoveScore !== null && (
                    <div className="space-y-2">
                        <div className="h-5 bg-zinc-800 rounded overflow-hidden">
                            <div
                                className="h-full bg-zinc-100 transition-[width] duration-300"
                                style={{ width: `${scoreToPercent(serverMoveScore)}%` }}
                            />
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="font-bold font-mono">{formatScore(serverMoveScore)}</span>
                            {serverBestFrench && (
                                <span className="text-muted-foreground">
                                    Meilleur coup : <span className="font-mono font-semibold text-foreground">{serverBestFrench}</span>
                                </span>
                            )}
                        </div>
                    </div>
                )}

                {serverStatus === 'completed' && serverMoveScore === null && (
                    <p className="text-sm text-muted-foreground">Pas de donnees pour ce coup.</p>
                )}

                {serverStatus === 'running' && (
                    <Button disabled className="w-full opacity-70">
                        <span className="inline-block mr-2 h-4 w-4 rounded-full border-2 border-current border-t-transparent animate-spin" />
                        Analyse en cours...
                    </Button>
                )}

                {serverStatus === 'none' && (
                    <Button onClick={handleServerAnalysis} className="w-full">
                        Analyse profonde
                    </Button>
                )}
            </div>
        </div>
    );
};

export default Analysis;
