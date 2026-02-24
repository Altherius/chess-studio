import React, { useState } from 'react';
import { useAuth } from '../../hooks/useAuth';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { AlertError, AlertSuccess, PasswordHint } from '../ui/alert';

const ProfilePage: React.FC = () => {
    const { user } = useAuth();
    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setSuccess('');

        if (newPassword !== confirmPassword) {
            setError('Les mots de passe ne correspondent pas.');
            return;
        }

        setSubmitting(true);
        try {
            const res = await fetch('/api/profile/password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ currentPassword, newPassword, confirmPassword }),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.error || 'Erreur lors du changement de mot de passe.');
            }

            setSuccess('Mot de passe modifié avec succès.');
            setCurrentPassword('');
            setNewPassword('');
            setConfirmPassword('');
        } catch (err: any) {
            setError(err.message);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="max-w-2xl mx-auto space-y-6">
            <h1 className="text-2xl font-bold">Mon profil</h1>

            <Card>
                <CardHeader>
                    <CardTitle>Informations</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    <div>
                        <span className="text-sm text-muted-foreground">Email : </span>
                        <span className="text-sm">{user?.email}</span>
                    </div>
                    {(user?.firstName || user?.lastName) && (
                        <div>
                            <span className="text-sm text-muted-foreground">Nom : </span>
                            <span className="text-sm">
                                {[user?.firstName, user?.lastName].filter(Boolean).join(' ')}
                            </span>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Changer le mot de passe</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {error && <AlertError message={error} />}
                        {success && <AlertSuccess message={success} />}
                        <div className="space-y-2">
                            <label htmlFor="currentPassword" className="text-sm font-medium">
                                Mot de passe actuel
                            </label>
                            <Input
                                id="currentPassword"
                                type="password"
                                value={currentPassword}
                                onChange={(e) => setCurrentPassword(e.target.value)}
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <label htmlFor="newPassword" className="text-sm font-medium">
                                Nouveau mot de passe
                            </label>
                            <Input
                                id="newPassword"
                                type="password"
                                value={newPassword}
                                onChange={(e) => setNewPassword(e.target.value)}
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <label htmlFor="confirmPassword" className="text-sm font-medium">
                                Confirmer le nouveau mot de passe
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
                        <Button type="submit" disabled={submitting}>
                            {submitting ? 'Enregistrement...' : 'Modifier le mot de passe'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
};

export default ProfilePage;
