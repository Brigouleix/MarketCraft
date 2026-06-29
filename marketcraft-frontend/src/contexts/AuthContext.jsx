import React, { createContext, useState, useEffect, useCallback } from 'react';
import { authAPI } from '../services/api';
import toast from 'react-hot-toast';

export const AuthContext = createContext(null);

const TOKEN_KEY = 'mc_token';
const REFRESH_KEY = 'mc_refresh_token';
const USER_KEY = 'mc_user';

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    try {
      const stored = localStorage.getItem(USER_KEY);
      return stored ? JSON.parse(stored) : null;
    } catch {
      return null;
    }
  });
  const [token, setToken] = useState(() => localStorage.getItem(TOKEN_KEY) || null);
  const [loading, setLoading] = useState(false);

  // Verify stored token on mount
  useEffect(() => {
    if (token && !user) {
      authAPI
        .me()
        .then(({ data }) => {
          setUser(data.user ?? data);
          localStorage.setItem(USER_KEY, JSON.stringify(data.user ?? data));
        })
        .catch(() => {
          clearAuth();
        });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const persistAuth = (accessToken, refreshToken, userData) => {
    localStorage.setItem(TOKEN_KEY, accessToken);
    if (refreshToken) localStorage.setItem(REFRESH_KEY, refreshToken);
    localStorage.setItem(USER_KEY, JSON.stringify(userData));
    setToken(accessToken);
    setUser(userData);
  };

  const clearAuth = () => {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(REFRESH_KEY);
    localStorage.removeItem(USER_KEY);
    setToken(null);
    setUser(null);
  };

  const login = useCallback(async (email, password, extras = {}) => {
    setLoading(true);
    try {
      const { data } = await authAPI.login({ email, password, ...extras });
      persistAuth(data.access_token, data.refresh_token, data.user);
      toast.success(`Bienvenue, ${data.user.prenom || data.user.nom} !`);
      return { success: true, user: data.user };
    } catch (err) {
      const status  = err.response?.status;
      const errData = err.response?.data || {};

      // IP rate-limited
      if (status === 429) {
        const msg = errData.error || 'Trop de tentatives. Réessayez plus tard.';
        toast.error(msg);
        return { success: false, blocked: true, retry_after: errData.retry_after || 900, error: msg };
      }

      const msg = errData.error || errData.message || 'Email ou mot de passe invalide.';
      toast.error(msg);
      return { success: false, error: msg };
    } finally {
      setLoading(false);
    }
  }, []);

  const register = useCallback(async (formData) => {
    setLoading(true);
    try {
      const { data } = await authAPI.register(formData);
      persistAuth(data.access_token, data.refresh_token, data.user);
      toast.success('Compte créé avec succès !');
      return { success: true, user: data.user };
    } catch (err) {
      const msg = err.response?.data?.message || 'Erreur lors de la création du compte.';
      toast.error(msg);
      return { success: false, error: msg };
    } finally {
      setLoading(false);
    }
  }, []);

  const logout = useCallback(async () => {
    try {
      await authAPI.logout();
    } catch {
      // ignore – clear locally anyway
    }
    clearAuth();
    toast.success('Déconnexion réussie.');
  }, []);

  const updateUser = useCallback((updatedUser) => {
    setUser(updatedUser);
    localStorage.setItem(USER_KEY, JSON.stringify(updatedUser));
  }, []);

  const isAuthenticated = Boolean(token && user);
  const isVendeur = isAuthenticated && (user?.role === 'vendeur' || user?.role === 'admin');
  const isAdmin = isAuthenticated && user?.role === 'admin';

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        loading,
        login,
        logout,
        register,
        updateUser,
        isAuthenticated,
        isVendeur,
        isAdmin,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}
