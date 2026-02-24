import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import type { User } from '../types/chess';

interface AuthContextType {
    user: User | null;
    loading: boolean;
    login: (email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
    hasRole: (role: string) => boolean;
}

const AuthContext = createContext<AuthContextType | null>(null);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);

    const fetchMe = useCallback(async () => {
        try {
            const res = await fetch('/api/me');
            if (res.ok) {
                setUser(await res.json());
            } else {
                setUser(null);
            }
        } catch {
            setUser(null);
        }
    }, []);

    useEffect(() => {
        fetchMe().finally(() => setLoading(false));
    }, [fetchMe]);

    const login = useCallback(async (email: string, password: string) => {
        const res = await fetch('/api/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password }),
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data.error || 'Identifiants incorrects.');
        }

        await fetchMe();
    }, [fetchMe]);

    const logout = useCallback(async () => {
        await fetch('/api/logout', { method: 'POST' });
        setUser(null);
    }, []);

    const hasRole = useCallback((role: string) => {
        return user?.roles?.includes(role) ?? false;
    }, [user]);

    return (
        <AuthContext.Provider value={{ user, loading, login, logout, hasRole }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = (): AuthContextType => {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth must be used within AuthProvider');
    return ctx;
};
