import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'react-hot-toast';
import App from './App';
import { AuthProvider } from './contexts/AuthContext';
import { CartProvider } from './contexts/CartContext';
import './index.css';

// ── ResizeObserver : bruit de l'overlay de développement ─────────────────────
// « ResizeObserver loop completed with undelivered notifications » est un
// avertissement bénin du navigateur : un observateur a redimensionné un
// élément pendant son propre cycle, le navigateur reporte simplement la
// notification à la frame suivante. Rien n'est cassé, mais l'overlay d'erreur
// de webpack-dev-server l'affiche en plein écran comme un crash.
// On le neutralise uniquement en développement ; en production l'overlay
// n'existe pas et ce code ne s'exécute pas.
if (process.env.NODE_ENV === 'development') {
  const RESIZE_OBSERVER_NOISE =
    /^(?:ResizeObserver loop (?:limit exceeded|completed with undelivered notifications))/;

  const masquerOverlay = () => {
    document
      .querySelectorAll('#webpack-dev-server-client-overlay, iframe#webpack-dev-server-client-overlay')
      .forEach((el) => el.remove());
  };

  window.addEventListener('error', (e) => {
    if (RESIZE_OBSERVER_NOISE.test(e.message || '')) {
      e.stopImmediatePropagation();
      masquerOverlay();
    }
  });

  window.addEventListener('unhandledrejection', (e) => {
    if (RESIZE_OBSERVER_NOISE.test(e.reason?.message || '')) {
      e.stopImmediatePropagation();
      masquerOverlay();
    }
  });
}

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 5,
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <CartProvider>
            <App />
            <Toaster
              position="top-right"
              toastOptions={{
                duration: 3500,
                style: {
                  background: '#F5F0E8',
                  color: '#3b2a1a',
                  border: '1px solid #e0d2b8',
                  borderRadius: '0.5rem',
                  fontFamily: 'Inter, system-ui, sans-serif',
                },
                success: {
                  iconTheme: { primary: '#6B7C3F', secondary: '#F5F0E8' },
                },
                error: {
                  iconTheme: { primary: '#8B4513', secondary: '#F5F0E8' },
                },
              }}
            />
          </CartProvider>
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  </React.StrictMode>
);
