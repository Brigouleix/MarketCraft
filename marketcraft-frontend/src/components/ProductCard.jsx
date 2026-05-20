import React, { useContext } from 'react';
import { Link } from 'react-router-dom';
import { ShoppingCart, Store } from 'lucide-react';
import { CartContext } from '../contexts/CartContext';
import StarRating from './StarRating';

const PLACEHOLDER_IMG =
  'https://images.unsplash.com/photo-1493106641515-6b5631de4bb9?w=400&q=80';

export default function ProductCard({ product }) {
  const { addItem } = useContext(CartContext);

  const {
    id,
    nom = 'Produit artisanal',
    prix = 0,
    image,
    images,
    boutique,
    note_moyenne = 0,
    nb_avis = 0,
    stock = 0,
    categorie,
  } = product;

  const imageUrl = image || (images && images[0]) || PLACEHOLDER_IMG;
  const inStock = stock > 0;

  const handleAddToCart = (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (inStock) addItem(product, 1);
  };

  return (
    <div className="card group flex flex-col hover:shadow-craft-lg transition-shadow duration-300">
      {/* Image */}
      <Link to={`/produits/${id}`} className="relative overflow-hidden aspect-square block">
        <img
          src={imageUrl}
          alt={nom}
          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
          onError={(e) => { e.currentTarget.src = PLACEHOLDER_IMG; }}
        />
        {!inStock && (
          <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
            <span className="text-white font-semibold text-sm bg-black/60 px-3 py-1 rounded-full">
              Rupture de stock
            </span>
          </div>
        )}
        {categorie && (
          <span className="absolute top-2 left-2 bg-accent text-white text-xs font-medium px-2 py-0.5 rounded-full">
            {categorie}
          </span>
        )}
      </Link>

      {/* Content */}
      <div className="flex flex-col flex-1 p-4">
        {/* Boutique */}
        {boutique && (
          <Link
            to={`/boutiques/${boutique.id}`}
            className="flex items-center gap-1 text-xs text-accent hover:text-accent-600 font-medium mb-1 transition-colors"
            onClick={(e) => e.stopPropagation()}
          >
            <Store size={12} />
            {boutique.nom}
          </Link>
        )}

        {/* Title */}
        <Link to={`/produits/${id}`}>
          <h3 className="font-serif font-semibold text-gray-800 group-hover:text-primary transition-colors line-clamp-2 mb-2">
            {nom}
          </h3>
        </Link>

        {/* Stars */}
        <div className="mb-3">
          <StarRating value={note_moyenne} size={14} showValue count={nb_avis} />
        </div>

        {/* Price + CTA */}
        <div className="mt-auto flex items-center justify-between">
          <span className="text-xl font-bold text-primary">
            {Number(prix).toFixed(2)} €
          </span>
          <button
            onClick={handleAddToCart}
            disabled={!inStock}
            className="flex items-center gap-1.5 bg-primary text-white text-sm font-medium px-3 py-2 rounded-lg
                       hover:bg-primary-600 active:bg-primary-700 disabled:opacity-40 disabled:cursor-not-allowed
                       transition-colors duration-200"
            title={inStock ? 'Ajouter au panier' : 'Rupture de stock'}
          >
            <ShoppingCart size={15} />
            <span className="hidden sm:inline">Ajouter</span>
          </button>
        </div>
      </div>
    </div>
  );
}
