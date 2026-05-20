import React, { useContext } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Trash2, Plus, Minus, ShoppingBag, ArrowLeft, ArrowRight, Package } from 'lucide-react';
import { CartContext } from '../contexts/CartContext';

const PLACEHOLDER = 'https://images.unsplash.com/photo-1493106641515-6b5631de4bb9?w=200&q=70';
const SHIPPING_THRESHOLD = 60;
const SHIPPING_COST = 4.9;

export default function CartPage() {
  const { items, removeItem, updateQuantity, clearCart, total, count } = useContext(CartContext);
  const navigate = useNavigate();

  const shippingFree = total >= SHIPPING_THRESHOLD;
  const shipping = shippingFree ? 0 : SHIPPING_COST;
  const grandTotal = total + shipping;
  const remainingForFreeShipping = Math.max(0, SHIPPING_THRESHOLD - total);

  if (items.length === 0) {
    return (
      <div className="max-w-2xl mx-auto px-4 py-24 text-center flex flex-col items-center gap-6">
        <div className="w-24 h-24 bg-secondary-200 rounded-full flex items-center justify-center">
          <ShoppingBag size={40} className="text-secondary-500" />
        </div>
        <div>
          <h1 className="text-2xl font-serif font-bold text-gray-800 mb-2">Votre panier est vide</h1>
          <p className="text-gray-500">Découvrez nos créations artisanales et ajoutez vos coups de cœur.</p>
        </div>
        <Link to="/produits" className="btn-primary flex items-center gap-2">
          <ShoppingBag size={16} /> Explorer les produits
        </Link>
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <h1 className="text-3xl font-serif font-bold text-primary">
          Mon panier ({count} article{count > 1 ? 's' : ''})
        </h1>
        <button
          onClick={clearCart}
          className="text-sm text-red-500 hover:text-red-700 flex items-center gap-1.5 transition-colors"
        >
          <Trash2 size={14} /> Vider le panier
        </button>
      </div>

      {/* Free shipping progress */}
      {!shippingFree && (
        <div className="mb-6 p-4 bg-accent-50 border border-accent-200 rounded-xl">
          <div className="flex items-center justify-between text-sm mb-2">
            <span className="text-accent-700 font-medium">
              Plus que <strong>{remainingForFreeShipping.toFixed(2)} €</strong> pour la livraison gratuite !
            </span>
            <Package size={16} className="text-accent" />
          </div>
          <div className="w-full h-2 bg-accent-200 rounded-full overflow-hidden">
            <div
              className="h-full bg-accent rounded-full transition-all duration-500"
              style={{ width: `${Math.min(100, (total / SHIPPING_THRESHOLD) * 100)}%` }}
            />
          </div>
        </div>
      )}

      {shippingFree && (
        <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl flex items-center gap-2 text-green-700 text-sm font-medium">
          <Package size={16} /> Livraison offerte ! Votre commande dépasse {SHIPPING_THRESHOLD} €.
        </div>
      )}

      <div className="flex flex-col lg:flex-row gap-8">
        {/* Items list */}
        <div className="flex-1 space-y-4">
          {items.map((item) => (
            <div key={item.id} className="card flex gap-4 p-4 hover:shadow-craft-lg transition-shadow">
              <Link to={`/produits/${item.id}`} className="flex-shrink-0">
                <img
                  src={item.image || item.images?.[0] || PLACEHOLDER}
                  alt={item.nom}
                  className="w-24 h-24 object-cover rounded-xl border border-secondary-200"
                  onError={(e) => { e.currentTarget.src = PLACEHOLDER; }}
                />
              </Link>

              <div className="flex flex-col flex-1 min-w-0">
                <div className="flex justify-between gap-2">
                  <div className="flex-1 min-w-0">
                    <Link to={`/produits/${item.id}`} className="font-semibold text-gray-800 hover:text-primary transition-colors line-clamp-2">
                      {item.nom}
                    </Link>
                    {item.boutique && (
                      <Link
                        to={`/boutiques/${item.boutique.id}`}
                        className="text-xs text-accent hover:text-accent-600 transition-colors"
                      >
                        {item.boutique.nom}
                      </Link>
                    )}
                  </div>
                  <button
                    onClick={() => removeItem(item.id)}
                    className="text-gray-400 hover:text-red-500 transition-colors p-1 flex-shrink-0"
                    title="Supprimer"
                  >
                    <Trash2 size={16} />
                  </button>
                </div>

                <div className="mt-auto flex items-center justify-between flex-wrap gap-3">
                  {/* Quantity */}
                  <div className="flex items-center border border-secondary-300 rounded-lg overflow-hidden">
                    <button
                      onClick={() => updateQuantity(item.id, item.quantity - 1)}
                      className="w-8 h-8 flex items-center justify-center hover:bg-secondary-200 transition-colors"
                    >
                      <Minus size={13} />
                    </button>
                    <span className="w-10 text-center text-sm font-semibold">{item.quantity}</span>
                    <button
                      onClick={() => updateQuantity(item.id, item.quantity + 1)}
                      disabled={item.stock !== undefined && item.quantity >= item.stock}
                      className="w-8 h-8 flex items-center justify-center hover:bg-secondary-200 transition-colors disabled:opacity-40"
                    >
                      <Plus size={13} />
                    </button>
                  </div>

                  {/* Price */}
                  <div className="text-right">
                    <div className="font-bold text-primary text-lg">
                      {(item.prix * item.quantity).toFixed(2)} €
                    </div>
                    {item.quantity > 1 && (
                      <div className="text-xs text-gray-400">
                        {Number(item.prix).toFixed(2)} € / unité
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Order summary */}
        <div className="lg:w-80 flex-shrink-0">
          <div className="card p-6 sticky top-20 space-y-4">
            <h2 className="font-serif font-bold text-lg text-gray-800">Résumé de la commande</h2>

            <div className="space-y-2.5 text-sm">
              <div className="flex justify-between text-gray-600">
                <span>Sous-total ({count} article{count > 1 ? 's' : ''})</span>
                <span className="font-medium text-gray-800">{total.toFixed(2)} €</span>
              </div>
              <div className="flex justify-between text-gray-600">
                <span>Livraison</span>
                <span className={`font-medium ${shippingFree ? 'text-green-600' : 'text-gray-800'}`}>
                  {shippingFree ? 'Gratuite' : `${SHIPPING_COST.toFixed(2)} €`}
                </span>
              </div>
              {!shippingFree && (
                <p className="text-xs text-gray-400">
                  Livraison offerte à partir de {SHIPPING_THRESHOLD} €
                </p>
              )}
            </div>

            <div className="border-t border-secondary-200 pt-4 flex justify-between font-bold text-base">
              <span>Total TTC</span>
              <span className="text-primary text-xl">{grandTotal.toFixed(2)} €</span>
            </div>

            <button
              onClick={() => navigate('/checkout')}
              className="btn-primary w-full flex items-center justify-center gap-2 py-3"
            >
              Commander <ArrowRight size={16} />
            </button>

            <Link
              to="/produits"
              className="flex items-center justify-center gap-2 text-sm text-primary hover:underline"
            >
              <ArrowLeft size={14} /> Continuer mes achats
            </Link>

            {/* Security badges */}
            <div className="border-t border-secondary-200 pt-4 flex justify-center gap-4 opacity-60">
              {['CB', 'Visa', 'MC', 'PayPal'].map((brand) => (
                <span key={brand} className="text-xs font-bold bg-secondary-200 px-2 py-0.5 rounded text-gray-600">
                  {brand}
                </span>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
