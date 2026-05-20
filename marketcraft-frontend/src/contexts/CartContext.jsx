import React, { createContext, useState, useCallback, useEffect, useMemo } from 'react';
import toast from 'react-hot-toast';

export const CartContext = createContext(null);

const CART_KEY = 'mc_cart';

const loadCart = () => {
  try {
    const stored = localStorage.getItem(CART_KEY);
    return stored ? JSON.parse(stored) : [];
  } catch {
    return [];
  }
};

const saveCart = (items) => {
  try {
    localStorage.setItem(CART_KEY, JSON.stringify(items));
  } catch {
    // quota exceeded – ignore
  }
};

export function CartProvider({ children }) {
  const [items, setItems] = useState(loadCart);
  const [isOpen, setIsOpen] = useState(false);

  useEffect(() => {
    saveCart(items);
  }, [items]);

  const addItem = useCallback((product, quantity = 1) => {
    setItems((prev) => {
      const existing = prev.find((i) => i.id === product.id);
      if (existing) {
        const newQty = existing.quantity + quantity;
        if (product.stock !== undefined && newQty > product.stock) {
          toast.error('Stock insuffisant.');
          return prev;
        }
        toast.success('Quantité mise à jour dans le panier.');
        return prev.map((i) =>
          i.id === product.id ? { ...i, quantity: newQty } : i
        );
      }
      toast.success('Produit ajouté au panier !');
      return [...prev, { ...product, quantity }];
    });
    setIsOpen(true);
  }, []);

  const removeItem = useCallback((productId) => {
    setItems((prev) => prev.filter((i) => i.id !== productId));
    toast.success('Article retiré du panier.');
  }, []);

  const updateQuantity = useCallback((productId, quantity) => {
    if (quantity <= 0) {
      setItems((prev) => prev.filter((i) => i.id !== productId));
      return;
    }
    setItems((prev) =>
      prev.map((i) => (i.id === productId ? { ...i, quantity } : i))
    );
  }, []);

  const clearCart = useCallback(() => {
    setItems([]);
    localStorage.removeItem(CART_KEY);
  }, []);

  const total = useMemo(
    () => items.reduce((sum, i) => sum + i.prix * i.quantity, 0),
    [items]
  );

  const count = useMemo(
    () => items.reduce((sum, i) => sum + i.quantity, 0),
    [items]
  );

  return (
    <CartContext.Provider
      value={{
        items,
        addItem,
        removeItem,
        updateQuantity,
        clearCart,
        total,
        count,
        isOpen,
        setIsOpen,
      }}
    >
      {children}
    </CartContext.Provider>
  );
}
