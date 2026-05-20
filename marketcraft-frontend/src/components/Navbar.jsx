import React, { useState, useContext, useRef, useEffect } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import {
  ShoppingCart,
  User,
  Search,
  Menu,
  X,
  LogOut,
  LayoutDashboard,
  UserCircle,
  ChevronDown,
  Hammer,
  Sparkles,
} from 'lucide-react';
import { CartContext } from '../contexts/CartContext';
import { useAuth } from '../hooks/useAuth';
import AISearchBar from './AISearchBar';

const navLinks = [
  { to: '/', label: 'Accueil', end: true },
  { to: '/produits', label: 'Produits' },
  { to: '/boutiques', label: 'Boutiques' },
];

export default function Navbar() {
  const { count, setIsOpen: openCart } = useContext(CartContext);
  const { user, isAuthenticated, isVendeur, logout } = useAuth();
  const navigate = useNavigate();

  const [mobileOpen, setMobileOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [aiSearchOpen, setAiSearchOpen] = useState(false);
  const userMenuRef = useRef(null);

  // Close user menu on outside click
  useEffect(() => {
    const handler = (e) => {
      if (userMenuRef.current && !userMenuRef.current.contains(e.target)) {
        setUserMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const handleSearch = (e) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      navigate(`/produits?search=${encodeURIComponent(searchQuery.trim())}`);
      setSearchQuery('');
      setMobileOpen(false);
    }
  };

  const handleLogout = async () => {
    setUserMenuOpen(false);
    await logout();
    navigate('/');
  };

  return (
    <>
    <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-sm shadow-craft border-b border-secondary-200">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Logo */}
          <Link to="/" className="flex items-center gap-2 flex-shrink-0 group">
            <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center group-hover:bg-primary-600 transition-colors">
              <Hammer size={16} className="text-white" />
            </div>
            <span className="font-serif text-xl font-bold text-primary group-hover:text-primary-600 transition-colors">
              MarketCraft
            </span>
          </Link>

          {/* Desktop Nav Links */}
          <nav className="hidden md:flex items-center gap-1">
            {navLinks.map(({ to, label, end }) => (
              <NavLink
                key={to}
                to={to}
                end={end}
                className={({ isActive }) =>
                  `px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-150 ${
                    isActive
                      ? 'bg-secondary text-primary'
                      : 'text-gray-600 hover:text-primary hover:bg-secondary-100'
                  }`
                }
              >
                {label}
              </NavLink>
            ))}
          </nav>

          {/* Desktop Search */}
          <form onSubmit={handleSearch} className="hidden md:flex items-center">
            <div className="relative">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Rechercher un produit…"
                className="pl-9 pr-4 py-2 text-sm border border-secondary-300 rounded-lg bg-secondary-50
                           focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary w-52 lg:w-64
                           placeholder-gray-400 transition-all"
              />
              <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            </div>
          </form>

          {/* Actions */}
          <div className="flex items-center gap-2">
            {/* Bouton Recherche IA (desktop uniquement) */}
            <button
              onClick={() => setAiSearchOpen(true)}
              className="hidden md:flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-purple-600 hover:text-purple-700 hover:bg-purple-50 rounded-lg transition-colors border border-purple-200"
            >
              <Sparkles size={15} />
              <span>IA</span>
            </button>

            {/* Cart */}
            <button
              onClick={() => openCart(true)}
              className="relative p-2.5 text-gray-600 hover:text-primary hover:bg-secondary-100 rounded-lg transition-colors"
              aria-label="Ouvrir le panier"
            >
              <ShoppingCart size={20} />
              {count > 0 && (
                <span className="absolute -top-1 -right-1 bg-primary text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">
                  {count > 99 ? '99+' : count}
                </span>
              )}
            </button>

            {/* User menu (desktop) */}
            {isAuthenticated ? (
              <div className="relative hidden md:block" ref={userMenuRef}>
                <button
                  onClick={() => setUserMenuOpen((v) => !v)}
                  className="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-700
                             hover:bg-secondary-100 hover:text-primary transition-colors"
                >
                  <div className="w-7 h-7 bg-primary rounded-full flex items-center justify-center">
                    <span className="text-white text-xs font-bold">
                      {(user?.prenom || user?.nom || 'U')[0].toUpperCase()}
                    </span>
                  </div>
                  <span className="max-w-[100px] truncate">{user?.prenom || user?.nom}</span>
                  <ChevronDown size={14} className={`transition-transform ${userMenuOpen ? 'rotate-180' : ''}`} />
                </button>

                {userMenuOpen && (
                  <div className="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-craft-lg border border-secondary-200 py-1 z-50">
                    <Link
                      to="/profil"
                      onClick={() => setUserMenuOpen(false)}
                      className="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-secondary-100 hover:text-primary transition-colors"
                    >
                      <UserCircle size={16} /> Mon profil
                    </Link>
                    {isVendeur && (
                      <Link
                        to="/dashboard"
                        onClick={() => setUserMenuOpen(false)}
                        className="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-secondary-100 hover:text-primary transition-colors"
                      >
                        <LayoutDashboard size={16} /> Dashboard
                      </Link>
                    )}
                    <hr className="my-1 border-secondary-200" />
                    <button
                      onClick={handleLogout}
                      className="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors"
                    >
                      <LogOut size={16} /> Déconnexion
                    </button>
                  </div>
                )}
              </div>
            ) : (
              <div className="hidden md:flex items-center gap-2">
                <Link to="/login" className="btn-secondary text-sm py-2 px-3">
                  Connexion
                </Link>
                <Link to="/register" className="btn-primary text-sm py-2 px-3">
                  S'inscrire
                </Link>
              </div>
            )}

            {/* Mobile hamburger */}
            <button
              onClick={() => setMobileOpen((v) => !v)}
              className="md:hidden p-2.5 text-gray-600 hover:text-primary hover:bg-secondary-100 rounded-lg transition-colors"
              aria-label="Menu"
            >
              {mobileOpen ? <X size={20} /> : <Menu size={20} />}
            </button>
          </div>
        </div>
      </div>

      {/* Mobile menu */}
      {mobileOpen && (
        <div className="md:hidden bg-white border-t border-secondary-200 px-4 pb-4 pt-2 space-y-2">
          {/* Mobile search */}
          <form onSubmit={handleSearch} className="flex gap-2 mb-3">
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Rechercher…"
              className="input-field text-sm flex-1"
            />
            <button type="submit" className="btn-primary py-2 px-3">
              <Search size={16} />
            </button>
          </form>

          {navLinks.map(({ to, label, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              onClick={() => setMobileOpen(false)}
              className={({ isActive }) =>
                `block px-4 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                  isActive ? 'bg-secondary text-primary' : 'text-gray-700 hover:bg-secondary-100'
                }`
              }
            >
              {label}
            </NavLink>
          ))}

          <hr className="border-secondary-200" />

          {isAuthenticated ? (
            <>
              <Link
                to="/profil"
                onClick={() => setMobileOpen(false)}
                className="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm text-gray-700 hover:bg-secondary-100"
              >
                <UserCircle size={16} /> Mon profil
              </Link>
              {isVendeur && (
                <Link
                  to="/dashboard"
                  onClick={() => setMobileOpen(false)}
                  className="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm text-gray-700 hover:bg-secondary-100"
                >
                  <LayoutDashboard size={16} /> Dashboard
                </Link>
              )}
              <button
                onClick={handleLogout}
                className="w-full flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm text-red-600 hover:bg-red-50"
              >
                <LogOut size={16} /> Déconnexion
              </button>
            </>
          ) : (
            <div className="flex gap-2 pt-1">
              <Link to="/login" onClick={() => setMobileOpen(false)} className="btn-secondary flex-1 text-center text-sm">
                Connexion
              </Link>
              <Link to="/register" onClick={() => setMobileOpen(false)} className="btn-primary flex-1 text-center text-sm">
                S'inscrire
              </Link>
            </div>
          )}
        </div>
      )}
    </header>

    {/* Modal de recherche IA (rendu en dehors du header pour éviter les z-index) */}
    <AISearchBar isOpen={aiSearchOpen} onClose={() => setAiSearchOpen(false)} />
    </>
  );
}
