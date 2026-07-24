/**
 * AISearchBar.jsx
 * Modal de recherche en langage naturel propulsé par Claude (IA).
 * S'ouvre depuis la Navbar via la prop `isOpen` / `onClose`.
 *
 * Props :
 *   - isOpen   {boolean}   : contrôle l'affichage du modal
 *   - onClose  {function}  : callback pour fermer le modal
 */

import React, { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { X, Sparkles, Search, ChevronRight } from 'lucide-react';
import ProductCard from './ProductCard';

// Suggestions rapides affichées sous le textarea
const SUGGESTIONS = [
  'Table en bois rustique',
  'Bijoux artisanaux',
  'Couverts dorés',
  'Vase céramique',
  'Lampe vintage',
];

export default function AISearchBar({ isOpen, onClose }) {
  const navigate = useNavigate();

  // ── États ────────────────────────────────────────────────────────────────────
  const [query, setQuery]       = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [results, setResults]   = useState(null);   // { ai_message, keywords, products, total }
  const [error, setError]       = useState(null);

  const textareaRef = useRef(null);
  const overlayRef  = useRef(null);

  // Focus textarea à l'ouverture
  useEffect(() => {
    if (isOpen) {
      setTimeout(() => textareaRef.current?.focus(), 100);
      // Bloquer le scroll du body
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => { document.body.style.overflow = ''; };
  }, [isOpen]);

  // Fermeture avec la touche Escape
  useEffect(() => {
    const handler = (e) => { if (e.key === 'Escape') handleClose(); };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, []);

  // ── Handlers ─────────────────────────────────────────────────────────────────

  /** Réinitialise l'état et ferme le modal */
  const handleClose = () => {
    setResults(null);
    setError(null);
    setQuery('');
    onClose();
  };

  /** Clic sur l'overlay sombre → ferme */
  const handleOverlayClick = (e) => {
    if (e.target === overlayRef.current) handleClose();
  };

  /** Sélection d'une suggestion rapide */
  const handleSuggestion = (suggestion) => {
    setQuery(suggestion);
    textareaRef.current?.focus();
  };

  /** Appel API : POST /search/ai */
  const handleSearch = async (e) => {
    e?.preventDefault();
    const trimmed = query.trim();
    if (!trimmed) return;

    setIsLoading(true);
    setError(null);
    setResults(null);

    try {
      const response = await fetch('http://localhost:8000/api/search/ai', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query: trimmed }),
      });

      if (!response.ok) {
        throw new Error(`Erreur serveur : ${response.status}`);
      }

      const data = await response.json();
      setResults(data);
    } catch (err) {
      setError(
        err.message?.includes('fetch')
          ? "Impossible de joindre le serveur. Vérifiez que le backend est démarré."
          : err.message || "Une erreur inattendue s'est produite."
      );
    } finally {
      setIsLoading(false);
    }
  };

  /** Navigation vers la page de résultats complète */
  const handleSeeAll = () => {
    if (!query.trim()) return;
    navigate(`/search?q=${encodeURIComponent(query.trim())}&ai=true`);
    handleClose();
  };

  // ── Rendu ─────────────────────────────────────────────────────────────────────

  if (!isOpen) return null;

  return (
    /* Overlay sombre */
    <div
      ref={overlayRef}
      onClick={handleOverlayClick}
      className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-start md:items-center justify-center p-0 md:p-4
                 animate-in fade-in duration-200"
    >
      {/* Panneau principal */}
      <div
        className="relative w-full md:max-w-2xl bg-white md:rounded-2xl shadow-2xl
                   flex flex-col max-h-screen md:max-h-[90vh] overflow-hidden
                   animate-in slide-in-from-bottom-4 md:slide-in-from-bottom-0 md:zoom-in-95 duration-200"
      >

        {/* ── En-tête du modal ── */}
        <div className="flex-shrink-0 px-6 pt-6 pb-4 border-b border-gray-100">
          <div className="flex items-start justify-between gap-4">
            <div>
              <div className="flex items-center gap-2 mb-1">
                <div className="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                  <Sparkles size={16} className="text-purple-600" />
                </div>
                <h2 className="text-lg font-serif font-bold text-gray-900">
                  Décrivez ce que vous cherchez
                </h2>
              </div>
              <p className="text-sm text-gray-500 ml-10">
                Dites-moi ce dont vous avez besoin en langage naturel
              </p>
            </div>
            <button
              onClick={handleClose}
              className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors flex-shrink-0"
              aria-label="Fermer"
            >
              <X size={18} />
            </button>
          </div>

          {/* ── Formulaire de saisie ── */}
          <form onSubmit={handleSearch} className="mt-4">
            <textarea
              ref={textareaRef}
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              onKeyDown={(e) => {
                // Ctrl+Enter ou Cmd+Enter → soumet
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') handleSearch();
              }}
              placeholder="Ex: j'aimerais un ensemble de couverts dorés avec une table en bois..."
              rows={3}
              className="w-full resize-none px-4 py-3 text-sm border border-gray-200 rounded-xl bg-gray-50
                         focus:outline-none focus:ring-2 focus:ring-purple-300 focus:border-purple-400
                         placeholder-gray-400 transition-all"
            />

            {/* Suggestions rapides */}
            <div className="flex flex-wrap gap-2 mt-3">
              {SUGGESTIONS.map((s) => (
                <button
                  key={s}
                  type="button"
                  onClick={() => handleSuggestion(s)}
                  className="px-3 py-1.5 text-xs font-medium rounded-full border border-purple-200
                             text-purple-700 bg-purple-50 hover:bg-purple-100 hover:border-purple-300
                             transition-colors"
                >
                  {s}
                </button>
              ))}
            </div>

            {/* Bouton de lancement */}
            <button
              type="submit"
              disabled={!query.trim() || isLoading}
              className="mt-4 w-full flex items-center justify-center gap-2 py-3 px-6
                         bg-purple-600 hover:bg-purple-700 active:bg-purple-800
                         disabled:opacity-50 disabled:cursor-not-allowed
                         text-white font-semibold text-sm rounded-xl transition-colors"
            >
              <Sparkles size={16} />
              Rechercher avec l'IA ✨
            </button>
          </form>
        </div>

        {/* ── Zone de résultats (scrollable) ── */}
        <div className="flex-1 overflow-y-auto px-6 py-4">

          {/* État de chargement */}
          {isLoading && (
            <div className="flex flex-col items-center justify-center py-12 gap-4">
              <div className="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center animate-pulse">
                <span className="text-2xl">🤖</span>
              </div>
              <div className="text-center">
                <p className="text-sm font-medium text-gray-700">
                  🤖 Claude analyse votre recherche
                  <LoadingDots />
                </p>
                <p className="text-xs text-gray-400 mt-1">Extraction des mots-clés en cours</p>
              </div>
            </div>
          )}

          {/* Message d'erreur */}
          {error && !isLoading && (
            <div className="flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
              <span className="text-lg leading-none mt-0.5">⚠️</span>
              <div>
                <p className="font-medium">Une erreur est survenue</p>
                <p className="mt-0.5 text-red-600">{error}</p>
              </div>
            </div>
          )}

          {/* Résultats IA */}
          {results && !isLoading && (
            <div className="space-y-5">

              {/* Message IA (bulle de chat) */}
              {results.ai_message && (
                <div className="flex items-start gap-3">
                  <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span className="text-sm">🤖</span>
                  </div>
                  <div className="bg-green-50 border border-green-200 rounded-2xl rounded-tl-sm px-4 py-3 text-sm text-green-800 flex-1">
                    {results.ai_message}
                  </div>
                </div>
              )}

              {/* Mots-clés extraits */}
              {results.keywords?.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                    Mots-clés :
                  </span>
                  {results.keywords.map((kw) => (
                    <span
                      key={kw}
                      className="px-2.5 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800 border border-amber-200"
                    >
                      {kw}
                    </span>
                  ))}
                </div>
              )}

              {/* Nombre de résultats */}
              <p className="text-sm text-gray-500">
                <span className="font-semibold text-gray-800">{results.total ?? results.products?.length ?? 0}</span>{' '}
                produit{(results.total ?? results.products?.length ?? 0) !== 1 ? 's' : ''} trouvé
                {(results.total ?? results.products?.length ?? 0) !== 1 ? 's' : ''}
              </p>

              {/* Grille de produits (4 col desktop, 2 mobile) */}
              {results.products?.length > 0 ? (
                <>
                  <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    {results.products.slice(0, 8).map((product) => (
                      <div key={product.id} onClick={handleClose}>
                        <ProductCard product={product} />
                      </div>
                    ))}
                  </div>

                  {/* Lien "Voir tous les résultats" */}
                  {(results.total ?? 0) > 8 && (
                    <button
                      onClick={handleSeeAll}
                      className="w-full flex items-center justify-center gap-2 py-3 px-4
                                 border border-gray-200 rounded-xl text-sm font-medium
                                 text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-colors"
                    >
                      Voir tous les résultats
                      <ChevronRight size={16} />
                    </button>
                  )}
                </>
              ) : (
                /* État vide */
                <div className="flex flex-col items-center justify-center py-10 text-center gap-3">
                  <span className="text-4xl">🔍</span>
                  <div>
                    <p className="font-medium text-gray-700">Aucun produit trouvé</p>
                    <p className="text-sm text-gray-500 mt-1">
                      Essayez d'autres mots-clés ou une description plus générale.
                    </p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* État initial (aucune recherche encore) */}
          {!results && !isLoading && !error && (
            <div className="flex flex-col items-center justify-center py-10 text-center gap-3">
              <div className="w-16 h-16 bg-purple-50 rounded-2xl flex items-center justify-center">
                <Search size={28} className="text-purple-300" />
              </div>
              <div>
                <p className="font-medium text-gray-600">Décrivez votre recherche ci-dessus</p>
                <p className="text-sm text-gray-400 mt-1">
                  Claude comprend le langage naturel — soyez précis !
                </p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

/** Sous-composant : trois points clignotants d'animation de chargement */
function LoadingDots() {
  return (
    <span className="inline-flex gap-0.5 ml-1 align-middle">
      {[0, 1, 2].map((i) => (
        <span
          key={i}
          className="w-1 h-1 bg-gray-600 rounded-full inline-block animate-bounce"
          style={{ animationDelay: `${i * 0.15}s` }}
        />
      ))}
    </span>
  );
}
