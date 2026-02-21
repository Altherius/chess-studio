import React, { useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import ImportPgnPaste from '../import/ImportPgnPaste';
import ImportPgnFile from '../import/ImportPgnFile';
import ImportForm from '../import/ImportForm';

type ImportMethod = 'paste' | 'file' | 'form' | 'image' | null;

const methods: { key: ImportMethod; icon: string; title: string; description: string }[] = [
    {
        key: 'paste',
        icon: '\u{1F4CB}',
        title: 'Coller un PGN',
        description: 'Collez directement le contenu d\u2019un fichier PGN.',
    },
    {
        key: 'file',
        icon: '\u{1F4C1}',
        title: 'Importer un fichier',
        description: 'Envoyez un fichier .pgn depuis votre ordinateur.',
    },
    {
        key: 'form',
        icon: '\u{270D}\uFE0F',
        title: 'Saisie manuelle',
        description: 'Remplissez un formulaire avec les informations de la partie.',
    },
    {
        key: 'image',
        icon: '\u{1F4F7}',
        title: 'Photo de feuille',
        description: 'Importez une photo de votre feuille de partie.',
    },
];

const GameImportPage: React.FC = () => {
    const navigate = useNavigate();
    const [selected, setSelected] = useState<ImportMethod>(null);
    const [error, setError] = useState('');

    const handleSuccess = useCallback((data: { id: number }) => {
        navigate(`/games/${data.id}`);
    }, [navigate]);

    const handleError = useCallback((msg: string) => {
        setError(msg);
    }, []);

    if (!selected) {
        return (
            <div className="max-w-[800px] mx-auto">
                <h1 className="text-2xl font-bold mb-6">Importer une partie</h1>
                <div className="grid grid-cols-2 gap-4">
                    {methods.map((m) => (
                        <Card
                            key={m.key}
                            className="cursor-pointer hover:ring-1 hover:ring-primary transition-shadow"
                            onClick={() => {
                                setError('');
                                setSelected(m.key);
                            }}
                        >
                            <CardContent className="p-6 text-center">
                                <div className="text-4xl mb-3">{m.icon}</div>
                                <h2 className="text-lg font-semibold mb-1">{m.title}</h2>
                                <p className="text-sm text-muted-foreground">{m.description}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div className="max-w-[700px] mx-auto">
            <Button
                variant="ghost"
                size="sm"
                className="mb-4"
                onClick={() => { setSelected(null); setError(''); }}
            >
                &larr; Choisir une autre methode
            </Button>

            {error && (
                <div className="mb-4 p-3 rounded-md bg-destructive/10 text-destructive text-sm">
                    {error}
                </div>
            )}

            {selected === 'paste' && (
                <ImportPgnPaste onSuccess={handleSuccess} onError={handleError} />
            )}
            {selected === 'file' && (
                <ImportPgnFile onSuccess={handleSuccess} onError={handleError} />
            )}
            {selected === 'form' && (
                <ImportForm onSuccess={handleSuccess} onError={handleError} />
            )}
            {selected === 'image' && (
                <Card>
                    <CardContent className="p-10 text-center">
                        <div className="text-5xl mb-4">{'\u{1F6A7}'}</div>
                        <h2 className="text-lg font-semibold mb-2">Fonctionnalite en cours de developpement</h2>
                        <p className="text-sm text-muted-foreground">
                            L&apos;import par photo de feuille de partie sera bientot disponible.
                        </p>
                    </CardContent>
                </Card>
            )}
        </div>
    );
};

export default GameImportPage;
