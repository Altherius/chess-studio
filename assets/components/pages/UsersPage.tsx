import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import { CheckCircle, XCircle } from 'lucide-react';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Card, CardContent } from '../ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../ui/table';

const PAGE_SIZE = 50;

interface UserRow {
    id: number;
    email: string;
    firstName: string | null;
    lastName: string | null;
    active: boolean;
    roles: string[];
}

interface UserListState {
    users: UserRow[];
    total: number;
    loading: boolean;
    loadingMore: boolean;
}

const initialState: UserListState = {
    users: [],
    total: 0,
    loading: true,
    loadingMore: false,
};

const UsersPage: React.FC = () => {
    const [state, setState] = useState<UserListState>(initialState);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const debounceRef = useRef<ReturnType<typeof setTimeout>>();

    useEffect(() => {
        debounceRef.current = setTimeout(() => {
            setDebouncedSearch(search);
        }, 300);
        return () => clearTimeout(debounceRef.current);
    }, [search]);

    const fetchUsers = useCallback(async (offset: number, append: boolean, searchQuery: string) => {
        setState((prev) => append ? { ...prev, loadingMore: true } : { ...prev, loading: true });
        try {
            const params = new URLSearchParams({ offset: String(offset), limit: String(PAGE_SIZE) });
            if (searchQuery) params.set('search', searchQuery);

            const res = await fetch(`/api/admin/users?${params}`);
            if (!res.ok) throw new Error();
            const data = await res.json();
            setState((prev) => ({
                users: append ? [...prev.users, ...data.items] : data.items,
                total: data.total,
                loading: false,
                loadingMore: false,
            }));
        } catch {
            setState((prev) => ({ ...prev, loading: false, loadingMore: false }));
        }
    }, []);

    useEffect(() => {
        fetchUsers(0, false, debouncedSearch);
    }, [fetchUsers, debouncedSearch]);

    const loadMore = () => {
        fetchUsers(state.users.length, true, debouncedSearch);
    };

    const toggleActive = async (id: number) => {
        const res = await fetch(`/api/admin/users/${id}/toggle-active`, { method: 'PATCH' });
        if (res.ok) {
            const data = await res.json();
            setState((prev) => ({
                ...prev,
                users: prev.users.map((u) => (u.id === data.id ? { ...u, active: data.active } : u)),
            }));
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold">Gestion des utilisateurs</h1>
                <Link to="/users/create">
                    <Button>Ajouter un utilisateur</Button>
                </Link>
            </div>

            <Input
                placeholder="Rechercher par email ou nom..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
            />

            {state.loading ? (
                <p className="text-muted-foreground text-center py-8">Chargement...</p>
            ) : state.users.length === 0 ? (
                <Card>
                    <CardContent className="py-8 text-center text-muted-foreground">
                        {debouncedSearch ? 'Aucun utilisateur trouvé.' : 'Aucun utilisateur.'}
                    </CardContent>
                </Card>
            ) : (
                <div>
                    <Card>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Nom</TableHead>
                                    <TableHead>Statut</TableHead>
                                    <TableHead className="text-right">Action</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {state.users.map((u) => (
                                    <TableRow key={u.id}>
                                        <TableCell>{u.email}</TableCell>
                                        <TableCell>
                                            {[u.firstName, u.lastName].filter(Boolean).join(' ') || '—'}
                                        </TableCell>
                                        <TableCell>
                                            {u.active ? (
                                                <CheckCircle className="h-5 w-5 text-green-500" />
                                            ) : (
                                                <XCircle className="h-5 w-5 text-red-500" />
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => toggleActive(u.id)}
                                            >
                                                {u.active ? 'Désactiver' : 'Activer'}
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                    {state.users.length < state.total && (
                        <div className="text-center mt-4">
                            <Button
                                variant="outline"
                                onClick={loadMore}
                                disabled={state.loadingMore}
                            >
                                {state.loadingMore ? 'Chargement...' : 'Charger plus'}
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default UsersPage;
