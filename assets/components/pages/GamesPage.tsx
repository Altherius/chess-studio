import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import type { GameSummary, PaginatedResponse } from '../../types/chess';

const PAGE_SIZE = 20;

interface GameListState {
    games: GameSummary[];
    total: number;
    loading: boolean;
    loadingMore: boolean;
}

const initialState: GameListState = {
    games: [],
    total: 0,
    loading: true,
    loadingMore: false,
};

function GameCard({ game, onClick }: { game: GameSummary; onClick: () => void }) {
    return (
        <Card
            className="cursor-pointer hover:bg-accent/50 transition-colors"
            onClick={onClick}
        >
            <CardContent className="py-3 px-4 flex items-center justify-between">
                <div>
                    <p className="font-medium">
                        {game.playerWhite ?? '?'} vs {game.playerBlack ?? '?'}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {[game.event, game.date].filter(Boolean).join(' — ') || 'Partie importée'}
                    </p>
                </div>
                <span className="text-sm font-mono text-muted-foreground">
                    {game.result ?? ''}
                </span>
            </CardContent>
        </Card>
    );
}

const GamesPage: React.FC = () => {
    const [my, setMy] = useState<GameListState>(initialState);
    const [pub, setPub] = useState<GameListState>(initialState);
    const mySentinelRef = useRef<HTMLDivElement>(null);
    const pubSentinelRef = useRef<HTMLDivElement>(null);
    const navigate = useNavigate();

    const fetchList = useCallback(
        async (
            url: string,
            offset: number,
            append: boolean,
            setter: React.Dispatch<React.SetStateAction<GameListState>>,
        ) => {
            setter((prev) => (append ? { ...prev, loadingMore: true } : { ...prev, loading: true }));
            try {
                const res = await fetch(`${url}?offset=${offset}&limit=${PAGE_SIZE}`);
                if (!res.ok) throw new Error();
                const data: PaginatedResponse<GameSummary> = await res.json();
                setter((prev) => ({
                    games: append ? [...prev.games, ...data.items] : data.items,
                    total: data.total,
                    loading: false,
                    loadingMore: false,
                }));
            } catch {
                setter((prev) => ({ ...prev, loading: false, loadingMore: false }));
            }
        },
        [],
    );

    useEffect(() => {
        fetchList('/api/games', 0, false, setMy);
        fetchList('/api/games/public', 0, false, setPub);
    }, [fetchList]);

    // Infinite scroll — my games
    useEffect(() => {
        const sentinel = mySentinelRef.current;
        if (!sentinel) return;
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && !my.loadingMore && my.games.length < my.total) {
                    fetchList('/api/games', my.games.length, true, setMy);
                }
            },
            { threshold: 0.1 },
        );
        observer.observe(sentinel);
        return () => observer.disconnect();
    }, [my.games.length, my.total, my.loadingMore, fetchList]);

    // Infinite scroll — public games
    useEffect(() => {
        const sentinel = pubSentinelRef.current;
        if (!sentinel) return;
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && !pub.loadingMore && pub.games.length < pub.total) {
                    fetchList('/api/games/public', pub.games.length, true, setPub);
                }
            },
            { threshold: 0.1 },
        );
        observer.observe(sentinel);
        return () => observer.disconnect();
    }, [pub.games.length, pub.total, pub.loadingMore, fetchList]);

    const renderList = (
        state: GameListState,
        sentinelRef: React.RefObject<HTMLDivElement>,
        emptyMessage: string,
    ) => {
        if (state.loading) {
            return <p className="text-muted-foreground text-center py-8">Chargement...</p>;
        }

        if (state.games.length === 0) {
            return (
                <Card>
                    <CardContent className="py-8 text-center text-muted-foreground">
                        {emptyMessage}
                    </CardContent>
                </Card>
            );
        }

        return (
            <div className="space-y-2">
                {state.games.map((game) => (
                    <GameCard
                        key={game.id}
                        game={game}
                        onClick={() => navigate(`/games/${game.id}`)}
                    />
                ))}
                <div ref={sentinelRef} className="h-4" />
                {state.loadingMore && (
                    <p className="text-center text-sm text-muted-foreground py-4">
                        Chargement...
                    </p>
                )}
            </div>
        );
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h2 className="text-2xl font-bold">Parties</h2>
                <Button onClick={() => navigate('/games/import')}>Importer</Button>
            </div>

            <div className="grid grid-cols-2 gap-6">
                <div className="space-y-4">
                    <Card>
                        <CardHeader className="py-3">
                            <CardTitle className="text-lg">Mes parties</CardTitle>
                        </CardHeader>
                    </Card>
                    {renderList(
                        my,
                        mySentinelRef,
                        'Aucune partie. Cliquez sur « Importer » pour ajouter votre première partie.',
                    )}
                </div>

                <div className="space-y-4">
                    <Card>
                        <CardHeader className="py-3">
                            <CardTitle className="text-lg">Parties publiques</CardTitle>
                        </CardHeader>
                    </Card>
                    {renderList(
                        pub,
                        pubSentinelRef,
                        'Aucune partie publique pour le moment.',
                    )}
                </div>
            </div>
        </div>
    );
};

export default GamesPage;
