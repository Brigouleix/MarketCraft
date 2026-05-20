import React from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Store, Star, Package, Calendar, ArrowLeft } from 'lucide-react';
import { boutiquesAPI, productsAPI } from '../services/api';
import ProductCard from '../components/ProductCard';

const PLACEHOLDER_BANNER =
  'https://images.unsplash.com/photo-1555529669-e69e7aa0ba9a?w=1200&q=80';

const MOCK_BOUTIQUE = {
  id: 1,
  nom: 'Céramiques de Lyon',
  description:
    'Atelier de poterie artisanale fondé en 1987 au cœur de Lyon. Nous façonnons chaque pièce à la main avec des argiles locales et des émaux naturels, dans le respect des traditions ancestrales de la céramique française.',
  image: 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=1200&q=80',
  nb_produits: 24,
  note_moyenne: 4.8,
  nb_avis: 142,
  created_at: '2021-06-15T00:00:00Z',
  artisan: { nom: 'Sophie Bernard', avatar: null },
};

const MOCK_PRODUCTS = Array.from({ length: 8 }, (_, i) => ({
  id: i + 1,
  nom: [
    'Vase en grès émaillé',
    'Bol à céréales fait main',
    'Théière artisanale',
    'Assiette creuse peinte',
    'Mug en céramique rustique',
    'Pichet en terre cuite',
    'Coupelle décorative',
    'Set de bols à sauce',
  ][i],
  prix: [65, 28, 95, 35, 22, 48, 18, 42][i],
  note_moyenne: [4.9, 4.7, 4.8, 4.6, 4.9, 4.5, 4.7, 4.8][i],
  nb_avis: [32, 18, 24, 15, 41, 12, 28, 9][i],
  stock: [5, 10, 3, 8, 15, 4, 20, 7][i],
  categorie: 'ceramique',
  boutique: { id: 1, nom: 'Céramiques de Lyon' },
  image: `https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=400&q=70&sig=${i}`,
}));

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

