/**
 * SearchResultsPage.jsx
 * Page de résultats de recherche IA.
 * URL : /search?q=...&ai=true
 *
 * Fonctionnalités :
 *  - Appel automatique à l'API IA au montage (paramètre `q`)
 *  - Message IA affiché en bulle de chat
 *  - Mots-clés en badges colorés
 *  - Filtre par prix (min/max)
 *  - Tri : pertinence | prix croissant | prix décroissant | note
 *  - Pagination (12 produits par page)
 *  - État vide, chargement, erreur
 */

import React, { useState, useEffect, useMemo } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { Sparkles, ArrowLeft, ChevronLeft, ChevronRight, SlidersHorizontal, X } from 'lucide-react';
import ProductCard from '../components/ProductCard';

// Nombre de produits affichés par page
const PAGE_SIZE = 12;

// Options de tri
const SORT_OPTIONS = [
  { value: 'relevance', label: 'Pertinence' },
  { value: 'price_asc',  label: 'Prix croissant' },
  { value: 'price_desc', label: 'Prix décroissant' },
  { value: 'rating',     label: 'Meilleures notes' },
];

export default function SearchResultsPage() {
  const [searchParams] = useSearchParams();
  const query = searchParams.get('q') || '';

  // ── États ────────────────────────────────────────────────────────────────────
  const [isLoading, setIsLoading]   = useState(false);
  const [rawResults, setRawResults] = useState(null);  // réponse brute de l'API
  const [error, setError]           = useState(null);

  // Filtres & tri
  const [minPrice, setMinPrice] = useState('');
  const [maxPrice, setMaxPrice] = useState('');
  const [sortBy, setSortBy]     = useState('relevance');
  const [showFilters, setShowFilters] = useState(false);

  // Pagination
  const [page, setPage] = useState(1);

  // ── Appel API ────────────────────────────────────────────────────────────────
  useEffect(() => {
    if (!query) return;

    const fetchResults = async () => {
      setIsLoading(true);
      setError(null);
      setRawResults(null);
      setPage(1);

      try {
        const response = await fetch('http://localhost:8000/api/search/ai', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ query }),
        });

        if (!response.ok) throw new Error(`Erreur serveur : ${response.status}`);

        const data = await response.json();
        setRawResults(data);
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

    fetchResults();
  }, [query]);

  // ── Produits filtrés & triés ─────────────────────────────────────────────────
  const processedProducts = useMemo(() => {
    const products = rawResults?.products ?? [];

    // Filtrage par prix
    let filtered = products.filter((p) => {
      const price = Number(p.prix ?? 0);
      if (minPrice !== '' && price < Number(minPrice)) return false;
      if (maxPrice !== '' && price > Number(maxPrice)) return false;
      return true;
    });

    // Tri
    switch (sortBy) {
      case 'price_asc':
        filtered = [...filtered].sort((a, b) => Number(a.prix) - Number(b.prix));
        break;
      case 'price_desc':
        filtered = [...filtered].sort((a, b) => Number(b.prix) - Number(a.prix));
        break;
      case 'rating':
        filtered = [...filtered].sort((a, b) => Number(b.note_moyenne ?? 0) - Number(a.note_moyenne ?? 0));
        break;
      default:
        // Pertinence = ordre retourné par l'IA
        break;
    }

    return filtered;
  }, [rawResults, minPrice, maxPrice, sortBy]);

  // ── Pagination ───────────────────────────────────────────────────────────────
  const totalPages = Math.max(1, Math.ceil(processedProducts.length / PAGE_SIZE));
  const paginatedProducts = processedProducts.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  // Réinitialise la page quand les filtres changent
  useEffect(() => { setPage(1); }, [minPrice, maxPrice, sortBy]);

  // ── Réinitialisation des filtres ─────────────────────────────────────────────
  const hasActiveFilters = minPrice !== '' || maxPrice !== '' || sortBy !== 'relevance';
  const resetFilters = () => {
    setMinPrice('');
    setMaxPrice('');
    setSortBy('relevance');
  };

  // ── Rendu ─────────────────────────────────────────────────────────────────────
  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {/* ── Retour + fil d'Ariane ── */}
        <div className="mb-6">
          <Link
            to="/"
            className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 transition-colors mb-4"
          >
            <ArrowLeft size={15} />
            Retour à l'accueil
          </Link>

          {/* ── En-tête de la page ── */}
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
              <Sparkles size={20} className="text-purple-600" />
            </div>
            <div className="flex-1">
              <h1 className="text-2xl md:text-3xl font-serif font-bold text-gray-900">
                Résultats pour
              </h1>
              <p className="text-gray-500 mt-0.5 text-sm md:text-base italic">
                "{query}"
              </p>
            </div>
          </div>
        </div>

        {/* ── État de chargement ── */}
        {isLoading && (
          <div className="flex flex-col items-center justify-center py-24 gap-5">
            <div className="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center animate-pulse">
              <span className="text-3xl">🤖</span>
            </div>
            <div className="text-center">
              <p className="font-semibold text-gray-700 text-lg">Claude analyse votre recherche…</p>
              <p className="text-sm text-gray-400 mt-1">Extraction des mots-clés et recherche en base</p>
            </div>
            <div className="w-8 h-8 border-4 border-purple-400 border-t-transparent rounded-full animate-spin" />
          </div>
        )}

        {/* ── Erreur ── */}
        {error && !isLoading && (
          <div className="flex flex-col items-center justify-center py-16 gap-4 text-center">
            <span className="text-5xl">⚠️</span>
            <div>
              <p className="text-lg font-semibold text-red-700">Une erreur est survenue</p>
              <p className="text-sm text-red-600 mt-1 max-w-md">{error}</p>
            </div>
            <Link to="/" className="btn-primary mt-2">
              Retourner à l'accueil
            </Link>
          </div>
        )}

        {/* ── Pas de query ── */}
        {!query && !isLoading && (
          <div className="flex flex-col items-center justify-center py-16 gap-4 text-center">
            <span className="text-5xl">🔍</span>
            <p className="text-lg text-gray-600">Aucune recherche spécifiée.</p>
            <Link to="/" className="btn-primary">Retourner à l'accueil</Link>
          </div>
        )}

        {/* ── Résultats disponibles ── */}
        {rawResults && !isLoading && (
          <div className="space-y-6">

            {/* Bulle de message IA */}
            {rawResults.ai_message && (
              <div className="flex items-start gap-3 bg-white border border-green-200 rounded-2xl p-4 shadow-sm">
                <div className="w-9 h-9 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                  <span className="text-lg">🤖</span>
                </div>
                <div className="flex-1">
                  <p className="text-xs font-semibold text-green-700 uppercase tracking-wide mb-1">
                    Claude
                  </p>
                  <p className="text-sm text-gray-700 leading-relaxed">{rawResults.ai_message}</p>
                </div>
              </div>
            )}

            {/* Mots-clés extraits */}
            {rawResults.keywords?.length > 0 && (
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                  Mots-clés détectés :
                </span>
                {rawResults.keywords.map((kw) => (
                  <span
                    key={kw}
                    className="px-3 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800 border border-amber-200"
                  >
                    {kw}
                  </span>
                ))}
              </div>
            )}

            {/* ── Barre filtres + tri ── */}
            <div className="flex flex-wrap items-center justify-between gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3 shadow-sm">
              {/* Compteur */}
              <p className="text-sm text-gray-600">
                <span className="font-bold text-gray-900">{processedProducts.length}</span>{' '}
                produit{processedProducts.length !== 1 ? 's' : ''} trouvé{processedProducts.length !== 1 ? 's' : ''}
                {hasActiveFilters && (
                  <span className="text-gray-400"> (filtré{processedProducts.length !== 1 ? 's' : ''})</span>
                )}
              </p>

              <div className="flex items-center gap-3 flex-wrap">
                {/* Bouton toggle filtres (mobile) */}
                <button
                  onClick={() => setShowFilters((v) => !v)}
                  className="flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-900
                             border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-50 transition-colors"
                >
                  <SlidersHorizontal size={14} />
                  Filtres
                  {hasActiveFilters && (
                    <span className="w-1.5 h-1.5 bg-purple-500 rounded-full" />
                  )}
                </button>

                {/* Sélecteur de tri */}
                <select
                  value={sortBy}
                  onChange={(e) => setSortBy(e.target.value)}
                  className="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white
                             focus:outline-none focus:ring-2 focus:ring-purple-300 text-gray-700"
                >
                  {SORT_OPTIONS.map((opt) => (
                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                  ))}
                </select>
              </div>
            </div>

            {/* Panneau de filtres (collapsible) */}
            {showFilters && (
              <div className="bg-white rounded-xl border border-gray-200 px-4 py-4 shadow-sm">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-sm font-semibold text-gray-700">Filtrer par prix</h3>
                  {hasActiveFilters && (
                    <button
                      onClick={resetFilters}
                      className="flex items-center gap-1 text-xs text-red-500 hover:text-red-700 transition-colors"
                    >
                      <X size={12} />
                      Réinitialiser
                    </button>
                  )}
                </div>
                <div className="flex items-center gap-3 flex-wrap">
                  <div className="flex items-center gap-2">
                    <label className="text-xs text-gray-500 whitespace-nowrap">Prix min :</label>
                    <input
                      type="number"
                      value={minPrice}
                      onChange={(e) => setMinPrice(e.target.value)}
                      placeholder="0"
                      min="0"
                      className="w-24 text-sm border border-gray-200 rounded-lg px-3 py-1.5
                                 focus:outline-none focus:ring-2 focus:ring-purple-300"
                    />
                    <span className="text-xs text-gray-400">€</span>
                  </div>
                  <span className="text-gray-300">—</span>
                  <div className="flex items-center gap-2">
                    <label className="text-xs text-gray-500 whitespace-nowrap">Prix max :</label>
                    <input
                      type="number"
                      value={maxPrice}
                      onChange={(e) => setMaxPrice(e.target.value)}
                      placeholder="∞"
                      min="0"
                      className="w-24 text-sm border border-gray-200 rounded-lg px-3 py-1.5
                                 focus:outline-none focus:ring-2 focus:ring-purple-300"
                    />
                    <span className="text-xs text-gray-400">€</span>
                  </div>
                </div>
              </div>
            )}

            {/* ── Grille de produits ── */}
            {paginatedProducts.length > 0 ? (
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
                {paginatedProducts.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
            ) : (
              /* État vide après filtrage */
              <div className="flex flex-col items-center justify-center py-16 gap-4 text-center bg-white rounded-2xl border border-dashed border-gray-200">
                <span className="text-5xl">🎨</span>
                <div>
                  <p className="text-lg font-semibold text-gray-700">Aucun produit ne correspond</p>
                  <p className="text-sm text-gray-500 mt-1 max-w-xs">
                    Essayez d'élargir vos filtres de prix ou de modifier votre recherche.
                  </p>
                </div>
                {hasActiveFilters && (
                  <button onClick={resetFilters} className="btn-secondary text-sm">
                    Effacer les filtres
                  </button>
                )}
              </div>
            )}

            {/* ── Pagination ── */}
            {totalPages > 1 && (
              <div className="flex items-center justify-center gap-2 pt-4">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page === 1}
                  className="p-2 rounded-lg border border-gray-200 text-gray-600
                             hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                  aria-label="Page précédente"
                >
                  <ChevronLeft size={18} />
                </button>

                {/* Numéros de pages */}
                {Array.from({ length: totalPages }, (_, i) => i + 1)
                  .filter((p) => Math.abs(p - page) <= 2 || p === 1 || p === totalPages)
                  .reduce((acc, p, idx, arr) => {
                    if (idx > 0 && p - arr[idx - 1] > 1) {
                      acc.push('...');
                    }
                    acc.push(p);
                    return acc;
                  }, [])
                  .map((item, idx) =>
                    item === '...' ? (
                      <span key={`ellipsis-${idx}`} className="px-1 text-gray-400 text-sm">…</span>
                    ) : (
                      <button
                        key={item}
                        onClick={() => setPage(item)}
                        className={`w-9 h-9 rounded-lg text-sm font-medium transition-colors
                          ${item === page
                            ? 'bg-purple-600 text-white'
                            : 'border border-gray-200 text-gray-700 hover:bg-gray-50'
                          }`}
                      >
                        {item}
                      </button>
                    )
                  )
                }

                <button
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={page === totalPages}
                  className="p-2 rounded-lg border border-gray-200 text-gray-600
                             hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                  aria-label="Page suivante"
                >
                  <ChevronRight size={18} />
                </button>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
