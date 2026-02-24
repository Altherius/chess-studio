import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { AlertError, PasswordHint } from '../ui/alert';

const ChangePasswordPage: React.FC = () => {
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const navigate = useNavigate();

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');

        if (password !== confirmPassword) {
            setError('Les mots de passe ne correspondent pas.');
            return;
        }

        setSubmitting(true);
        try {
            const res = await fetch('/api/change-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password, confirmPassword }),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.error || 'Erreur lors du changement de mot de passe.');
            }

            navigate('/games');
            window.location.reload();
        } catch (err: any) {
            setError(err.message);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center p-5">
            <Card className="w-full max-w-md">
                <CardHeader>
                    <CardTitle className="text-center text-2xl">Changement de mot de passe</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground mb-4">
                        Vous devez choisir un nouveau mot de passe pour continuer.
                    </p>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {error && <AlertError message={error} />}
                        <div className="space-y-2">
                            <label htmlFor="password" className="text-sm font-medium">
                                Nouveau mot de passe
                            </label>
                            <Input
                                id="password"
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                                autoFocus
                            />
                        </div>
                        <div className="space-y-2">
                            <label htmlFor="confirmPassword" className="text-sm font-medium">
                                Confirmer le mot de passe
                            </label>
                            <Input
                                id="confirmPassword"
                                type="password"
                                value={confirmPassword}
                                onChange={(e) => setConfirmPassword(e.target.value)}
                                required
                            />
                        </div>
                        <PasswordHint />
                        <Button type="submit" className="w-full" disabled={submitting}>
                            {submitting ? 'Enregistrement...' : 'Changer le mot de passe'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
};

export default ChangePasswordPage;