export default function BoutiquePage() {
  const { id } = useParams();

  const { data: boutiqueData, isLoading: boutiqueLoading } = useQuery({
    queryKey: ['boutique', id],
    queryFn: async () => {
      const { data } = await boutiquesAPI.getById(id);
      return data;
    },
    staleTime: 1000 * 60 * 5,
  });

  const { data: productsData, isLoading: productsLoading } = useQuery({
    queryKey: ['products', { boutique_id: id }],
    queryFn: async () => {
      const { data } = await productsAPI.getAll({ boutique_id: id, per_page: 24 });
      return data;
    },
    staleTime: 1000 * 60 * 2,
  });

  const boutique = boutiqueData?.boutique || boutiqueData || MOCK_BOUTIQUE;
  const products =
    productsData?.data || productsData?.products || (productsLoading ? [] : MOCK_PRODUCTS);

  if (boutiqueLoading) {
    return (
      <div className="animate-pulse">
        <div className="h-56 bg-secondary-300 w-full" />
        <div className="max-w-7xl mx-auto px-4 py-10 space-y-4">
          <div className="h-8 bg-secondary-300 rounded w-1/3" />
          <div className="h-4 bg-secondary-300 rounded w-2/3" />
          <div className="h-4 bg-secondary-300 rounded w-1/2" />
        </div>
      </div>
    );
  }

  return (
    <div>
      {/* Banner */}
      <div
        className="relative h-56 md:h-72 bg-cover bg-center"
        style={{ backgroundImage: `url(${boutique.image || PLACEHOLDER_BANNER})` }}
      >
        <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent" />

        {/* Back link */}
        <div className="absolute top-4 left-4">
          <Link
            to="/produits"
            className="flex items-center gap-1.5 text-white/80 hover:text-white text-sm bg-black/30 backdrop-blur-sm px-3 py-1.5 rounded-lg transition-colors"
          >
            <ArrowLeft size={14} /> Retour aux produits
          </Link>
        </div>

        {/* Boutique identity */}
        <div className="absolute bottom-0 left-0 right-0 p-6 md:p-10">
          <div className="max-w-7xl mx-auto flex items-end gap-5">
            <div className="w-20 h-20 md:w-24 md:h-24 rounded-2xl bg-white shadow-lg overflow-hidden flex-shrink-0 border-4 border-white">
              {boutique.image ? (
                <img
                  src={boutique.image}
                  alt={boutique.nom}
                  className="w-full h-full object-cover"
                />
              ) : (
                <div className="w-full h-full bg-primary flex items-center justify-center">
                  <Store size={32} className="text-white" />
                </div>
              )}
            </div>
            <div className="pb-1">
              <h1 className="text-2xl md:text-4xl font-serif font-bold text-white drop-shadow-lg">
                {boutique.nom}
              </h1>
              {boutique.artisan && (
                <p className="text-white/80 text-sm mt-1">par {boutique.artisan.nom}</p>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        {/* Meta row */}
        <div className="flex flex-wrap items-center gap-6 mb-8 pb-8 border-b border-secondary-200">
          {/* Stats */}
          <div className="flex items-center gap-1.5 text-sm text-gray-700">
            <Package size={16} className="text-accent" />
            <strong>{boutique.nb_produits || products.length}</strong> produits
          </div>

          {boutique.note_moyenne > 0 && (
            <div className="flex items-center gap-1.5 text-sm text-gray-700">
              <Star size={16} className="text-amber-400 fill-amber-400" />
              <strong>{Number(boutique.note_moyenne).toFixed(1)}</strong>
              {boutique.nb_avis && (
                <span className="text-gray-500">({boutique.nb_avis} avis)</span>
              )}
            </div>
          )}

          {boutique.created_at && (
            <div className="flex items-center gap-1.5 text-sm text-gray-500">
              <Calendar size={14} />
              Membre depuis{' '}
              {new Date(boutique.created_at).toLocaleDateString('fr-FR', {
                month: 'long',
                year: 'numeric',
              })}
            </div>
          )}
        </div>

        {/* Description + Products layout */}
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-10">
          {/* Sidebar: description */}
          <aside className="lg:col-span-1">
            <div className="card p-5 sticky top-20">
              <h2 className="font-serif font-semibold text-gray-800 mb-3">À propos</h2>
              <p className="text-sm text-gray-600 leading-relaxed">
                {boutique.description ||
                  'Cette boutique artisanale propose des créations uniques, fabriquées avec passion et savoir-faire.'}
              </p>

              {/* Quick stats */}
              <div className="mt-5 space-y-3 border-t border-secondary-200 pt-4">
                <div className="flex items-center justify-between text-sm">
                  <span className="text-gray-500">Produits</span>
                  <span className="font-semibold text-gray-800">
                    {boutique.nb_produits || products.length}
                  </span>
                </div>
                {boutique.note_moyenne > 0 && (
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-gray-500">Note</span>
                    <span className="font-semibold text-amber-600 flex items-center gap-1">
                      <Star size={12} fill="currentColor" />
                      {Number(boutique.note_moyenne).toFixed(1)}
                    </span>
                  </div>
                )}
                {boutique.nb_avis && (
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-gray-500">Avis</span>
                    <span className="font-semibold text-gray-800">{boutique.nb_avis}</span>
                  </div>
                )}
              </div>
            </div>
          </aside>

          {/* Products grid */}
          <div className="lg:col-span-3">
            <div className="flex items-center justify-between mb-6">
              <h2 className="font-serif font-bold text-xl text-gray-800">
                Tous les produits
              </h2>
              <span className="text-sm text-gray-500">
                {products.length} produit{products.length !== 1 ? 's' : ''}
              </span>
            </div>

            {productsLoading ? (
              <div className="grid grid-cols-2 md:grid-cols-3 gap-5">
                {Array.from({ length: 6 }).map((_, i) => (
                  <ProductSkeleton key={i} />
                ))}
              </div>
            ) : products.length === 0 ? (
              <div className="text-center py-20 flex flex-col items-center gap-4">
                <Store size={56} className="text-secondary-400" />
                <h3 className="text-xl font-serif font-semibold text-gray-700">
                  Aucun produit disponible
                </h3>
                <p className="text-gray-500 text-sm">
                  Cette boutique n'a pas encore publié de produits.
                </p>
                <Link to="/produits" className="btn-primary">
                  Explorer d'autres boutiques
                </Link>
              </div>
            ) : (
              <div className="grid grid-cols-2 md:grid-cols-3 gap-5">
                {products.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
