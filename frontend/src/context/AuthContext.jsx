import React, { createContext, useContext, useEffect, useState } from 'react';
import { apiRequest } from '../services/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('udharpay_token');
    if (!token) {
      setLoading(false);
      return;
    }
    apiRequest('/api/auth/me')
      .then((res) => setUser(res.data.user))
      .catch(() => {
        localStorage.removeItem('udharpay_token');
        localStorage.removeItem('udharpay_csrf');
      })
      .finally(() => setLoading(false));
  }, []);

  const persistSession = (payload) => {
    localStorage.setItem('udharpay_token', payload.session.token);
    localStorage.setItem('udharpay_csrf', payload.session.csrf_token);
    setUser(payload.user);
  };

  const login = async (identifier, password) => {
    const res = await apiRequest('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({ identifier, password }),
    });
    persistSession(res.data);
    return res.data.user;
  };

  const verifyOtp = async (phone, otp) => {
    const res = await apiRequest('/api/auth/otp/verify', {
      method: 'POST',
      body: JSON.stringify({ phone, otp }),
    });
    persistSession(res.data);
    return res.data.user;
  };

  const logout = async () => {
    try { await apiRequest('/api/auth/logout', { method: 'POST' }); } catch {}
    localStorage.removeItem('udharpay_token');
    localStorage.removeItem('udharpay_csrf');
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, verifyOtp, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
