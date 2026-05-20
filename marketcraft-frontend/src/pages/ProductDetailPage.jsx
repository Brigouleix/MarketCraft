import React, { useState, useContext, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  ShoppingCart, Store, ChevronLeft, ChevronRight, Plus, Minus,
  Package, Truck, RotateCcw, Star, Sparkles,
} from 'lucide-react';
import axios from 'axios';
import { productsAPI, avisAPI } from '../services/api';
import { CartContext } from '../contexts/CartContext';
import { useAuth } from '../hooks/useAuth';
import StarRating from '../components/StarRating';
import ProductCard from '../components/ProductCard';
import toast from 'react-hot-toast';

const PLACEHOLDER = 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=800&q=80';

const MOCK_PRODUCT = {
  id: 1,
  nom: 'Vase en céramique fait main',
  prix: 45,
  description: `Ce vase en céramique est une œuvre unique, façonnée à la main dans notre atelier lyonnais. Chaque pièce est différente, avec ses propres nuances et textures qui témoignent du savoir-faire artisanal.

Dimensions : H 28 cm × ⌀ 12 cm
Poids : 650 g
Matière : Grès chamotté, émaux naturels
Origine : France – Lyon

Parfait pour sublimer votre intérieur avec une touche d'authenticité.`,
  stock: 5,
  note_moyenne: 4.8,
  nb_avis: 24,
  categorie: 'céramique',
  images: [
    'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=800&q=80',
    'https://images.unsplash.com/photo-1544816155-12df9643f363?w=800&q=80',
    'https://images.unsplash.com/photo-1493106641515-6b5631de4bb9?w=800&q=80',
  ],
  boutique: { id: 1, nom: 'Céramiques de Lyon', description: 'Poteries artisanales depuis 1987.' },
};

const MOCK_AVIS = [
  { id: 1, note: 5, commentaire: 'Magnifique vase, exactement comme sur les photos. La qualité est au rendez-vous !', utilisateur: { nom: 'Sophie M.' }, created_at: '2026-03-15' },
  { id: 2, note: 4, commentaire: 'Très beau produit, livraison soignée. Je recommande.', utilisateur: { nom: 'Jean-Pierre D.' }, created_at: '2026-02-28' },
  { id: 3, note: 5, commentaire: 'Un vrai bijou artisanal, je suis ravie de mon achat.', utilisateur: { nom: 'Martine L.' }, created_at: '2026-01-10' },
];

