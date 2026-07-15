import React, { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Store, Package, Star, ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { boutiquesAPI } from '../services/api';

const PLACEHOLDER = 'https://images.unsplash.com/photo-1555529669-e69e7aa0ba9a?w=600&q=70';

function BoutiqueSkeleton() {
  return (
    <div className="card animate-pulse">
      <div className="aspect-video bg-secondary-300" />
      <div className="p-4 space-y-3">
        <div className="h-4 bg-secondary-300 rounded w-2/3" />
        <div className="h-3 bg-secondary-300 rounded w-full" />
        <div className="h-3 bg-secondary-300 rounded w-1/2" />
      </div>
    </div>
  );
}

function BoutiqueCard({ boutique }) {
  const cover = boutique.banniere_url || boutique.logo_url || PLACEHOLDER;

  return (
    <Link
      to={`/boutiques/${boutique.id}`}
      className="card group flex flex-col hover:shadow-craft-lg transition-shadow duration-300"
    >
      <div className="relative overflow-hidden aspect-video bg-secondary-200">
        <img
          src={cover}
          alt={boutique.nom}
          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
          onError={(e) => { e.currentTarget.src = PLACEHOLDER; }}
        />
        {boutique.logo_url && (
          <div className="absolute bottom-3 left-3 w-12 h-12 rounded-xl bg-white shadow-md overflow-hidden border-2 border-white">
            <img src={boutique.logo_url} alt="" className="w-full h-full object-cover" />
          </div>
        )}
      </div>

      <div className="p-4 flex-1 flex flex-col">
        <h3 className="font-serif font-bold text-gray-800 mb-1">{boutique.nom}</h3>
        {boutique.description && (
          <p className="text-sm text-gray-500 line-clamp-2 flex-1">{boutique.description}</p>
        )}
        <div className="flex items-center gap-4 mt-3 pt-3 border-t border-secondary-100 text-xs text-gray-500">
          <span className="flex items-center gap-1">
            <Package size={13} className="text-accent" />
            {boutique.nb_produits ?? 0} produit{(boutique.nb_produits ?? 0) !== 1 ? 's' : ''}
          </span>
          {boutique.note_moyenne > 0 && (
            <span className="flex items-center gap-1">
              <Star size={13} className="text-amber-400 fill-amber-400" />
              {Number(boutique.note_moyenne).toFixed(1)}
            </span>
          )}
        </div>
      </div>
    </Link>
  );
}

export default function BoutiquesPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const page = Number(searchParams.get('page')) || 1;
  const [search, setSearch] = useState('');

  const { data, isLoading, isError } = useQuery({
    queryKey: ['boutiques', { page, limit: 12 }],
    queryFn: async () => {
      const { data } = await boutiquesAPI.getAll({ page, limit: 12 });
      return data;
    },
    staleTime: 1000 * 60 * 5,
  });

  const boutiques = data?.data || [];
  const totalPages = data?.pagination?.total_pages || 1;
  const total = data?.pagination?.total ?? boutiques.length;

  const filtered = search.trim()
    ? boutiques.filter((b) => b.nom.toLowerCase().includes(search.trim().toLowerCase()))
    : boutiques;

  const goToPage = (p) => {
    const newParams = new URLSearchParams(searchParams);
    newParams.set('page', p);
    setSearchParams(newParams);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      {/* Header */}
      <div className="mb-8">
        <h1 className="section-title">Nos boutiques artisanales</h1>
        <p className="text-gray-500 text-sm">
          {isLoading ? 'Chargement…' : `${total} boutique${total > 1 ? 's' : ''}`}
        </p>
      </div>

      {/* Search */}
      <div className="relative max-w-md mb-8">
        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Rechercher une boutique…"
          className="input-field pl-9 text-sm"
        />
      </div>

      {isError && !isLoading && (
        <div className="text-center py-12 text-gray-500">
          <p className="text-lg mb-2">Impossible de charger les boutiques.</p>
        </div>
      )}

      {isLoading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {Array.from({ length: 6 }).map((_, i) => <BoutiqueSkeleton key={i} />)}
        </div>
      ) : filtered.length === 0 ? (
        <div className="text-center py-20 flex flex-col items-center gap-4">
          <Store size={56} className="text-secondary-400" />
          <h3 className="text-xl font-serif font-semibold text-gray-700">
            {search ? 'Aucune boutique ne correspond' : 'Aucune boutique disponible'}
          </h3>
          <p className="text-gray-500 text-sm">
            {search ? 'Essayez un autre terme de recherche.' : 'Reviens un peu plus tard.'}
          </p>
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {filtered.map((boutique) => (
              <BoutiqueCard key={boutique.id} boutique={boutique} />
            ))}
          </div>

          {!search && totalPages > 1 && (
            <div className="flex justify-center items-center gap-2 mt-10">
              <button
                onClick={() => goToPage(page - 1)}
                disabled={page <= 1}
                className="p-2 rounded-lg border border-secondary-300 hover:bg-secondary-200 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <ChevronLeft size={18} />
              </button>

              {Array.from({ length: totalPages }, (_, i) => i + 1).map((p) => (
                <button
                  key={p}
                  onClick={() => goToPage(p)}
                  className={`w-9 h-9 rounded-lg text-sm font-medium transition-colors ${
                    p === page
                      ? 'bg-primary text-white'
                      : 'border border-secondary-300 hover:bg-secondary-200 text-gray-700'
                  }`}
                >
                  {p}
                </button>
              ))}

              <button
                onClick={() => goToPage(page + 1)}
                disabled={page >= totalPages}
                className="p-2 rounded-lg border border-secondary-300 hover:bg-secondary-200 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <ChevronRight size={18} />
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
