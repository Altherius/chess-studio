import React, { useState } from 'react';
import { Outlet, Link } from 'react-router-dom';
import { Sun, Moon, Menu, X } from 'lucide-react';
import { useAuth } from '../hooks/useAuth';
import { useTheme } from '../hooks/useTheme';
import { Button } from './ui/button';

const Layout: React.FC = () => {
    const { user, logout, hasRole } = useAuth();
    const { theme, toggleTheme } = useTheme();
    const [menuOpen, setMenuOpen] = useState(false);

    const handleLogout = async () => {
        setMenuOpen(false);
        await logout();
    };

    return (
        <div className="mx-auto max-w-[1400px] px-4 md:px-5 py-5">
            <header className="py-5 border-b border-border mb-8">
                <div className="flex items-center justify-between">
                    <Link to="/games" className="flex items-center gap-2 hover:opacity-80 transition-opacity">
                        <img src="/logo.svg" alt="Breizh Chess Studio" className="h-8 w-8" />
                        <span className="hidden sm:inline text-xl font-bold font-serif">Breizh Chess Studio</span>
                    </Link>
                    {user && (
                        <>
                            <nav className="hidden md:flex items-center gap-4">
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
                            </nav>
                            <div className="flex items-center gap-2 md:hidden">
                                <Button variant="ghost" size="icon" onClick={toggleTheme} aria-label="Changer de thème">
                                    {theme === 'dark' ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                                </Button>
                                <Button variant="ghost" size="icon" onClick={() => setMenuOpen(!menuOpen)} aria-label="Menu">
                                    {menuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                                </Button>
                            </div>
                        </>
                    )}
                </div>
                {user && menuOpen && (
                    <nav className="flex flex-col gap-2 pt-4 md:hidden">
                        {hasRole('ROLE_USER_MANAGER') && (
                            <Link to="/users" onClick={() => setMenuOpen(false)} className="text-sm text-muted-foreground hover:text-foreground transition-colors py-1">
                                Gestion des utilisateurs
                            </Link>
                        )}
                        <Link to="/profile" onClick={() => setMenuOpen(false)} className="text-sm text-muted-foreground hover:text-foreground transition-colors py-1">
                            {user.email}
                        </Link>
                        <Button variant="ghost" size="sm" className="justify-start px-0" onClick={handleLogout}>
                            Déconnexion
                        </Button>
                    </nav>
                )}
            </header>
            <Outlet />
        </div>
    );
};

export default Layout;
