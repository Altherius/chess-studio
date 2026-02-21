import React from 'react';
import { Outlet, Link } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { useTheme } from '../hooks/useTheme';
import { Button } from './ui/button';

const Layout: React.FC = () => {
    const { user, logout } = useAuth();
    const { theme, toggleTheme } = useTheme();

    const handleLogout = async () => {
        await logout();
    };

    return (
        <div className="mx-auto max-w-[1400px] p-5">
            <header className="flex items-center justify-between py-5 border-b border-border mb-8">
                <Link to="/games" className="text-3xl font-bold hover:opacity-80 transition-opacity">
                    Breizh Chess Studio
                </Link>
                {user && (
                    <div className="flex items-center gap-4">
                        <span className="text-sm text-muted-foreground">{user.email}</span>
                        <Button variant="ghost" size="sm" onClick={handleLogout}>
                            Déconnexion
                        </Button>
                        <Button variant="ghost" size="sm" onClick={toggleTheme} aria-label="Changer de thème">
                            {theme === 'dark' ? '\u2600\uFE0F' : '\uD83C\uDF19'}
                        </Button>
                    </div>
                )}
            </header>
            <Outlet />
        </div>
    );
};

export default Layout;
