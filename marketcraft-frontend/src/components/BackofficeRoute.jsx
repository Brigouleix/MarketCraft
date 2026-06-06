import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

export default function BackofficeRoute({ children }) {
  const { isAuthenticated, isVendeur } = useAuth();
  const location = useLocation();

  if (!isAuthenticated) {
    return <Navigate to="/backoffice/login" state={{ from: location }} replace />;
  }

  if (!isVendeur) {
    return <Navigate to="/backoffice/login" state={{ error: 'vendor_required' }} replace />;
  }

  return children;
}
