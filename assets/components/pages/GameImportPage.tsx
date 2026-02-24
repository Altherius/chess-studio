import React, { useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { ClipboardList, FolderUp, PenLine, Camera, Construction, ArrowLeft } from 'lucide-react';
import { Card, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import ImportPgnPaste from '../import/ImportPgnPaste';
import ImportPgnFile from '../import/ImportPgnFile';
import ImportForm from '../import/ImportForm';
import { AlertError } from '../ui/alert';

type ImportMethod = 'paste' | 'file' | 'form' | 'image' | null;

const methods: { key: ImportMethod; icon: React.FC<{ className?: string }>; title: string; description: string }[] = [
    {
        key: 'paste',
        icon: ClipboardList,
        title: 'Coller un PGN',
        description: 'Collez directement le contenu d\u2019un fichier PGN.',
    },
    {
        key: 'file',
        icon: FolderUp,
        title: 'Importer un fichier',
        description: 'Envoyez un fichier .pgn depuis votre ordinateur.',
    },
    {
        key: 'form',
        icon: PenLine,
        title: 'Saisie manuelle',
        description: 'Remplissez un formulaire avec les informations de la partie.',
    },
    {
        key: 'image',
        icon: Camera,
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
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                                <m.icon className="h-10 w-10 mx-auto mb-3 text-muted-foreground" />
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
                <ArrowLeft className="h-4 w-4 mr-1" />
                Choisir une autre methode
            </Button>

            {error && <AlertError message={error} />}

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
                        <Construction className="h-12 w-12 mx-auto mb-4 text-muted-foreground" />
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
