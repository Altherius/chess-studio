import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '../ui/table';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '../ui/tabs';
import type { GameSummary, PaginatedResponse } from '../../types/chess';

const PAGE_SIZE = 50;

interface Filters {
    minElo: string;
    maxElo: string;
    player: string;
    event: string;
    minDate: string;
    maxDate: string;
}

const emptyFilters: Filters = {
    minElo: '',
    maxElo: '',
    player: '',
    event: '',
    minDate: '',
    maxDate: '',
};

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

function formatDate(date: string | null): string {
    if (!date) return '';
    const [y, m, d] = date.split('-');
    return `${d}/${m}/${y}`;
}

function formatPlayer(name: string | null, elo: number | null): string {
    const display = name ?? '?';
    return elo ? `${display} (${elo})` : display;
}

function formatEvent(event: string | null, round: string | null): string {
    if (!event && !round) return '';
    const parts = [event];
    if (round) parts.push(`Ronde ${round}`);
    return parts.filter(Boolean).join(' — ');
}

function buildFilterParams(filters: Filters): string {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value !== '') {
            params.set(key, value);
        }
    }
    return params.toString();
}

const GamesPage: React.FC = () => {
    const [my, setMy] = useState<GameListState>(initialState);
    const [pub, setPub] = useState<GameListState>(initialState);
    const [filters, setFilters] = useState<Filters>(emptyFilters);
    const [debouncedFilters, setDebouncedFilters] = useState<Filters>(emptyFilters);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>();
    const navigate = useNavigate();

    const handleFilterChange = (key: keyof Filters, value: string) => {
        setFilters((prev) => ({ ...prev, [key]: value }));
    };

    // Debounce filters
    useEffect(() => {
        debounceRef.current = setTimeout(() => {
            setDebouncedFilters(filters);
        }, 300);
        return () => clearTimeout(debounceRef.current);
    }, [filters]);

    const fetchList = useCallback(
        async (
            url: string,
            offset: number,
            append: boolean,
            setter: React.Dispatch<React.SetStateAction<GameListState>>,
            filterParams: string,
        ) => {
            setter((prev) => (append ? { ...prev, loadingMore: true } : { ...prev, loading: true }));
            try {
                const sep = filterParams ? '&' : '';
                const res = await fetch(`${url}?offset=${offset}&limit=${PAGE_SIZE}${sep}${filterParams}`);
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

    // Reload both lists when debounced filters change
    useEffect(() => {
        const filterParams = buildFilterParams(debouncedFilters);
        fetchList('/api/games', 0, false, setMy, filterParams);
        fetchList('/api/games/public', 0, false, setPub, filterParams);
    }, [fetchList, debouncedFilters]);

    const loadMore = (
        url: string,
        state: GameListState,
        setter: React.Dispatch<React.SetStateAction<GameListState>>,
    ) => {
        const filterParams = buildFilterParams(debouncedFilters);
        fetchList(url, state.games.length, true, setter, filterParams);
    };

    const renderTable = (
        state: GameListState,
        emptyMessage: string,
        url: string,
        setter: React.Dispatch<React.SetStateAction<GameListState>>,
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
            <div>
                <Card>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Blancs</TableHead>
                                <TableHead>Noirs</TableHead>
                                <TableHead>Résultat</TableHead>
                                <TableHead>Événement</TableHead>
                                <TableHead>Date</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {state.games.map((game) => (
                                <TableRow
                                    key={game.id}
                                    className="cursor-pointer"
                                    onClick={() => navigate(`/games/${game.id}`)}
                                >
                                    <TableCell>{formatPlayer(game.playerWhite, game.whiteElo)}</TableCell>
                                    <TableCell>{formatPlayer(game.playerBlack, game.blackElo)}</TableCell>
                                    <TableCell className="font-mono">{game.result ?? ''}</TableCell>
                                    <TableCell>{formatEvent(game.event, game.round)}</TableCell>
                                    <TableCell>{formatDate(game.date)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
                {state.games.length < state.total && (
                    <div className="text-center mt-4">
                        <Button
                            variant="outline"
                            onClick={() => loadMore(url, state, setter)}
                            disabled={state.loadingMore}
                        >
                            {state.loadingMore ? 'Chargement...' : 'Charger plus'}
                        </Button>
                    </div>
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

            <Card>
                <CardContent className="py-3">
                    <div className="grid grid-cols-6 gap-3">
                        <Input
                            type="number"
                            placeholder="Élo min"
                            value={filters.minElo}
                            onChange={(e) => handleFilterChange('minElo', e.target.value)}
                        />
                        <Input
                            type="number"
                            placeholder="Élo max"
                            value={filters.maxElo}
                            onChange={(e) => handleFilterChange('maxElo', e.target.value)}
                        />
                        <Input
                            placeholder="Joueur"
                            value={filters.player}
                            onChange={(e) => handleFilterChange('player', e.target.value)}
                        />
                        <Input
                            placeholder="Événement"
                            value={filters.event}
                            onChange={(e) => handleFilterChange('event', e.target.value)}
                        />
                        <Input
                            type="date"
                            placeholder="Date min"
                            value={filters.minDate}
                            onChange={(e) => handleFilterChange('minDate', e.target.value)}
                        />
                        <Input
                            type="date"
                            placeholder="Date max"
                            value={filters.maxDate}
                            onChange={(e) => handleFilterChange('maxDate', e.target.value)}
                        />
                    </div>
                </CardContent>
            </Card>

            <Tabs defaultValue="my">
                <TabsList>
                    <TabsTrigger value="my">Mes parties</TabsTrigger>
                    <TabsTrigger value="public">Parties publiques</TabsTrigger>
                </TabsList>
                <TabsContent value="my">
                    {renderTable(
                        my,
                        'Aucune partie. Cliquez sur « Importer » pour ajouter votre première partie.',
                        '/api/games',
                        setMy,
                    )}
                </TabsContent>
                <TabsContent value="public">
                    {renderTable(
                        pub,
                        'Aucune partie publique pour le moment.',
                        '/api/games/public',
                        setPub,
                    )}
                </TabsContent>
            </Tabs>
        </div>
    );
};

export default GamesPage;
