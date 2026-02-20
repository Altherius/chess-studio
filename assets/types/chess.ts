export interface Game {
    id: number;
    pgn: string;
    playerWhite: string | null;
    playerBlack: string | null;
    result: string | null;
    event: string | null;
    date: string | null;
    createdAt: string;
    analyses?: AnalysisResult[];
}

export interface GameSummary {
    id: number;
    playerWhite: string | null;
    playerBlack: string | null;
    result: string | null;
    event: string | null;
    date: string | null;
    createdAt: string;
}

export interface AnalysisResult {
    id: number;
    depth: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    evaluation: Record<number, string> | null;
    bestMoves: Record<number, string> | null;
    createdAt: string;
}

export interface StockfishEvaluation {
    score: number | string;
    bestMove: string;
    pv: string;
    depth: number;
    multipv: number;
}
