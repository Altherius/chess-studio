import React, { useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import GameImport from '../GameImport';

const GameImportPage: React.FC = () => {
    const navigate = useNavigate();

    const handleImport = useCallback(async (pgn: string, isPublic: boolean) => {
        try {
            const response = await fetch('/api/games/import', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pgn, isPublic }),
            });

            if (!response.ok) throw new Error('Import failed');

            const data = await response.json();
            navigate(`/games/${data.id}`);
        } catch (err) {
            console.error('Failed to import game:', err);
        }
    }, [navigate]);

    return <GameImport onImport={handleImport} />;
};

export default GameImportPage;
