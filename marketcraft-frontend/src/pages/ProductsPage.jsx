import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Search, SlidersHorizontal, X, ChevronLeft, ChevronRight } from 'lucide-react';
import { useProducts } from '../hooks/useProducts';
import ProductCard from '../components/ProductCard';

const CATEGORIES = [
  'bijoux', 'ceramique', 'mode', 'decoration', 'floral', 'art', 'textile', 'papeterie', 'cuisine', 'autre',
];

const TRI_OPTIONS = [
  { value: '', label: 'Pertinence' },
  { value: 'prix_asc', label: 'Prix croissant' },
  { value: 'prix_desc', label: 'Prix décroissant' },
  { value: 'recent', label: 'Plus récents' },
  { value: 'populaire', label: 'Plus populaires' },
];

function ProductSkeleton() {
  return (
    <div className="card animate-pulse">
      <div className="aspect-square bg-secondary-300" />
      <div className="p-4 space-y-3">
        <div className="h-3 bg-secondary-300 rounded w-1/3" />
        <div className="h-4 bg-secondary-300 rounded w-2/3" />
        <div className="h-3 bg-secondary-300 rounded w-1/2" />
        <div className="flex justify-between items-center mt-2">
          <div className="h-6 bg-secondary-300 rounded w-16" />
          <div className="h-8 bg-secondary-300 rounded w-20" />
        </div>
      </div>
    </div>
  );
}

