import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  ArrowRight,
  Leaf,
  Shield,
  Truck,
  Star,
  Palette,
  Gem,
  Coffee,
  Shirt,
  Home,
  FlowerIcon,
  Scissors,
  BookOpen,
} from 'lucide-react';
import { productsAPI, boutiquesAPI } from '../services/api';
import ProductCard from '../components/ProductCard';

const HERO_BG =
  'https://images.unsplash.com/photo-1452860606245-08befc0ff44b?w=1600&q=80';

const categories = [
  { label: 'Bijoux', icon: Gem, color: 'bg-amber-100 text-amber-600', slug: 'bijoux' },
  { label: 'Céramique', icon: Coffee, color: 'bg-orange-100 text-orange-600', slug: 'ceramique' },
  { label: 'Mode', icon: Shirt, color: 'bg-pink-100 text-pink-600', slug: 'mode' },
  { label: 'Décoration', icon: Home, color: 'bg-blue-100 text-blue-600', slug: 'decoration' },
  { label: 'Floral', icon: FlowerIcon, color: 'bg-green-100 text-green-600', slug: 'floral' },
  { label: 'Art', icon: Palette, color: 'bg-purple-100 text-purple-600', slug: 'art' },
  { label: 'Textile', icon: Scissors, color: 'bg-red-100 text-red-600', slug: 'textile' },
  { label: 'Papeterie', icon: BookOpen, color: 'bg-teal-100 text-teal-600', slug: 'papeterie' },
];

