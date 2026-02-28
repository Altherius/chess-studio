import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './hooks/useAuth';
import ProtectedRoute from './components/ProtectedRoute';
import Layout from './components/Layout';
import LoginPage from './components/pages/LoginPage';
import GamesPage from './components/pages/GamesPage';
import GameImportPage from './components/pages/GameImportPage';
import GamePage from './components/pages/GamePage';
import GameEditPage from './components/pages/GameEditPage';
import ChangePasswordPage from './components/pages/ChangePasswordPage';
import ProfilePage from './components/pages/ProfilePage';
import UsersPage from './components/pages/UsersPage';
import UserCreatePage from './components/pages/UserCreatePage';
import './styles/app.css';

const container = document.getElementById('root');
if (container) {
    const root = createRoot(container);
    root.render(
        <BrowserRouter>
            <AuthProvider>
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route element={<ProtectedRoute />}>
                        <Route path="/change-password" element={<ChangePasswordPage />} />
                        <Route element={<Layout />}>
                            <Route path="/games" element={<GamesPage />} />
                            <Route path="/games/import" element={<GameImportPage />} />
                            <Route path="/games/:id" element={<GamePage />} />
                            <Route path="/games/:id/edit" element={<GameEditPage />} />
                            <Route path="/profile" element={<ProfilePage />} />
                            <Route path="/users" element={<UsersPage />} />
                            <Route path="/users/create" element={<UserCreatePage />} />
                        </Route>
                    </Route>
                    <Route path="*" element={<Navigate to="/games" replace />} />
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    );
}
