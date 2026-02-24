import React, { useState } from 'react';
import { Card, CardContent } from '../ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../ui/tabs';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { AlertError, AlertSuccess } from '../ui/alert';

const UserCreatePage: React.FC = () => {
    return (
        <div className="max-w-2xl mx-auto space-y-6">
            <h1 className="text-2xl font-bold">Ajouter un utilisateur</h1>
            <Tabs defaultValue="form">
                <TabsList>
                    <TabsTrigger value="form">Formulaire</TabsTrigger>
                    <TabsTrigger value="csv">Import CSV</TabsTrigger>
                </TabsList>
                <TabsContent value="form">
                    <UserForm />
                </TabsContent>
                <TabsContent value="csv">
                    <CsvImport />
                </TabsContent>
            </Tabs>
        </div>
    );
};

const UserForm: React.FC = () => {
    const [email, setEmail] = useState('');
    const [lastName, setLastName] = useState('');
    const [firstName, setFirstName] = useState('');
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setSuccess('');
        setSubmitting(true);

        try {
            const res = await fetch('/api/admin/users', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, firstName: firstName || null, lastName: lastName || null }),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.error || 'Erreur lors de la création.');
            }

            const data = await res.json();
            setSuccess(`Utilisateur ${data.email} créé. Un email avec le mot de passe temporaire a été envoyé.`);
            setEmail('');
            setLastName('');
            setFirstName('');
        } catch (err: any) {
            setError(err.message);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Card>
            <CardContent className="pt-6">
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <AlertError message={error} />}
                    {success && <AlertSuccess message={success} />}
                    <div className="space-y-2">
                        <label htmlFor="email" className="text-sm font-medium">
                            Adresse email *
                        </label>
                        <Input
                            id="email"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                        />
                    </div>
                    <div className="space-y-2">
                        <label htmlFor="lastName" className="text-sm font-medium">
                            Nom
                        </label>
                        <Input
                            id="lastName"
                            type="text"
                            value={lastName}
                            onChange={(e) => setLastName(e.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <label htmlFor="firstName" className="text-sm font-medium">
                            Prénom
                        </label>
                        <Input
                            id="firstName"
                            type="text"
                            value={firstName}
                            onChange={(e) => setFirstName(e.target.value)}
                        />
                    </div>
                    <Button type="submit" disabled={submitting}>
                        {submitting ? 'Création...' : 'Créer l\'utilisateur'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
};

interface CsvResult {
    created: number;
    errors: { row: number; email: string; error: string }[];
}

const CsvImport: React.FC = () => {
    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState('');
    const [result, setResult] = useState<CsvResult | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!file) return;

        setError('');
        setResult(null);
        setSubmitting(true);

        try {
            const formData = new FormData();
            formData.append('file', file);

            const res = await fetch('/api/admin/users/import', {
                method: 'POST',
                body: formData,
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.error || 'Erreur lors de l\'import.');
            }

            setResult(await res.json());
            setFile(null);
        } catch (err: any) {
            setError(err.message);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Card>
            <CardContent className="pt-6">
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <AlertError message={error} />}
                    <p className="text-sm text-muted-foreground">
                        Format CSV attendu : email, nom, prénom (une ligne par utilisateur).
                    </p>
                    <div className="space-y-2">
                        <label htmlFor="csvFile" className="text-sm font-medium">
                            Fichier CSV
                        </label>
                        <Input
                            id="csvFile"
                            type="file"
                            accept=".csv"
                            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                        />
                    </div>
                    <Button type="submit" disabled={submitting || !file}>
                        {submitting ? 'Import en cours...' : 'Importer'}
                    </Button>
                </form>

                {result && (
                    <div className="mt-4 space-y-2">
                        <AlertSuccess message={`${result.created} utilisateur(s) créé(s).`} />
                        {result.errors.length > 0 && (
                            <div className="space-y-1">
                                <p className="text-sm font-medium text-destructive-foreground">Erreurs :</p>
                                {result.errors.map((err, i) => (
                                    <p key={i} className="text-sm text-destructive-foreground">
                                        Ligne {err.row}{err.email ? ` (${err.email})` : ''} : {err.error}
                                    </p>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
};

export default UserCreatePage;
