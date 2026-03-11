import React, { useState, useRef } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import { Textarea } from '../ui/textarea';
import { AlertError } from '../ui/alert';
import { Upload, FlaskConical, AlertTriangle, Loader2 } from 'lucide-react';

interface Props {
    onSuccess: (data: { id: number }) => void;
    onError: (msg: string) => void;
}

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp', 'image/tiff'];
const MAX_SIZE = 20 * 1024 * 1024;

const inputClass =
    'w-full rounded-md border border-input bg-accent px-3 py-2 text-sm text-foreground ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

type Step = 'upload' | 'form';

const ImportOcr: React.FC<Props> = ({ onSuccess, onError }) => {
    const [step, setStep] = useState<Step>('upload');
    const [file, setFile] = useState<File | null>(null);
    const [ocrLoading, setOcrLoading] = useState(false);
    const [ocrError, setOcrError] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

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
    const [submitLoading, setSubmitLoading] = useState(false);

    const set = (key: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
        setForm((f) => ({ ...f, [key]: e.target.value }));

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const f = e.target.files?.[0] ?? null;
        setOcrError('');
        if (f) {
            if (!ACCEPTED_TYPES.includes(f.type)) {
                setOcrError('Format non supporté. Formats acceptés : JPG, PNG, WebP, BMP, TIFF.');
                setFile(null);
                return;
            }
            if (f.size > MAX_SIZE) {
                setOcrError('L\'image est trop volumineuse (maximum 20 Mo).');
                setFile(null);
                return;
            }
        }
        setFile(f);
    };

    const handleUpload = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!file) return;

        setOcrLoading(true);
        setOcrError('');
        try {
            const formData = new FormData();
            formData.append('image', file);

            const res = await fetch('/api/games/ocr', {
                method: 'POST',
                body: formData,
            });

            const data = await res.json().catch(() => null);
            if (!res.ok) {
                throw new Error(data?.error ?? 'Erreur lors de la reconnaissance.');
            }

            setForm((f) => ({ ...f, moves: data.moves || '' }));
            setStep('form');
        } catch (err: any) {
            setOcrError(err.message);
        } finally {
            setOcrLoading(false);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!form.moves.trim()) {
            onError('Les coups sont obligatoires.');
            return;
        }

        setSubmitLoading(true);
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
            setSubmitLoading(false);
        }
    };

    if (step === 'upload') {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        Photo de feuille de partie
                        <span className="inline-flex items-center gap-1 text-xs font-normal bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 px-2 py-0.5 rounded-full">
                            <FlaskConical className="h-3 w-3" />
                            Expérimental
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleUpload} className="space-y-4">
                        <div
                            className="border-2 border-dashed border-input rounded-md p-8 text-center cursor-pointer hover:border-primary transition-colors"
                            onClick={() => inputRef.current?.click()}
                        >
                            <input
                                ref={inputRef}
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/bmp,image/tiff"
                                onChange={handleFileChange}
                                className="hidden"
                            />
                            {file ? (
                                <div className="flex items-center justify-center gap-2">
                                    <Upload className="h-5 w-5 text-muted-foreground" />
                                    <p className="text-sm">{file.name}</p>
                                </div>
                            ) : (
                                <div>
                                    <Upload className="h-8 w-8 mx-auto mb-2 text-muted-foreground" />
                                    <p className="text-sm text-muted-foreground">
                                        Cliquez pour sélectionner une image de feuille de partie
                                    </p>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        JPG, PNG, WebP, BMP ou TIFF — 20 Mo maximum
                                    </p>
                                </div>
                            )}
                        </div>

                        {ocrError && <AlertError message={ocrError} />}

                        <div className="flex items-start gap-2 rounded-md bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 p-3">
                            <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                            <p className="text-sm text-amber-800 dark:text-amber-300">
                                La reconnaissance de texte est expérimentale et peut commettre des erreurs.
                                Vous pourrez vérifier et corriger les coups à l'étape suivante.
                            </p>
                        </div>

                        <div className="rounded-md bg-muted/50 p-3">
                            <p className="text-sm font-medium mb-2">Recommandations pour une bonne lecture :</p>
                            <ul className="text-sm text-muted-foreground space-y-1 list-disc list-inside">
                                <li>Fond uni et bien éclairé</li>
                                <li>Feuille verticale et bien droite (pas inclinée)</li>
                                <li>Coups écrits en notation algébrique française (ex : Cf3, Fe2, Dd1)</li>
                                <li>Écriture lisible, sans ratures ni annotations dans les cases</li>
                                <li>Texte bien centré dans chaque case</li>
                            </ul>
                        </div>

                        <Button type="submit" disabled={!file || ocrLoading}>
                            {ocrLoading ? (
                                <span className="flex items-center gap-2">
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    Reconnaissance en cours...
                                </span>
                            ) : (
                                'Lancer la reconnaissance'
                            )}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    Vérifier et compléter la partie
                    <span className="inline-flex items-center gap-1 text-xs font-normal bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 px-2 py-0.5 rounded-full">
                        <FlaskConical className="h-3 w-3" />
                        Expérimental
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} className="space-y-4">
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

                    <div>
                        <label className="text-sm font-medium mb-1 block">Coups (reconnus par OCR) *</label>
                        <div className="flex items-start gap-2 rounded-md bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 p-3 mb-2">
                            <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                            <p className="text-sm text-amber-800 dark:text-amber-300">
                                Les coups ci-dessous ont été reconnus automatiquement. Veuillez les vérifier et corriger si nécessaire.
                            </p>
                        </div>
                        <Textarea
                            value={form.moves}
                            onChange={set('moves')}
                            placeholder="1. e4 e5 2. Cf3 Cc6 ..."
                            rows={6}
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="ocr-public"
                            checked={isPublic}
                            onChange={(e) => setIsPublic(e.target.checked)}
                            className="h-4 w-4 rounded border-input accent-primary"
                        />
                        <label htmlFor="ocr-public" className="text-sm cursor-pointer">
                            Partie publique
                        </label>
                    </div>

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep('upload')}
                        >
                            Nouvelle image
                        </Button>
                        <Button type="submit" disabled={!form.moves.trim() || submitLoading}>
                            {submitLoading ? 'Import en cours...' : 'Importer la partie'}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
};

export default ImportOcr;