function AvisForm({ productId, onSuccess }) {
  const [note, setNote] = useState(0);
  const [commentaire, setCommentaire] = useState('');
  const queryClient = useQueryClient();

  const { mutate, isPending } = useMutation({
    mutationFn: () => avisAPI.create(productId, { note, commentaire }),
    onSuccess: () => {
      toast.success('Avis publié avec succès !');
      setNote(0);
      setCommentaire('');
      queryClient.invalidateQueries(['avis', productId]);
      onSuccess?.();
    },
    onError: (err) => {
      toast.error(err.response?.data?.message || 'Erreur lors de la publication.');
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    if (note === 0) { toast.error('Veuillez attribuer une note.'); return; }
    if (commentaire.trim().length < 10) { toast.error('Commentaire trop court (min. 10 caractères).'); return; }
    mutate();
  };

  return (
    <form onSubmit={handleSubmit} className="card p-5 space-y-4">
      <h3 className="font-serif font-semibold text-gray-800">Laisser un avis</h3>
      <div>
        <label className="text-sm font-medium text-gray-700 mb-2 block">Votre note</label>
        <StarRating value={note} onChange={setNote} size={26} />
      </div>
      <div>
        <label className="text-sm font-medium text-gray-700 mb-2 block">Commentaire</label>
        <textarea
          value={commentaire}
          onChange={(e) => setCommentaire(e.target.value)}
          rows={4}
          placeholder="Partagez votre expérience avec ce produit…"
          className="input-field resize-none"
          minLength={10}
          maxLength={1000}
          required
        />
        <div className="text-xs text-gray-400 text-right mt-1">{commentaire.length}/1000</div>
      </div>
      <button type="submit" disabled={isPending || note === 0} className="btn-primary">
        {isPending ? 'Publication…' : 'Publier mon avis'}
      </button>
    </form>
  );
}

export default function ProductDetailPage() {
  const { id } = useParams();
  const { addItem } = useContext(CartContext);
  const { isAuthenticated } = useAuth();
  const [selectedImg, setSelectedImg] = useState(0);
  const [quantity, setQuantity] = useState(1);
  const [tab, setTab] = useState('description');
  const [recommendations, setRecommendations] = useState([]);
  const [recoLoading, setRecoLoading] = useState(false);

  const { data: productData, isLoading } = useQuery({
    queryKey: ['product', id],
    queryFn: async () => {
      const { data } = await productsAPI.getById(id);
      return data;
    },
    staleTime: 1000 * 60 * 5,
  });

  const product = productData?.product || productData || MOCK_PRODUCT;

  const { data: avisData } = useQuery({
    queryKey: ['avis', id],
    queryFn: async () => {
      const { data } = await avisAPI.getByProduct(id);
      return data;
    },
    staleTime: 1000 * 60 * 2,
  });

  const avis = avisData?.data || avisData?.avis || MOCK_AVIS;

  const { data: similarData } = useQuery({
    queryKey: ['similar', id],
    queryFn: async () => {
      const { data } = await productsAPI.getSimilar(id);
      return data;
    },
    staleTime: 1000 * 60 * 5,
  });

  const similarProducts = similarData?.products || similarData?.data || [];

  useEffect(() => {
    if (!product) return;
    const fetchRecommendations = async () => {
      setRecoLoading(true);
      try {
        const res = await axios.get(
          `${process.env.REACT_APP_API_URL || 'http://localhost:8000/api'}/search/recommendations/${product.id}`
        );
        if (res.data.success) {
          setRecommendations(res.data.recommendations || []);
        }
      } catch {
        // Silencieux — les recos sont optionnelles
      } finally {
        setRecoLoading(false);
      }
    };
    fetchRecommendations();
  }, [product?.id]);

  if (isLoading) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-10 animate-pulse">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-10">
          <div className="aspect-square bg-secondary-300 rounded-2xl" />
          <div className="space-y-4">
            <div className="h-8 bg-secondary-300 rounded w-3/4" />
            <div className="h-6 bg-secondary-300 rounded w-1/4" />
            <div className="h-4 bg-secondary-300 rounded w-1/2" />
            <div className="h-24 bg-secondary-300 rounded" />
          </div>
        </div>
      </div>
    );
  }

  const images = product.images?.length ? product.images : [product.image || PLACEHOLDER];
  const inStock = (product.stock || 0) > 0;

  const handleAddToCart = () => {
    addItem(product, quantity);
  };

  const tabs = [
    { key: 'description', label: 'Description' },
    { key: 'avis', label: `Avis (${avis.length})` },
    { key: 'livraison', label: 'Livraison' },
  ];

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-2 text-sm text-gray-500 mb-8">
        <Link to="/" className="hover:text-primary transition-colors">Accueil</Link>
        <span>/</span>
        <Link to="/produits" className="hover:text-primary transition-colors">Produits</Link>
        <span>/</span>
        <span className="text-gray-800 line-clamp-1">{product.nom}</span>
      </nav>

      {/* Main grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 mb-16">
        {/* Gallery */}
        <div className="space-y-4">
          <div className="relative aspect-square rounded-2xl overflow-hidden bg-secondary-100 border border-secondary-200">
            <img
              src={images[selectedImg] || PLACEHOLDER}
              alt={product.nom}
              className="w-full h-full object-cover"
              onError={(e) => { e.currentTarget.src = PLACEHOLDER; }}
            />
            {images.length > 1 && (
              <>
                <button
                  onClick={() => setSelectedImg((prev) => (prev - 1 + images.length) % images.length)}
                  className="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 bg-white/80 rounded-full flex items-center justify-center hover:bg-white transition-colors shadow"
                >
                  <ChevronLeft size={18} />
                </button>
                <button
                  onClick={() => setSelectedImg((prev) => (prev + 1) % images.length)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 bg-white/80 rounded-full flex items-center justify-center hover:bg-white transition-colors shadow"
                >
                  <ChevronRight size={18} />
                </button>
              </>
            )}
          </div>
          {images.length > 1 && (
            <div className="flex gap-3 overflow-x-auto pb-1">
              {images.map((img, i) => (
                <button
                  key={i}
                  onClick={() => setSelectedImg(i)}
                  className={`w-20 h-20 rounded-lg overflow-hidden flex-shrink-0 border-2 transition-colors ${
                    i === selectedImg ? 'border-primary' : 'border-secondary-200 hover:border-secondary-400'
                  }`}
                >
                  <img src={img} alt="" className="w-full h-full object-cover"
                    onError={(e) => { e.currentTarget.src = PLACEHOLDER; }} />
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Product info */}
        <div className="space-y-5">
          {product.categorie && (
            <span className="inline-block text-xs font-medium text-accent bg-accent-100 px-3 py-1 rounded-full capitalize">
              {product.categorie}
            </span>
          )}

          <h1 className="text-3xl font-serif font-bold text-gray-800 leading-tight">{product.nom}</h1>

          <div className="flex items-center gap-3">
            <StarRating value={product.note_moyenne} size={20} showValue count={product.nb_avis} />
          </div>

          <div className="text-4xl font-bold text-primary">
            {Number(product.prix).toFixed(2)} €
          </div>

          {product.boutique && (
            <Link
              to={`/boutiques/${product.boutique.id}`}
              className="flex items-center gap-2 text-sm text-accent hover:text-accent-600 font-medium transition-colors"
            >
              <Store size={15} />
              {product.boutique.nom}
            </Link>
          )}

          {/* Stock indicator */}
          <div className="flex items-center gap-2">
            <div className={`w-2 h-2 rounded-full ${inStock ? 'bg-green-500' : 'bg-red-400'}`} />
            <span className={`text-sm font-medium ${inStock ? 'text-green-700' : 'text-red-600'}`}>
              {inStock ? `En stock (${product.stock} disponible${product.stock > 1 ? 's' : ''})` : 'Rupture de stock'}
            </span>
          </div>

          {/* Quantity + Add to cart */}
          {inStock && (
            <div className="flex items-center gap-4">
              <div className="flex items-center border border-secondary-300 rounded-lg overflow-hidden">
                <button
                  onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                  className="w-10 h-10 flex items-center justify-center hover:bg-secondary-200 transition-colors"
                >
                  <Minus size={16} />
                </button>
                <span className="w-12 text-center font-semibold text-gray-800">{quantity}</span>
                <button
                  onClick={() => setQuantity((q) => Math.min(product.stock || 99, q + 1))}
                  className="w-10 h-10 flex items-center justify-center hover:bg-secondary-200 transition-colors"
                >
                  <Plus size={16} />
                </button>
              </div>
              <button
                onClick={handleAddToCart}
                className="flex-1 btn-primary flex items-center justify-center gap-2 py-3"
              >
                <ShoppingCart size={18} />
                Ajouter au panier
              </button>
            </div>
          )}

          {/* Quick benefits */}
          <div className="grid grid-cols-3 gap-3 pt-2">
            {[
              { icon: Package, label: 'Emballage soigné' },
              { icon: Truck, label: 'Livraison offerte dès 60€' },
              { icon: RotateCcw, label: 'Retour 30 jours' },
            ].map(({ icon: Icon, label }) => (
              <div key={label} className="text-center p-3 bg-secondary rounded-xl">
                <Icon size={18} className="mx-auto text-accent mb-1" />
                <span className="text-xs text-gray-600">{label}</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-secondary-300 mb-8">
        <div className="flex gap-1">
          {tabs.map(({ key, label }) => (
            <button
              key={key}
              onClick={() => setTab(key)}
              className={`px-5 py-3 text-sm font-medium border-b-2 transition-colors -mb-px ${
                tab === key
                  ? 'border-primary text-primary'
                  : 'border-transparent text-gray-500 hover:text-gray-800'
              }`}
            >
              {label}
            </button>
          ))}
        </div>
      </div>

      {/* Tab content */}
      {tab === 'description' && (
        <div className="prose prose-stone max-w-none">
          <p className="text-gray-700 leading-relaxed whitespace-pre-line text-base">
            {product.description || 'Aucune description disponible pour ce produit.'}
          </p>
        </div>
      )}

      {tab === 'avis' && (
        <div className="space-y-8">
          {/* Average */}
          <div className="flex items-center gap-6 p-6 bg-secondary rounded-2xl">
            <div className="text-center">
              <div className="text-5xl font-bold text-primary">{Number(product.note_moyenne).toFixed(1)}</div>
              <StarRating value={product.note_moyenne} size={20} className="mt-1 justify-center" />
              <p className="text-sm text-gray-500 mt-1">{product.nb_avis || avis.length} avis</p>
            </div>
          </div>

          {/* Review form */}
          {isAuthenticated ? (
            <AvisForm productId={id} />
          ) : (
            <div className="card p-5 text-center">
              <Star className="mx-auto text-amber-400 mb-2" size={28} fill="currentColor" />
              <p className="text-gray-600 mb-3">Connectez-vous pour laisser un avis</p>
              <Link to="/login" className="btn-primary text-sm">Se connecter</Link>
            </div>
          )}

          {/* Reviews list */}
          <div className="space-y-4">
            {avis.length === 0 ? (
              <p className="text-center text-gray-500 py-8">Soyez le premier à laisser un avis !</p>
            ) : (
              avis.map((a) => (
                <div key={a.id} className="card p-5">
                  <div className="flex items-start justify-between mb-2">
                    <div>
                      <p className="font-semibold text-gray-800 text-sm">{a.utilisateur?.nom || 'Anonyme'}</p>
                      <StarRating value={a.note} size={14} className="mt-0.5" />
                    </div>
                    <span className="text-xs text-gray-400">
                      {new Date(a.created_at).toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' })}
                    </span>
                  </div>
                  <p className="text-gray-700 text-sm leading-relaxed">{a.commentaire}</p>
                </div>
              ))
            )}
          </div>
        </div>
      )}

      {tab === 'livraison' && (
        <div className="space-y-4 max-w-lg">
          {[
            { icon: Truck, title: 'Livraison standard (3–5 jours)', desc: 'Gratuite dès 60€ d\'achat, sinon 4,90€' },
            { icon: Package, title: 'Livraison express (24h)', desc: 'Disponible en France métropolitaine – 9,90€' },
            { icon: RotateCcw, title: 'Retours & échanges', desc: 'Retour gratuit sous 30 jours si le produit est dans son état d\'origine.' },
          ].map(({ icon: Icon, title, desc }) => (
            <div key={title} className="flex gap-4 p-5 card">
              <div className="w-10 h-10 bg-accent-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <Icon size={18} className="text-accent" />
              </div>
              <div>
                <h4 className="font-semibold text-gray-800 text-sm mb-0.5">{title}</h4>
                <p className="text-gray-600 text-sm">{desc}</p>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Similar products */}
      {similarProducts.length > 0 && (
        <div className="mt-16">
          <h2 className="section-title mb-2">Produits similaires</h2>
          <p className="section-subtitle">Vous pourriez aussi aimer</p>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            {similarProducts.slice(0, 4).map((p) => (
              <ProductCard key={p.id} product={p} />
            ))}
          </div>
        </div>
      )}

      {/* ── Section Recommandations IA ──────────────────────────────── */}
      {(recoLoading || recommendations.length > 0) && (
        <section className="mt-16">
          <div className="flex items-center gap-2 mb-6">
            <Sparkles size={20} className="text-purple-500" />
            <h2 className="text-2xl font-serif font-bold text-gray-800">Vous aimerez aussi</h2>
            <span className="text-xs text-purple-500 bg-purple-50 border border-purple-200 px-2 py-0.5 rounded-full font-medium">
              Sélection IA
            </span>
          </div>

          {recoLoading ? (
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              {[...Array(4)].map((_, i) => (
                <div key={i} className="bg-gray-100 rounded-xl h-48 animate-pulse" />
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              {recommendations.map((reco) => (
                <Link
                  key={reco.id}
                  to={`/produits/${reco.id}`}
                  className="group bg-white rounded-xl border border-secondary-200 overflow-hidden hover:shadow-craft-lg transition-all hover:-translate-y-1"
                >
                  <div className="aspect-square bg-secondary-50 overflow-hidden">
                    {reco.images?.[0] ? (
                      <img
                        src={reco.images[0]}
                        alt={reco.nom}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                      />
                    ) : (
                      <div className="w-full h-full flex items-center justify-center text-4xl">🎨</div>
                    )}
                  </div>
                  <div className="p-3">
                    <p className="text-sm font-medium text-gray-800 line-clamp-2 group-hover:text-primary transition-colors">
                      {reco.nom}
                    </p>
                    <p className="text-sm font-bold text-primary mt-1">
                      {parseFloat(reco.prix).toFixed(2)} €
                    </p>
                  </div>
                </Link>
              ))}
            </div>
          )}
        </section>
      )}
    </div>
  );
}
