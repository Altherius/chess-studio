import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { CheckCircle, XCircle } from 'lucide-react';
import { Button } from '../ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../ui/table';

interface UserRow {
    id: number;
    email: string;
    firstName: string | null;
    lastName: string | null;
    active: boolean;
    roles: string[];
}

const UsersPage: React.FC = () => {
    const [users, setUsers] = useState<UserRow[]>([]);
    const [loading, setLoading] = useState(true);

    const fetchUsers = useCallback(async () => {
        try {
            const res = await fetch('/api/admin/users');
            if (res.ok) {
                setUsers(await res.json());
            }
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchUsers();
    }, [fetchUsers]);

    const toggleActive = async (id: number) => {
        const res = await fetch(`/api/admin/users/${id}/toggle-active`, { method: 'PATCH' });
        if (res.ok) {
            const data = await res.json();
            setUsers((prev) => prev.map((u) => (u.id === data.id ? { ...u, active: data.active } : u)));
        }
    };

    if (loading) {
        return <p className="text-muted-foreground">Chargement...</p>;
    }

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold">Gestion des utilisateurs</h1>
                <Link to="/users/create">
                    <Button>Ajouter un utilisateur</Button>
                </Link>
            </div>

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
                    {users.map((u) => (
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
        </div>
    );
};

export default UsersPage;
