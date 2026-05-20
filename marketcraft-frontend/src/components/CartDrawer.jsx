import React, { useContext, Fragment } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Dialog, Transition } from '@headlessui/react';
import { X, ShoppingBag, Trash2, Plus, Minus } from 'lucide-react';
import { CartContext } from '../contexts/CartContext';

const PLACEHOLDER_IMG =
  'https://images.unsplash.com/photo-1493106641515-6b5631de4bb9?w=100&q=70';

export default function CartDrawer() {
  const { items, isOpen, setIsOpen, removeItem, updateQuantity, total, count } =
    useContext(CartContext);
  const navigate = useNavigate();

  const handleCheckout = () => {
    setIsOpen(false);
    navigate('/checkout');
  };

  return (
    <Transition.Root show={isOpen} as={Fragment}>
      <Dialog as="div" className="relative z-50" onClose={setIsOpen}>
        {/* Overlay */}
        <Transition.Child
          as={Fragment}
          enter="ease-in-out duration-300"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in-out duration-300"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div className="fixed inset-0 bg-black/40 backdrop-blur-sm" />
        </Transition.Child>

        {/* Drawer */}
        <div className="fixed inset-0 overflow-hidden">
          <div className="absolute inset-0 overflow-hidden">
            <div className="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
              <Transition.Child
                as={Fragment}
                enter="transform transition ease-in-out duration-300"
                enterFrom="translate-x-full"
                enterTo="translate-x-0"
                leave="transform transition ease-in-out duration-300"
                leaveFrom="translate-x-0"
                leaveTo="translate-x-full"
              >
                <Dialog.Panel className="pointer-events-auto w-screen max-w-md">
                  <div className="flex h-full flex-col bg-secondary shadow-xl">
                    {/* Header */}
                    <div className="flex items-center justify-between bg-white px-6 py-4 border-b border-secondary-300">
                      <Dialog.Title className="flex items-center gap-2 text-lg font-serif font-semibold text-primary">
                        <ShoppingBag size={20} />
                        Mon panier
                        {count > 0 && (
                          <span className="bg-primary text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                            {count}
                          </span>
                        )}
                      </Dialog.Title>
                      <button
                        onClick={() => setIsOpen(false)}
                        className="text-gray-500 hover:text-primary transition-colors p-1 rounded-lg hover:bg-secondary-200"
                      >
                        <X size={20} />
                      </button>
                    </div>

                    {/* Items */}
                    <div className="flex-1 overflow-y-auto px-6 py-4">
                      {items.length === 0 ? (
                        <div className="flex flex-col items-center justify-center h-full text-center gap-4 py-16">
                          <ShoppingBag size={56} className="text-secondary-400" />
                          <p className="text-gray-500 font-medium">Votre panier est vide</p>
                          <Link
                            to="/produits"
                            onClick={() => setIsOpen(false)}
                            className="btn-primary text-sm"
                          >
                            Découvrir les produits
                          </Link>
                        </div>
                      ) : (
                        <ul className="divide-y divide-secondary-300 space-y-1">
                          {items.map((item) => (
                            <li key={item.id} className="py-4 flex gap-4">
                              <img
                                src={item.image || item.images?.[0] || PLACEHOLDER_IMG}
                                alt={item.nom}
                                className="w-20 h-20 object-cover rounded-lg flex-shrink-0 border border-secondary-300"
                                onError={(e) => { e.currentTarget.src = PLACEHOLDER_IMG; }}
                              />
                              <div className="flex flex-col flex-1 min-w-0">
                                <Link
                                  to={`/produits/${item.id}`}
                                  onClick={() => setIsOpen(false)}
                                  className="font-medium text-gray-800 hover:text-primary text-sm line-clamp-2 transition-colors"
                                >
                                  {item.nom}
                                </Link>
                                <span className="text-primary font-bold text-sm mt-0.5">
                                  {Number(item.prix).toFixed(2)} €
                                </span>

                                {/* Quantity Controls */}
                                <div className="flex items-center gap-2 mt-2">
                                  <button
                                    onClick={() => updateQuantity(item.id, item.quantity - 1)}
                                    className="w-7 h-7 flex items-center justify-center rounded-full border border-secondary-400 hover:bg-secondary-300 transition-colors"
                                  >
                                    <Minus size={12} />
                                  </button>
                                  <span className="w-6 text-center text-sm font-semibold">
                                    {item.quantity}
                                  </span>
                                  <button
                                    onClick={() => updateQuantity(item.id, item.quantity + 1)}
                                    className="w-7 h-7 flex items-center justify-center rounded-full border border-secondary-400 hover:bg-secondary-300 transition-colors"
                                  >
                                    <Plus size={12} />
                                  </button>
                                  <button
                                    onClick={() => removeItem(item.id)}
                                    className="ml-auto text-red-400 hover:text-red-600 transition-colors p-1"
                                    title="Supprimer"
                                  >
                                    <Trash2 size={15} />
                                  </button>
                                </div>
                              </div>
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>

                    {/* Footer */}
                    {items.length > 0 && (
                      <div className="border-t border-secondary-300 bg-white px-6 py-4 space-y-4">
                        <div className="flex justify-between text-sm text-gray-600">
                          <span>Sous-total ({count} article{count > 1 ? 's' : ''})</span>
                          <span className="font-semibold text-gray-800">
                            {total.toFixed(2)} €
                          </span>
                        </div>
                        <div className="flex justify-between font-bold text-base">
                          <span>Total estimé</span>
                          <span className="text-primary">{total.toFixed(2)} €</span>
                        </div>
                        <button
                          onClick={handleCheckout}
                          className="btn-primary w-full justify-center flex items-center gap-2"
                        >
                          Commander
                        </button>
                        <Link
                          to="/panier"
                          onClick={() => setIsOpen(false)}
                          className="block text-center text-sm text-primary hover:underline"
                        >
                          Voir le panier complet
                        </Link>
                      </div>
                    )}
                  </div>
                </Dialog.Panel>
              </Transition.Child>
            </div>
          </div>
        </div>
      </Dialog>
    </Transition.Root>
  );
}
