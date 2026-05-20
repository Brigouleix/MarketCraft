import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

/**
 * ProtectedRoute – wraps a route that requires authentication.
 *
 * Props:
 *  children  – JSX to render when authorized
 *  role      – 'vendeur' | 'admin' – if provided, also checks the user role
 */
export default function ProtectedRoute({ children, role }) {
  const { isAuthenticated, isVendeur, isAdmin } = useAuth();
  const location = useLocation();

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  if (role === 'admin' && !isAdmin) {
    return <Navigate to="/" replace />;
  }

  if (role === 'vendeur' && !isVendeur) {
    return <Navigate to="/" replace />;
  }

  return children;
}
