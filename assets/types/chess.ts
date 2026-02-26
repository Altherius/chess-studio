export interface Game {
    id: number;
    pgn: string;
    playerWhite: string | null;
    playerBlack: string | null;
    result: string | null;
    event: string | null;
    date: string | null;
    createdAt: string;
    openingName: string | null;
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
    isPublic: boolean;
    whiteElo: number | null;
    blackElo: number | null;
    round: string | null;
    openingName: string | null;
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

export interface User {
    id: number;
    email: string;
    firstName: string | null;
    lastName: string | null;
    roles: string[];
    mustChangePassword: boolean;
    active: boolean;
}

export interface PaginatedResponse<T> {
    items: T[];
    total: number;
    offset: number;
    limit: number;
}
