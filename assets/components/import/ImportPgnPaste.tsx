import React, { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import { Textarea } from '../ui/textarea';

interface Props {
    onSuccess: (data: { id: number }) => void;
    onError: (msg: string) => void;
}

const ImportPgnPaste: React.FC<Props> = ({ onSuccess, onError }) => {
    const [pgn, setPgn] = useState('');
    const [isPublic, setIsPublic] = useState(true);
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!pgn.trim()) return;

        setLoading(true);
        try {
            const res = await fetch('/api/games/import', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pgn: pgn.trim(), isPublic }),
            });

            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.error ?? 'Import failed');
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
                <CardTitle>Coller un PGN</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit}>
                    <Textarea
                        value={pgn}
                        onChange={(e) => setPgn(e.target.value)}
                        placeholder="Copiez votre PGN ici..."
                    />
                    <div className="flex items-center gap-2 mt-3">
                        <input
                            type="checkbox"
                            id="paste-public"
                            checked={isPublic}
                            onChange={(e) => setIsPublic(e.target.checked)}
                            className="h-4 w-4 rounded border-input accent-primary"
                        />
                        <label htmlFor="paste-public" className="text-sm cursor-pointer">
                            Partie publique
                        </label>
                    </div>
                    <div className="mt-3">
                        <Button type="submit" disabled={!pgn.trim() || loading}>
                            {loading ? 'Import en cours...' : 'Importer la partie'}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
};

export default ImportPgnPaste;
