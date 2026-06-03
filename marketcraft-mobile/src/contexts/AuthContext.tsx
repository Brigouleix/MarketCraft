import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { authAPI } from '../services/api';

interface User {
  id: number;
  email: string;
  nom: string;
  prenom: string;
  role: 'acheteur' | 'vendeur' | 'admin';
}

interface AuthContextValue {
  user: User | null;
  isAuthenticated: boolean;
  isVendeur: boolean;
  isAdmin: boolean;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (data: object) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser]       = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    (async () => {
      const token = await AsyncStorage.getItem('jwt_token');
      if (token) {
        try {
          const { data } = await authAPI.me();
          setUser(data.user ?? data);
        } catch {
          await AsyncStorage.removeItem('jwt_token');
        }
      }
      setIsLoading(false);
    })();
  }, []);

  const login = async (email: string, password: string) => {
    const { data } = await authAPI.login({ email, password });
    await AsyncStorage.setItem('jwt_token', data.token);
    setUser(data.user);
  };

  const register = async (formData: object) => {
    const { data } = await authAPI.register(formData);
    await AsyncStorage.setItem('jwt_token', data.token);
    setUser(data.user);
  };

  const logout = async () => {
    try { await authAPI.logout(); } catch {}
    await AsyncStorage.removeItem('jwt_token');
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{
      user,
      isAuthenticated: !!user,
      isVendeur: user?.role === 'vendeur',
      isAdmin:   user?.role === 'admin',
      isLoading,
      login, register, logout,
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
};
