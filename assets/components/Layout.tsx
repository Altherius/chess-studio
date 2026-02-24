import React from 'react';
import { Outlet, Link } from 'react-router-dom';
import { Sun, Moon } from 'lucide-react';
import { useAuth } from '../hooks/useAuth';
import { useTheme } from '../hooks/useTheme';
import { Button } from './ui/button';

const Layout: React.FC = () => {
    const { user, logout, hasRole } = useAuth();
    const { theme, toggleTheme } = useTheme();

    const handleLogout = async () => {
        await logout();
    };

    return (
        <div className="mx-auto max-w-[1400px] px-4 md:px-5 py-5">
            <header className="flex items-center justify-between py-5 border-b border-border mb-8">
                <Link to="/games" className="text-xl sm:text-2xl md:text-3xl font-bold hover:opacity-80 transition-opacity">
                    Breizh Chess Studio
                </Link>
                {user && (
                    <div className="flex items-center gap-2 sm:gap-4">
                        {hasRole('ROLE_USER_MANAGER') && (
                            <Link to="/users" className="text-sm text-muted-foreground hover:text-foreground transition-colors">
                                Gestion des utilisateurs
                            </Link>
                        )}
                        <Link to="/profile" className="text-sm text-muted-foreground hover:text-foreground transition-colors">
                            {user.email}
                        </Link>
                        <Button variant="ghost" size="sm" onClick={handleLogout}>
                            Déconnexion
                        </Button>
                        <Button variant="ghost" size="icon" onClick={toggleTheme} aria-label="Changer de thème">
                            {theme === 'dark' ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                        </Button>
                    </div>
                )}
            </header>
            <Outlet />
        </div>
    );
};

export default Layout;