const whyUs = [
  {
    icon: Leaf,
    title: 'Artisanat authentique',
    desc: 'Chaque produit est fabriqué à la main par des artisans passionnés, garantissant une qualité unique et irréprochable.',
    color: 'bg-accent-100 text-accent',
  },
  {
    icon: Shield,
    title: 'Achat sécurisé',
    desc: 'Paiements 100% sécurisés, remboursement garanti sous 30 jours. Votre satisfaction est notre priorité.',
    color: 'bg-primary-100 text-primary',
  },
  {
    icon: Truck,
    title: 'Livraison soignée',
    desc: 'Vos créations sont emballées avec soin et livrées en toute sécurité, directement à votre porte.',
    color: 'bg-secondary-300 text-primary-700',
  },
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

function BoutiqueCard({ boutique }) {
  const PLACEHOLDER = 'https://images.unsplash.com/photo-1555529669-e69e7aa0ba9a?w=400&q=70';
  return (
    <Link
      to={`/boutiques/${boutique.id}`}
      className="card group flex flex-col hover:shadow-craft-lg transition-shadow duration-300"
    >
      <div className="relative overflow-hidden aspect-video">
        <img
          src={boutique.image || PLACEHOLDER}
          alt={boutique.nom}
          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
          onError={(e) => { e.currentTarget.src = PLACEHOLDER; }}
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
        <div className="absolute bottom-3 left-3">
          <h3 className="text-white font-serif font-bold text-lg drop-shadow">{boutique.nom}</h3>
          {boutique.nb_produits !== undefined && (
            <span className="text-white/80 text-xs">{boutique.nb_produits} produits</span>
          )}
        </div>
      </div>
      {boutique.description && (
        <p className="p-4 text-sm text-gray-600 line-clamp-2">{boutique.description}</p>
      )}
    </Link>
  );
}

export default function HomePage() {
  const navigate = useNavigate();

  const { data: productsData, isLoading: productsLoading } = useQuery({
    queryKey: ['products', { page: 1, per_page: 8, tri: 'populaire' }],
    queryFn: async () => {
      const { data } = await productsAPI.getAll({ page: 1, per_page: 8, tri: 'populaire' });
      return data;
    },
    staleTime: 1000 * 60 * 5,
  });

  const { data: boutiquesData } = useQuery({
    queryKey: ['boutiques', { page: 1, per_page: 4 }],
    queryFn: async () => {
      const { data } = await boutiquesAPI.getAll({ page: 1, per_page: 4 });
      return data;
    },
    staleTime: 1000 * 60 * 5,
  });

  // Fallback mock products for display when API is unavailable
  const mockProducts = Array.from({ length: 8 }, (_, i) => ({
    id: i + 1,
    nom: ['Vase en céramique fait main', 'Collier en argent ciselé', 'Panier en osier tressé', 'Carnet en cuir artisanal', 'Bougie parfumée à la cire d\'abeille', 'Savon naturel au miel', 'Écharpe en laine mérinos', 'Tasse en grès émaillé'][i],
    prix: [45, 89, 35, 28, 18, 12, 65, 32][i],
    note_moyenne: [4.8, 4.9, 4.5, 4.7, 4.6, 4.9, 4.4, 4.8][i],
    nb_avis: [24, 18, 32, 15, 41, 55, 12, 28][i],
    stock: 10,
    categorie: categories[i % categories.length].label,
    boutique: { id: (i % 3) + 1, nom: ['Céramiques de Lyon', 'Bijoux Céleste', 'Artisan du Midi'][i % 3] },
    image: [
      'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=400&q=70',
      'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=400&q=70',
      'https://images.unsplash.com/photo-1584464367415-25f25e98d8c8?w=400&q=70',
      'https://images.unsplash.com/photo-1544816155-12df9643f363?w=400&q=70',
      'https://images.unsplash.com/photo-1512572525676-f9b59951929e?w=400&q=70',
      'https://images.unsplash.com/photo-1607006344380-b6775a0824a7?w=400&q=70',
      'https://images.unsplash.com/photo-1521488741906-21a748d4d374?w=400&q=70',
      'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=400&q=70',
    ][i],
  }));

  const products = productsData?.data || productsData?.products || (productsLoading ? [] : mockProducts);

  const mockBoutiques = [
    { id: 1, nom: 'Céramiques de Lyon', description: 'Poteries et céramiques artisanales depuis 1987.', image: 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=600&q=70', nb_produits: 24 },
    { id: 2, nom: 'Bijoux Céleste', description: 'Bijoux fins en argent et or, façonnés à la main.', image: 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=600&q=70', nb_produits: 18 },
    { id: 3, nom: 'Artisan du Midi', description: 'Savons, bougies et cosmétiques naturels du sud.', image: 'https://images.unsplash.com/photo-1512572525676-f9b59951929e?w=600&q=70', nb_produits: 31 },
    { id: 4, nom: 'Maison Textile', description: 'Linge de maison et accessoires tissés à la main.', image: 'https://images.unsplash.com/photo-1521488741906-21a748d4d374?w=600&q=70', nb_produits: 15 },
  ];

  const boutiques = boutiquesData?.data || boutiquesData?.boutiques || mockBoutiques;

  return (
    <div>
      {/* ── Hero ──────────────────────────────────────────────────────────── */}
      <section
        className="relative min-h-[85vh] flex items-center justify-center"
        style={{ backgroundImage: `url(${HERO_BG})`, backgroundSize: 'cover', backgroundPosition: 'center' }}
      >
        <div className="absolute inset-0 bg-gradient-to-br from-black/65 via-primary/40 to-accent/30" />
        <div className="relative z-10 text-center px-4 max-w-3xl mx-auto">
          <span className="inline-block bg-white/15 backdrop-blur-sm text-white text-sm font-medium px-4 py-1.5 rounded-full mb-6 border border-white/20">
            🌿 La marketplace des artisans du monde
          </span>
          <h1 className="text-5xl md:text-7xl font-serif font-bold text-white leading-tight mb-6 drop-shadow-lg">
            L'artisanat,
            <br />
            <span className="text-amber-300">réinventé</span>
          </h1>
          <p className="text-lg md:text-xl text-white/85 mb-10 max-w-xl mx-auto leading-relaxed">
            Découvrez des créations uniques, fabriquées à la main par des artisans passionnés
            aux quatre coins du monde.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link
              to="/produits"
              className="flex items-center justify-center gap-2 bg-primary hover:bg-primary-600 text-white font-semibold px-8 py-3.5 rounded-xl transition-colors text-base shadow-lg"
            >
              Explorer les produits <ArrowRight size={18} />
            </Link>
            <Link
              to="/register"
              className="flex items-center justify-center gap-2 bg-white/15 hover:bg-white/25 backdrop-blur-sm text-white font-semibold px-8 py-3.5 rounded-xl transition-colors text-base border border-white/30"
            >
              Devenir artisan
            </Link>
          </div>

          {/* Stats */}
          <div className="mt-16 grid grid-cols-3 gap-6 max-w-sm mx-auto">
            {[
              { val: '1 200+', label: 'Artisans' },
              { val: '8 500+', label: 'Produits' },
              { val: '4.9★', label: 'Note moyenne' },
            ].map(({ val, label }) => (
              <div key={label} className="text-center">
                <div className="text-2xl font-bold text-white">{val}</div>
                <div className="text-white/60 text-xs mt-0.5">{label}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Categories ────────────────────────────────────────────────────── */}
      <section className="py-16 px-4 bg-white">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-10">
            <h2 className="section-title">Explorez par catégorie</h2>
            <p className="section-subtitle">Trouvez l'artisanat qui vous ressemble</p>
          </div>
          <div className="grid grid-cols-4 md:grid-cols-8 gap-4">
            {categories.map(({ label, icon: Icon, color, slug }) => (
              <button
                key={slug}
                onClick={() => navigate(`/produits?categorie=${slug}`)}
                className="flex flex-col items-center gap-2.5 group"
              >
                <div
                  className={`w-14 h-14 md:w-16 md:h-16 rounded-2xl ${color} flex items-center justify-center
                             group-hover:scale-110 transition-transform duration-200 shadow-sm`}
                >
                  <Icon size={24} />
                </div>
                <span className="text-xs font-medium text-gray-700 group-hover:text-primary transition-colors text-center">
                  {label}
                </span>
              </button>
            ))}
          </div>
        </div>
      </section>

      {/* ── Trending Products ─────────────────────────────────────────────── */}
      <section className="py-16 px-4 bg-secondary-50">
        <div className="max-w-7xl mx-auto">
          <div className="flex items-end justify-between mb-10">
            <div>
              <h2 className="section-title">Produits tendance</h2>
              <p className="section-subtitle mb-0">Les coups de cœur de notre communauté</p>
            </div>
            <Link
              to="/produits"
              className="hidden md:flex items-center gap-1 text-primary font-medium hover:underline text-sm"
            >
              Voir tout <ArrowRight size={15} />
            </Link>
          </div>

          {productsLoading ? (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
              {Array.from({ length: 8 }).map((_, i) => <ProductSkeleton key={i} />)}
            </div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
              {products.slice(0, 8).map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}

          <div className="text-center mt-10">
            <Link to="/produits" className="btn-outline inline-flex items-center gap-2">
              Voir tous les produits <ArrowRight size={16} />
            </Link>
          </div>
        </div>
      </section>

      {/* ── Featured Boutiques ────────────────────────────────────────────── */}
      <section className="py-16 px-4 bg-white">
        <div className="max-w-7xl mx-auto">
          <div className="flex items-end justify-between mb-10">
            <div>
              <h2 className="section-title">Boutiques à la une</h2>
              <p className="section-subtitle mb-0">Rencontrez nos artisans d'exception</p>
            </div>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {boutiques.slice(0, 4).map((boutique) => (
              <BoutiqueCard key={boutique.id} boutique={boutique} />
            ))}
          </div>
        </div>
      </section>

      {/* ── Why MarketCraft ───────────────────────────────────────────────── */}
      <section className="py-16 px-4 bg-secondary">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-12">
            <h2 className="section-title">Pourquoi choisir MarketCraft ?</h2>
            <p className="section-subtitle">Notre engagement envers vous et les artisans</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {whyUs.map(({ icon: Icon, title, desc, color }) => (
              <div key={title} className="text-center p-8 bg-white rounded-2xl shadow-craft hover:shadow-craft-lg transition-shadow">
                <div className={`w-14 h-14 ${color} rounded-2xl flex items-center justify-center mx-auto mb-5`}>
                  <Icon size={26} />
                </div>
                <h3 className="font-serif font-bold text-xl text-gray-800 mb-3">{title}</h3>
                <p className="text-gray-600 leading-relaxed text-sm">{desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── CTA Banner ────────────────────────────────────────────────────── */}
      <section className="py-16 px-4 bg-primary">
        <div className="max-w-3xl mx-auto text-center">
          <Star className="mx-auto text-amber-300 mb-4" size={36} fill="currentColor" />
          <h2 className="text-3xl md:text-4xl font-serif font-bold text-white mb-4">
            Vous êtes artisan ?
          </h2>
          <p className="text-primary-200 text-lg mb-8 leading-relaxed">
            Rejoignez notre communauté de créateurs et vendez vos créations au monde entier.
            Création de boutique gratuite, commission réduite.
          </p>
          <Link
            to="/register"
            className="inline-flex items-center gap-2 bg-white text-primary font-bold px-8 py-3.5 rounded-xl
                       hover:bg-secondary-100 transition-colors text-base shadow-lg"
          >
            Créer ma boutique <ArrowRight size={18} />
          </Link>
        </div>
      </section>
    </div>
  );
}
