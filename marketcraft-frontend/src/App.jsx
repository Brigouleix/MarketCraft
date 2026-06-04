import React, { Suspense, lazy } from 'react';
import { Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar';
import Footer from './components/Footer';
import CartDrawer from './components/CartDrawer';
import ProtectedRoute from './components/ProtectedRoute';

// Lazy-load pages
const HomePage = lazy(() => import('./pages/HomePage'));
const ProductsPage = lazy(() => import('./pages/ProductsPage'));
const ProductDetailPage = lazy(() => import('./pages/ProductDetailPage'));
const CartPage = lazy(() => import('./pages/CartPage'));
const CheckoutPage = lazy(() => import('./pages/CheckoutPage'));
const LoginPage = lazy(() => import('./pages/LoginPage'));
const RegisterPage = lazy(() => import('./pages/RegisterPage'));
const DashboardPage = lazy(() => import('./pages/DashboardPage'));
const BoutiquePage = lazy(() => import('./pages/BoutiquePage'));
const ProfilePage = lazy(() => import('./pages/ProfilePage'));
const SearchResultsPage = lazy(() => import('./pages/SearchResultsPage'));
const BuyerStatsPage = lazy(() => import('./pages/BuyerStatsPage'));

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

export default function App() {
  return (
    <div className="min-h-screen flex flex-col">
      <Navbar />
      <CartDrawer />
      <main className="flex-1">
        <Suspense fallback={<PageLoader />}>
          <Routes>
            <Route path="/" element={<HomePage />} />
            <Route path="/produits" element={<ProductsPage />} />
            <Route path="/produits/:id" element={<ProductDetailPage />} />
            <Route path="/boutiques/:id" element={<BoutiquePage />} />
            <Route path="/search" element={<SearchResultsPage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />

            {/* Protected routes */}
            <Route
              path="/panier"
              element={
                <ProtectedRoute>
                  <CartPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/checkout"
              element={
                <ProtectedRoute>
                  <CheckoutPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/profil"
              element={
                <ProtectedRoute>
                  <ProfilePage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/dashboard"
              element={
                <ProtectedRoute role="vendeur">
                  <DashboardPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/mes-stats"
              element={
                <ProtectedRoute>
                  <BuyerStatsPage />
                </ProtectedRoute>
              }
            />

            {/* 404 */}
            <Route
              path="*"
              element={
                <div className="min-h-[60vh] flex flex-col items-center justify-center gap-6 px-4 text-center">
                  <h1 className="text-6xl font-serif font-bold text-primary">404</h1>
                  <p className="text-xl text-gray-600">Page introuvable</p>
                  <a href="/" className="btn-primary">Retour à l'accueil</a>
                </div>
              }
            />
          </Routes>
        </Suspense>
      </main>
      <Footer />
    </div>
  );
}
