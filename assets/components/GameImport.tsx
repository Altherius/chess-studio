import React, { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from './ui/card';
import { Button } from './ui/button';
import { Textarea } from './ui/textarea';

interface GameImportProps {
    onImport: (pgn: string, isPublic: boolean) => void;
}

const GameImport: React.FC<GameImportProps> = ({ onImport }) => {
    const [pgn, setPgn] = useState('');
    const [isPublic, setIsPublic] = useState(true);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (pgn.trim()) {
            onImport(pgn.trim(), isPublic);
        }
    };

    return (
        <Card className="max-w-[700px] mx-auto">
            <CardHeader>
                <CardTitle>Importer un PGN</CardTitle>
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
                            id="is-public"
                            checked={isPublic}
                            onChange={(e) => setIsPublic(e.target.checked)}
                            className="h-4 w-4 rounded border-input accent-primary"
                        />
                        <label htmlFor="is-public" className="text-sm cursor-pointer">
                            Partie publique
                        </label>
                    </div>
                    <div className="flex gap-2 mt-3">
                        <Button type="submit" disabled={!pgn.trim()}>
                            Importer la partie
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
};

export default GameImport;
