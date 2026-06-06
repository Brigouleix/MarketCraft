import React, { Suspense, lazy } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import Navbar from './components/Navbar';
import Footer from './components/Footer';
import CartDrawer from './components/CartDrawer';
import ProtectedRoute from './components/ProtectedRoute';
import BackofficeRoute from './components/BackofficeRoute';

// ── Main site pages ───────────────────────────────────────────────────────────
const HomePage          = lazy(() => import('./pages/HomePage'));
const ProductsPage      = lazy(() => import('./pages/ProductsPage'));
const ProductDetailPage = lazy(() => import('./pages/ProductDetailPage'));
const CartPage          = lazy(() => import('./pages/CartPage'));
const CheckoutPage      = lazy(() => import('./pages/CheckoutPage'));
const LoginPage         = lazy(() => import('./pages/LoginPage'));
const RegisterPage      = lazy(() => import('./pages/RegisterPage'));
const DashboardPage     = lazy(() => import('./pages/DashboardPage'));
const BoutiquePage      = lazy(() => import('./pages/BoutiquePage'));
const ProfilePage       = lazy(() => import('./pages/ProfilePage'));
const SearchResultsPage = lazy(() => import('./pages/SearchResultsPage'));

// ── Back-office pages ─────────────────────────────────────────────────────────
const BackofficeLoginPage = lazy(() => import('./pages/backoffice/BackofficeLoginPage'));
const BackofficeDashboard = lazy(() => import('./pages/backoffice/BackofficeDashboard'));

function PageLoader() {
  return (
    <div className="min-h-[60vh] flex items-center justify-center">
      <div className="flex flex-col items-center gap-4">
        <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin" />
        <p className="text-gray-500 text-sm">Chargement…</p>
      </div>
    </div>
  );
}

// Wraps the public marketplace routes in the shared Navbar/Footer shell
function MainLayout({ children }) {
  return (
    <div className="min-h-screen flex flex-col">
      <Navbar />
      <CartDrawer />
      <main className="flex-1">{children}</main>
      <Footer />
    </div>
  );
}

export default function App() {
  return (
    <Suspense fallback={<PageLoader />}>
      <Routes>

        {/* ── Back-office (no Navbar / Footer) ─────────────────────────── */}
        <Route path="/backoffice/login"     element={<BackofficeLoginPage />} />
        <Route
          path="/backoffice/dashboard"
          element={
            <BackofficeRoute>
              <BackofficeDashboard />
            </BackofficeRoute>
          }
        />
        <Route path="/backoffice" element={<Navigate to="/backoffice/dashboard" replace />} />

        {/* ── Main public site ─────────────────────────────────────────── */}
        <Route path="/" element={<MainLayout><HomePage /></MainLayout>} />
        <Route path="/produits" element={<MainLayout><ProductsPage /></MainLayout>} />
        <Route path="/produits/:id" element={<MainLayout><ProductDetailPage /></MainLayout>} />
        <Route path="/boutiques/:id" element={<MainLayout><BoutiquePage /></MainLayout>} />
        <Route path="/search" element={<MainLayout><SearchResultsPage /></MainLayout>} />
        <Route path="/login" element={<MainLayout><LoginPage /></MainLayout>} />
        <Route path="/register" element={<MainLayout><RegisterPage /></MainLayout>} />

        {/* Protected main-site routes */}
        <Route
          path="/panier"
          element={
            <MainLayout>
              <ProtectedRoute><CartPage /></ProtectedRoute>
            </MainLayout>
          }
        />
        <Route
          path="/checkout"
          element={
            <MainLayout>
              <ProtectedRoute><CheckoutPage /></ProtectedRoute>
            </MainLayout>
          }
        />
        <Route
          path="/profil"
          element={
            <MainLayout>
              <ProtectedRoute><ProfilePage /></ProtectedRoute>
            </MainLayout>
          }
        />
        <Route
          path="/dashboard"
          element={
            <MainLayout>
              <ProtectedRoute role="vendeur"><DashboardPage /></ProtectedRoute>
            </MainLayout>
          }
        />

        {/* 404 */}
        <Route
          path="*"
          element={
            <MainLayout>
              <div className="min-h-[60vh] flex flex-col items-center justify-center gap-6 px-4 text-center">
                <h1 className="text-6xl font-serif font-bold text-primary">404</h1>
                <p className="text-xl text-gray-600">Page introuvable</p>
                <a href="/" className="btn-primary">Retour à l'accueil</a>
              </div>
            </MainLayout>
          }
        />
      </Routes>
    </Suspense>
  );
}
