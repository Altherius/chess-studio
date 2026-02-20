import React, { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from './ui/card';
import { Button } from './ui/button';
import { Textarea } from './ui/textarea';

interface GameImportProps {
    onImport: (pgn: string) => void;
}

const GameImport: React.FC<GameImportProps> = ({ onImport }) => {
    const [pgn, setPgn] = useState('');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (pgn.trim()) {
            onImport(pgn.trim());
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
