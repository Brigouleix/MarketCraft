import React, { useContext } from 'react';
import { Link } from 'react-router-dom';
import { ShoppingCart, Store, Star } from 'lucide-react';
import { CartContext } from '../contexts/CartContext';
import { useAuth } from '../hooks/useAuth';
import StarRating from './StarRating';
import { parseImages } from '../utils/parseImages';

const PLACEHOLDER_IMG =
  'https://images.unsplash.com/photo-1493106641515-6b5631de4bb9?w=400&q=80';

/**
 * @param {boolean} compact  Variante resserrée : une seule étoile suivie de la
 *   note, et pas de bouton d'ajout au panier — toute la carte devient alors un
 *   raccourci vers la fiche produit. Utilisée là où la place manque, comme le
 *   modal de recherche IA qui affiche quatre colonnes.
 */
export default function ProductCard({ product, compact = false }) {
  const { addItem } = useContext(CartContext);
  // Un compte vendeur n'achète pas : le bouton d'ajout au panier disparaît.
  // Le refus qui fait foi reste côté serveur, sur POST /orders.
  const { isVendeur } = useAuth();

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
    categories,
  } = product;

  const imageUrl = image || parseImages(images)[0] || PLACEHOLDER_IMG;
  const inStock = stock > 0;

  // Toutes les catégories du produit (liaison N-N) ; repli sur la catégorie
  // principale (string) si la liste n'est pas fournie par l'API.
  const categoryNames = Array.isArray(categories) && categories.length > 0
    ? categories.map((c) => c.nom)
    : (categorie ? [categorie] : []);

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
        {categoryNames.length > 0 && (
          <div className="absolute top-2 left-2 right-2 flex flex-wrap gap-1">
            {categoryNames.slice(0, 2).map((name) => (
              <span
                key={name}
                className="bg-accent text-white text-xs font-medium px-2 py-0.5 rounded-full"
              >
                {name}
              </span>
            ))}
            {categoryNames.length > 2 && (
              <span
                className="bg-accent text-white text-xs font-medium px-2 py-0.5 rounded-full"
                title={categoryNames.slice(2).join(', ')}
              >
                +{categoryNames.length - 2}
              </span>
            )}
          </div>
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

        {/* Note : une seule étoile en compact, la rangée complète sinon */}
        <div className="mb-3">
          {compact ? (
            <span className="flex items-center gap-1 text-sm text-gray-600">
              <Star size={14} className="text-amber-400 fill-amber-400" />
              <span className="font-medium text-gray-800">
                {Number(note_moyenne).toFixed(1)}
              </span>
              {nb_avis > 0 && (
                <span className="text-xs text-gray-400">({nb_avis})</span>
              )}
            </span>
          ) : (
            <StarRating value={note_moyenne} size={14} showValue count={nb_avis} />
          )}
        </div>

        {/* Prix, et bouton d'ajout hors mode compact */}
        <div className="mt-auto flex items-center justify-between gap-2">
          <span className={`font-bold text-primary ${compact ? 'text-lg' : 'text-xl'}`}>
            {Number(prix).toFixed(2)} €
          </span>
          {!compact && !isVendeur && (
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
          )}
        </div>
      </div>
    </div>
  );
}
