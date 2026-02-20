import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './hooks/useAuth';
import ProtectedRoute from './components/ProtectedRoute';
import Layout from './components/Layout';
import LoginPage from './components/pages/LoginPage';
import RegisterPage from './components/pages/RegisterPage';
import GamesPage from './components/pages/GamesPage';
import GameImportPage from './components/pages/GameImportPage';
import GamePage from './components/pages/GamePage';
import './styles/app.css';

const container = document.getElementById('root');
if (container) {
    const root = createRoot(container);
    root.render(
        <BrowserRouter>
            <AuthProvider>
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/register" element={<RegisterPage />} />
                    <Route element={<ProtectedRoute />}>
                        <Route element={<Layout />}>
                            <Route path="/games" element={<GamesPage />} />
                            <Route path="/games/import" element={<GameImportPage />} />
                            <Route path="/games/:id" element={<GamePage />} />
                        </Route>
                    </Route>
                    <Route path="*" element={<Navigate to="/games" replace />} />
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    );
}
