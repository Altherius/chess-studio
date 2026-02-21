import React, { useState, useRef } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { Button } from '../ui/button';

interface Props {
    onSuccess: (data: { id: number }) => void;
    onError: (msg: string) => void;
}

const ImportPgnFile: React.FC<Props> = ({ onSuccess, onError }) => {
    const [file, setFile] = useState<File | null>(null);
    const [isPublic, setIsPublic] = useState(true);
    const [loading, setLoading] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const f = e.target.files?.[0] ?? null;
        if (f && !f.name.toLowerCase().endsWith('.pgn')) {
            onError('Seuls les fichiers .pgn sont acceptes.');
            setFile(null);
            return;
        }
        setFile(f);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!file) return;

        setLoading(true);
        try {
            const formData = new FormData();
            formData.append('pgn', file);
            formData.append('isPublic', String(isPublic));

            const res = await fetch('/api/games/import/file', {
                method: 'POST',
                body: formData,
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
                <CardTitle>Importer un fichier PGN</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit}>
                    <div
                        className="border-2 border-dashed border-input rounded-md p-8 text-center cursor-pointer hover:border-primary transition-colors"
                        onClick={() => inputRef.current?.click()}
                    >
                        <input
                            ref={inputRef}
                            type="file"
                            accept=".pgn"
                            onChange={handleFileChange}
                            className="hidden"
                        />
                        {file ? (
                            <p className="text-sm">{file.name}</p>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Cliquez pour selectionner un fichier .pgn
                            </p>
                        )}
                    </div>
                    <div className="flex items-center gap-2 mt-3">
                        <input
                            type="checkbox"
                            id="file-public"
                            checked={isPublic}
                            onChange={(e) => setIsPublic(e.target.checked)}
                            className="h-4 w-4 rounded border-input accent-primary"
                        />
                        <label htmlFor="file-public" className="text-sm cursor-pointer">
                            Partie publique
                        </label>
                    </div>
                    <div className="mt-3">
                        <Button type="submit" disabled={!file || loading}>
                            {loading ? 'Import en cours...' : 'Importer la partie'}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
};

export default ImportPgnFile;
