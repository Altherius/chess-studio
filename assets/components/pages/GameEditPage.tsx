import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import { AlertError, AlertSuccess } from '../ui/alert';
import type { Game } from '../../types/chess';

const inputClass =
    'w-full rounded-md border border-input bg-accent px-3 py-2 text-sm text-foreground ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const GameEditPage: React.FC = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    const [form, setForm] = useState({
        playerWhite: '',
        playerBlack: '',
        whiteElo: '',
        blackElo: '',
        event: '',
        date: '',
        round: '',
        result: '',
        isPublic: true,
    });

    useEffect(() => {
        (async () => {
            try {
                const res = await fetch(`/api/games/${id}`);
                if (!res.ok) throw new Error('Partie introuvable');
                const data: Game = await res.json();

                if (!data.isOwner) {
                    navigate(`/games/${id}`, { replace: true });
                    return;
                }

                setForm({
                    playerWhite: data.playerWhite ?? '',
                    playerBlack: data.playerBlack ?? '',
                    whiteElo: data.whiteElo != null ? String(data.whiteElo) : '',
                    blackElo: data.blackElo != null ? String(data.blackElo) : '',
                    event: data.event ?? '',
                    date: data.date ?? '',
                    round: data.round ?? '',
                    result: data.result ?? '',
                    isPublic: data.isPublic,
                });
            } catch (err: any) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        })();
    }, [id, navigate]);

    const set = (key: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
        setForm((f) => ({ ...f, [key]: e.target.value }));

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setSuccess('');
        setSaving(true);

        try {
            const body: Record<string, unknown> = {
                playerWhite: form.playerWhite || null,
                playerBlack: form.playerBlack || null,
                whiteElo: form.whiteElo ? parseInt(form.whiteElo, 10) : null,
                blackElo: form.blackElo ? parseInt(form.blackElo, 10) : null,
                event: form.event || null,
                date: form.date || null,
                round: form.round || null,
                result: form.result || null,
                isPublic: form.isPublic,
            };

            const res = await fetch(`/api/games/${id}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => null);
                throw new Error(data?.error ?? 'Erreur lors de la sauvegarde');
            }

            navigate(`/games/${id}`);
        } catch (err: any) {
            setError(err.message);
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center py-20">
                <p className="text-muted-foreground">Chargement...</p>
            </div>
        );
    }

    return (
        <div className="max-w-2xl mx-auto">
            <div className="mb-4">
                <Button variant="ghost" size="sm" asChild>
                    <Link to={`/games/${id}`}><ArrowLeft className="inline h-4 w-4 mr-1" />Retour à la partie</Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Modifier la partie</CardTitle>
                </CardHeader>
                <CardContent>
                    {error && <AlertError message={error} />}
                    {success && <AlertSuccess message={success} />}

                    <form onSubmit={handleSubmit} className="space-y-4 mt-2">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="text-sm font-medium mb-1 block">Joueur Blancs</label>
                                <input className={inputClass} value={form.playerWhite} onChange={set('playerWhite')} placeholder="Nom" />
                            </div>
                            <div>
                                <label className="text-sm font-medium mb-1 block">Joueur Noirs</label>
                                <input className={inputClass} value={form.playerBlack} onChange={set('playerBlack')} placeholder="Nom" />
                            </div>
                            <div>
                                <label className="text-sm font-medium mb-1 block">ELO Blancs</label>
                                <input className={inputClass} type="number" value={form.whiteElo} onChange={set('whiteElo')} placeholder="1500" />
                            </div>
                            <div>
                                <label className="text-sm font-medium mb-1 block">ELO Noirs</label>
                                <input className={inputClass} type="number" value={form.blackElo} onChange={set('blackElo')} placeholder="1500" />
                            </div>
                            <div>
                                <label className="text-sm font-medium mb-1 block">Evénement</label>
                                <input className={inputClass} value={form.event} onChange={set('event')} placeholder="Tournoi..." />
                            </div>
                            <div>
                                <label className="text-sm font-medium mb-1 block">Date</label>
                                <input className={inputClass} type="date" value={form.date} onChange={set('date')} />
                            </div>
                            <div>
                                <label className="text-sm font-medium mb-1 block">Ronde</label>
                                <input className={inputClass} value={form.round} onChange={set('round')} placeholder="1" />
                            </div>
                            <div>
                                <label className="text-sm font-medium mb-1 block">Résultat</label>
                                <select className={inputClass} value={form.result} onChange={set('result')}>
                                    <option value="">--</option>
                                    <option value="1-0">1-0</option>
                                    <option value="0-1">0-1</option>
                                    <option value="1/2-1/2">1/2-1/2</option>
                                    <option value="*">*</option>
                                </select>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="edit-public"
                                checked={form.isPublic}
                                onChange={(e) => setForm((f) => ({ ...f, isPublic: e.target.checked }))}
                                className="h-4 w-4 rounded border-input accent-primary"
                            />
                            <label htmlFor="edit-public" className="text-sm cursor-pointer">
                                Partie publique
                            </label>
                        </div>

                        <Button type="submit" disabled={saving}>
                            {saving ? 'Enregistrement...' : 'Enregistrer'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
};

export default GameEditPage;
