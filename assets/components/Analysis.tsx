import React, { useMemo } from 'react';
import { Chess } from 'chess.js';
import { useMercure } from '../hooks/useMercure';
import { Button } from './ui/button';
import type { StockfishEvaluation } from '../types/chess';
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
}

const Analysis: React.FC<AnalysisProps> = ({ lines, isAnalyzing, gameId, fen }) => {
    const topic = gameId ? `game/${gameId}/analysis` : null;
    const serverAnalysis = useMercure(topic);

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

    const handleServerAnalysis = () => {
        fetch(`/api/analysis/game/${gameId}`, { method: 'POST' })
            .catch(console.error);
    };

    const frenchLines = useMemo(() =>
        lines.map((line) => uciToFrenchSan(fen, line.pv.split(' ').slice(0, 6))),
        [lines, fen],
    );

    const best = lines[0] ?? null;

    return (
        <div>
            {best && (
                <>
                    <div className="h-6 bg-secondary rounded overflow-hidden mb-3">
                        <div
                            className="h-full bg-foreground transition-[width] duration-300"
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
                <p className="text-center text-muted-foreground">Analyzing...</p>
            )}

            <div className="mt-4">
                <Button onClick={handleServerAnalysis}>
                    Analyse profonde
                </Button>
            </div>

            {serverAnalysis && (
                <div className="mt-3">
                    <p className="text-muted-foreground text-sm">
                        Analyse profonde : {serverAnalysis.status}
                    </p>
                </div>
            )}
        </div>
    );
};

export default Analysis;
