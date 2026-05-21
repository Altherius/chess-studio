import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { AlertError, AlertSuccess } from '../ui/alert';

const ForgotPasswordPage: React.FC = () => {
    const [email, setEmail] = useState('');
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setMessage('');
        setSubmitting(true);

        try {
            const res = await fetch('/api/password-reset/request', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email }),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                throw new Error(data.error || 'Une erreur est survenue.');
            }

            setMessage(data.message);
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
                    <CardTitle className="text-center text-2xl">Mot de passe oublié</CardTitle>
                </CardHeader>
                <CardContent>
                    {message ? (
                        <div className="space-y-4">
                            <AlertSuccess message={message} />
                            <p className="text-center">
                                <Link to="/login" className="text-sm text-muted-foreground hover:underline">
                                    Retour à la connexion
                                </Link>
                            </p>
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                Saisissez votre adresse email pour recevoir un lien de réinitialisation.
                            </p>
                            {error && <AlertError message={error} />}
                            <div className="space-y-2">
                                <label htmlFor="email" className="text-sm font-medium">
                                    Adresse email
                                </label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    required
                                    autoFocus
                                />
                            </div>
                            <Button type="submit" className="w-full" disabled={submitting}>
                                {submitting ? 'Envoi...' : 'Envoyer le lien'}
                            </Button>
                            <p className="text-center">
                                <Link to="/login" className="text-sm text-muted-foreground hover:underline">
                                    Retour à la connexion
                                </Link>
                            </p>
                        </form>
                    )}
                </CardContent>
            </Card>
        </div>
    );
};

export default ForgotPasswordPage;