export default function ProductsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const [filters, setFilters] = useState({
    search: searchParams.get('search') || '',
    categorie: searchParams.get('categorie') || '',
    prix_min: searchParams.get('prix_min') || '',
    prix_max: searchParams.get('prix_max') || '',
    note_min: Number(searchParams.get('note_min')) || 0,
    tri: searchParams.get('tri') || '',
    page: Number(searchParams.get('page')) || 1,
  });

  const [localSearch, setLocalSearch] = useState(filters.search);

  // Sync URL params → filters
  useEffect(() => {
    setFilters({
      search: searchParams.get('search') || '',
      categorie: searchParams.get('categorie') || '',
      prix_min: searchParams.get('prix_min') || '',
      prix_max: searchParams.get('prix_max') || '',
      note_min: Number(searchParams.get('note_min')) || 0,
      tri: searchParams.get('tri') || '',
      page: Number(searchParams.get('page')) || 1,
    });
    setLocalSearch(searchParams.get('search') || '');
  }, [searchParams]);

  const { data, isLoading, isError } = useProducts(filters);

  // Use mock data when API is unavailable
  const mockProducts = Array.from({ length: 12 }, (_, i) => ({
    id: i + 1,
    nom: ['Vase en céramique', 'Collier argent', 'Panier osier', 'Carnet cuir', 'Bougie cire', 'Savon miel', 'Écharpe laine', 'Tasse grès', 'Bracelet bois', 'Brooche émail', 'Carafe soufflée', 'Plateau bambou'][i],
    prix: [45, 89, 35, 28, 18, 12, 65, 32, 22, 48, 75, 19][i],
    note_moyenne: [4.8, 4.9, 4.5, 4.7, 4.6, 4.9, 4.4, 4.8, 4.3, 4.7, 4.6, 4.5][i],
    nb_avis: [24, 18, 32, 15, 41, 55, 12, 28, 9, 17, 22, 38][i],
    stock: [10, 5, 0, 8, 20, 15, 3, 10, 7, 12, 4, 25][i],
    categorie: CATEGORIES[i % CATEGORIES.length],
    boutique: { id: (i % 4) + 1, nom: ['Céramiques de Lyon', 'Bijoux Céleste', 'Artisan du Midi', 'Maison Textile'][(i % 4)] },
    image: `https://picsum.photos/seed/${i + 10}/400/400`,
  }));

  const products = data?.data || data?.products || (isLoading ? [] : mockProducts);
  const totalPages = data?.last_page || data?.meta?.last_page || 1;
  const totalItems = data?.total || data?.meta?.total || mockProducts.length;

  const updateFilter = (key, value) => {
    const newParams = new URLSearchParams(searchParams);
    if (value === '' || value === 0) newParams.delete(key);
    else newParams.set(key, value);
    if (key !== 'page') newParams.delete('page');
    setSearchParams(newParams);
  };

  const handleSearch = (e) => {
    e.preventDefault();
    updateFilter('search', localSearch.trim());
  };

  const clearFilters = () => {
    setSearchParams({});
    setLocalSearch('');
  };

  const hasActiveFilters = filters.search || filters.categorie || filters.prix_min || filters.prix_max || filters.note_min > 0;

  // Sidebar component
  const Sidebar = () => (
    <aside className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="font-serif font-bold text-lg text-primary">Filtres</h2>
        {hasActiveFilters && (
          <button onClick={clearFilters} className="text-xs text-red-500 hover:text-red-700 flex items-center gap-1">
            <X size={12} /> Effacer tout
          </button>
        )}
      </div>

      {/* Category */}
      <div>
        <h3 className="font-semibold text-sm text-gray-700 mb-3">Catégorie</h3>
        <div className="space-y-1.5">
          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="radio"
              name="categorie"
              value=""
              checked={!filters.categorie}
              onChange={() => updateFilter('categorie', '')}
              className="text-primary"
            />
            <span className="text-sm text-gray-700">Toutes</span>
          </label>
          {CATEGORIES.map((cat) => (
            <label key={cat} className="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                name="categorie"
                value={cat}
                checked={filters.categorie === cat}
                onChange={() => updateFilter('categorie', cat)}
                className="text-primary"
              />
              <span className="text-sm text-gray-700 capitalize">{cat}</span>
            </label>
          ))}
        </div>
      </div>

      {/* Price range */}
      <div>
        <h3 className="font-semibold text-sm text-gray-700 mb-3">Prix (€)</h3>
        <div className="flex gap-2 items-center">
          <input
            type="number"
            placeholder="Min"
            value={filters.prix_min}
            onChange={(e) => updateFilter('prix_min', e.target.value)}
            min={0}
            className="input-field text-sm py-2"
          />
          <span className="text-gray-400">–</span>
          <input
            type="number"
            placeholder="Max"
            value={filters.prix_max}
            onChange={(e) => updateFilter('prix_max', e.target.value)}
            min={0}
            className="input-field text-sm py-2"
          />
        </div>
      </div>

      {/* Min rating */}
      <div>
        <h3 className="font-semibold text-sm text-gray-700 mb-3">Note minimale</h3>
        <div className="space-y-1.5">
          {[0, 3, 3.5, 4, 4.5].map((val) => (
            <label key={val} className="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                name="note_min"
                value={val}
                checked={filters.note_min === val}
                onChange={() => updateFilter('note_min', val)}
                className="text-primary"
              />
              <span className="text-sm text-gray-700 flex items-center gap-1">
                {val === 0 ? 'Toutes' : `${val}★ et plus`}
              </span>
            </label>
          ))}
        </div>
      </div>
    </aside>
  );

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      {/* Page header */}
      <div className="mb-8">
        <h1 className="section-title">Tous les produits</h1>
        <p className="text-gray-500 text-sm">
          {isLoading ? 'Chargement…' : `${totalItems} produit${totalItems > 1 ? 's' : ''} trouvé${totalItems > 1 ? 's' : ''}`}
        </p>
      </div>

      {/* Search + Sort bar */}
      <div className="flex flex-col sm:flex-row gap-3 mb-6">
        <form onSubmit={handleSearch} className="flex flex-1 gap-2">
          <div className="relative flex-1">
            <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
              type="text"
              value={localSearch}
              onChange={(e) => setLocalSearch(e.target.value)}
              placeholder="Rechercher un produit, une boutique…"
              className="input-field pl-9 text-sm"
            />
          </div>
          <button type="submit" className="btn-primary py-2.5 px-4 text-sm whitespace-nowrap">
            Rechercher
          </button>
        </form>

        <div className="flex gap-2">
          <select
            value={filters.tri}
            onChange={(e) => updateFilter('tri', e.target.value)}
            className="input-field text-sm py-2 pr-8 cursor-pointer"
          >
            {TRI_OPTIONS.map(({ value, label }) => (
              <option key={value} value={value}>{label}</option>
            ))}
          </select>

          {/* Mobile filter toggle */}
          <button
            onClick={() => setSidebarOpen(true)}
            className="lg:hidden flex items-center gap-1.5 btn-secondary text-sm py-2"
          >
            <SlidersHorizontal size={15} />
            Filtres
            {hasActiveFilters && <span className="w-2 h-2 bg-primary rounded-full" />}
          </button>
        </div>
      </div>

      {/* Active filter chips */}
      {hasActiveFilters && (
        <div className="flex flex-wrap gap-2 mb-5">
          {filters.search && (
            <span className="flex items-center gap-1 text-xs bg-primary-100 text-primary px-3 py-1 rounded-full">
              "{filters.search}"
              <button onClick={() => { updateFilter('search', ''); setLocalSearch(''); }}><X size={11} /></button>
            </span>
          )}
          {filters.categorie && (
            <span className="flex items-center gap-1 text-xs bg-primary-100 text-primary px-3 py-1 rounded-full capitalize">
              {filters.categorie}
              <button onClick={() => updateFilter('categorie', '')}><X size={11} /></button>
            </span>
          )}
          {(filters.prix_min || filters.prix_max) && (
            <span className="flex items-center gap-1 text-xs bg-primary-100 text-primary px-3 py-1 rounded-full">
              {filters.prix_min || '0'}€ – {filters.prix_max || '∞'}€
              <button onClick={() => { updateFilter('prix_min', ''); updateFilter('prix_max', ''); }}><X size={11} /></button>
            </span>
          )}
          {filters.note_min > 0 && (
            <span className="flex items-center gap-1 text-xs bg-primary-100 text-primary px-3 py-1 rounded-full">
              {filters.note_min}★+
              <button onClick={() => updateFilter('note_min', 0)}><X size={11} /></button>
            </span>
          )}
        </div>
      )}

      <div className="flex gap-8">
        {/* Desktop Sidebar */}
        <div className="hidden lg:block w-56 flex-shrink-0">
          <div className="card p-5 sticky top-20">
            <Sidebar />
          </div>
        </div>

        {/* Mobile Sidebar overlay */}
        {sidebarOpen && (
          <div className="fixed inset-0 z-50 lg:hidden">
            <div className="absolute inset-0 bg-black/40" onClick={() => setSidebarOpen(false)} />
            <div className="absolute left-0 top-0 bottom-0 w-72 bg-white shadow-xl overflow-y-auto p-5">
              <div className="flex justify-between items-center mb-4">
                <h2 className="font-serif font-bold text-lg text-primary">Filtres</h2>
                <button onClick={() => setSidebarOpen(false)}>
                  <X size={20} className="text-gray-500" />
                </button>
              </div>
              <Sidebar />
              <button
                onClick={() => setSidebarOpen(false)}
                className="btn-primary w-full mt-6"
              >
                Appliquer
              </button>
            </div>
          </div>
        )}

        {/* Products grid */}
        <div className="flex-1 min-w-0">
          {isError && !isLoading && (
            <div className="text-center py-12 text-gray-500">
              <p className="text-lg mb-2">Impossible de charger les produits.</p>
              <p className="text-sm">Affichage des données de démonstration.</p>
            </div>
          )}

          {isLoading ? (
            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-5">
              {Array.from({ length: 12 }).map((_, i) => <ProductSkeleton key={i} />)}
            </div>
          ) : products.length === 0 ? (
            <div className="text-center py-20 flex flex-col items-center gap-4">
              <Search size={48} className="text-secondary-400" />
              <h3 className="text-xl font-serif font-semibold text-gray-700">Aucun produit trouvé</h3>
              <p className="text-gray-500 text-sm">Essayez d'autres filtres ou termes de recherche.</p>
              <button onClick={clearFilters} className="btn-primary">Effacer les filtres</button>
            </div>
          ) : (
            <>
              <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-5">
                {products.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>

              {/* Pagination */}
              {totalPages > 1 && (
                <div className="flex justify-center items-center gap-2 mt-10">
                  <button
                    onClick={() => updateFilter('page', filters.page - 1)}
                    disabled={filters.page <= 1}
                    className="p-2 rounded-lg border border-secondary-300 hover:bg-secondary-200 disabled:opacity-40 disabled:cursor-not-allowed"
                  >
                    <ChevronLeft size={18} />
                  </button>

                  {Array.from({ length: Math.min(totalPages, 7) }, (_, i) => {
                    let page;
                    if (totalPages <= 7) {
                      page = i + 1;
                    } else if (filters.page <= 4) {
                      page = i + 1;
                    } else if (filters.page >= totalPages - 3) {
                      page = totalPages - 6 + i;
                    } else {
                      page = filters.page - 3 + i;
                    }
                    return (
                      <button
                        key={page}
                        onClick={() => updateFilter('page', page)}
                        className={`w-9 h-9 rounded-lg text-sm font-medium transition-colors ${
                          page === filters.page
                            ? 'bg-primary text-white'
                            : 'border border-secondary-300 hover:bg-secondary-200 text-gray-700'
                        }`}
                      >
                        {page}
                      </button>
                    );
                  })}

                  <button
                    onClick={() => updateFilter('page', filters.page + 1)}
                    disabled={filters.page >= totalPages}
                    className="p-2 rounded-lg border border-secondary-300 hover:bg-secondary-200 disabled:opacity-40 disabled:cursor-not-allowed"
                  >
                    <ChevronRight size={18} />
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
