import React, { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import { Textarea } from '../ui/textarea';

interface Props {
    onSuccess: (data: { id: number }) => void;
    onError: (msg: string) => void;
}

const inputClass =
    'w-full rounded-md border border-input bg-accent px-3 py-2 text-sm text-foreground ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const ImportForm: React.FC<Props> = ({ onSuccess, onError }) => {
    const [form, setForm] = useState({
        event: '',
        date: '',
        round: '',
        result: '',
        playerWhite: '',
        playerBlack: '',
        whiteElo: '',
        blackElo: '',
        moves: '',
    });
    const [isPublic, setIsPublic] = useState(true);
    const [loading, setLoading] = useState(false);

    const set = (key: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
        setForm((f) => ({ ...f, [key]: e.target.value }));

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!form.moves.trim()) {
            onError('Les coups sont obligatoires.');
            return;
        }

        setLoading(true);
        try {
            const body: Record<string, unknown> = {
                moves: form.moves.trim(),
                isPublic,
            };
            if (form.event) body.event = form.event;
            if (form.date) body.date = form.date;
            if (form.round) body.round = form.round;
            if (form.result) body.result = form.result;
            if (form.playerWhite) body.playerWhite = form.playerWhite;
            if (form.playerBlack) body.playerBlack = form.playerBlack;
            if (form.whiteElo) body.whiteElo = parseInt(form.whiteElo, 10);
            if (form.blackElo) body.blackElo = parseInt(form.blackElo, 10);

            const res = await fetch('/api/games/import/form', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => null);
                throw new Error(data?.error ?? 'Import failed');
            }

            onSuccess(await res.json());
        } catch (err: any) {
            onError(err.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Saisie manuelle</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
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
                            <label className="text-sm font-medium mb-1 block">Evenement</label>
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
                            <label className="text-sm font-medium mb-1 block">Resultat</label>
                            <select className={inputClass} value={form.result} onChange={set('result')}>
                                <option value="">--</option>
                                <option value="1-0">1-0</option>
                                <option value="0-1">0-1</option>
                                <option value="1/2-1/2">1/2-1/2</option>
                                <option value="*">*</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="text-sm font-medium mb-1 block">Coups *</label>
                        <Textarea
                            value={form.moves}
                            onChange={set('moves')}
                            placeholder="1. e4 e5 2. Nf3 Nc6 ..."
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="form-public"
                            checked={isPublic}
                            onChange={(e) => setIsPublic(e.target.checked)}
                            className="h-4 w-4 rounded border-input accent-primary"
                        />
                        <label htmlFor="form-public" className="text-sm cursor-pointer">
                            Partie publique
                        </label>
                    </div>

                    <Button type="submit" disabled={!form.moves.trim() || loading}>
                        {loading ? 'Import en cours...' : 'Importer la partie'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
};

export default ImportForm;
