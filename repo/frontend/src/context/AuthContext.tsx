import { createContext, useContext, useState, useEffect, useCallback, type ReactNode } from 'react';
import * as authApi from '../api/auth';
import type { User, UserRole } from '../types';

interface AuthContextType {
  user: User | null;
  csrfToken: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  hasRole: (role: UserRole) => boolean;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [csrfToken, setCsrfToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const isAuthenticated = !!user;

  // Check for existing session on mount
  useEffect(() => {
    let cancelled = false;
    authApi
      .getMe()
      .then((data) => {
        if (!cancelled) {
          setUser(data.user);
          setCsrfToken(data.csrfToken);
          localStorage.setItem('csrf_token', data.csrfToken);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setUser(null);
          setCsrfToken(null);
          localStorage.removeItem('csrf_token');
        }
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const login = useCallback(async (username: string, password: string) => {
    const data = await authApi.login(username, password);
    setUser(data.user);
    setCsrfToken(data.csrfToken);
    localStorage.setItem('csrf_token', data.csrfToken);
  }, []);

  const logout = useCallback(async () => {
    await authApi.logout();
    setUser(null);
    setCsrfToken(null);
    localStorage.removeItem('csrf_token');
  }, []);

  const hasRole = useCallback(
    (role: UserRole): boolean => {
      if (!user) return false;
      // ROLE_ADMIN has all roles
      if (user.role === 'ROLE_ADMIN') return true;
      return user.role === role;
    },
    [user]
  );

  return (
    <AuthContext.Provider
      value={{ user, csrfToken, isAuthenticated, isLoading, login, logout, hasRole }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
