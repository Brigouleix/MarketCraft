import React, { createContext, useState, useCallback, useContext, useEffect, useMemo, useRef } from 'react';
import toast from 'react-hot-toast';
import { AuthContext } from './AuthContext';

export const CartContext = createContext(null);

const CART_KEY = 'mc_cart';

const loadCart = (key) => {
  try {
    const stored = localStorage.getItem(key);
    return stored ? JSON.parse(stored) : [];
  } catch {
    return [];
  }
};

const saveCart = (key, items) => {
  try {
    localStorage.setItem(key, JSON.stringify(items));
  } catch {
    // quota exceeded – ignore
  }
};

export function CartProvider({ children }) {
  // Panier isolé par compte : clé dédiée à l'utilisateur connecté,
  // clé "invité" partagée sinon.
  const { user } = useContext(AuthContext) ?? {};
  const userId = user?.id ?? null;
  const storageKey = userId ? `${CART_KEY}_${userId}` : CART_KEY;

  const [items, setItems] = useState(() => loadCart(storageKey));
  const [isOpen, setIsOpen] = useState(false);

  const prevKey = useRef(storageKey);
  const skipSave = useRef(false);

  // Changement de compte (login/logout) : recharger le panier de la
  // nouvelle clé, sans que l'ancien panier ne soit écrit dessus.
  useEffect(() => {
    if (prevKey.current === storageKey) return;
    prevKey.current = storageKey;
    skipSave.current = true;

    let next = loadCart(storageKey);
    // À la connexion, on adopte le panier invité si l'utilisateur n'en a pas déjà un
    if (userId && next.length === 0) {
      const guestCart = loadCart(CART_KEY);
      if (guestCart.length > 0) {
        next = guestCart;
        localStorage.removeItem(CART_KEY);
      }
    }
    setItems(next);
  }, [storageKey, userId]);

  useEffect(() => {
    if (skipSave.current) {
      skipSave.current = false;
      return;
    }
    saveCart(storageKey, items);
  }, [items, storageKey]);

  const addItem = useCallback((product, quantity = 1) => {
    const stock = Number(product.stock ?? 0);
    if (stock <= 0) {
      toast.error('Ce produit est en rupture de stock.');
      return;
    }

    const existing = items.find((i) => i.id === product.id);
    const newQty = (existing?.quantity || 0) + quantity;
    if (newQty > stock) {
      toast.error('Stock insuffisant.');
      return;
    }

    if (existing) {
      toast.success('Quantité mise à jour dans le panier.');
      setItems((prev) =>
        prev.map((i) => (i.id === product.id ? { ...i, quantity: newQty } : i))
      );
    } else {
      toast.success('Produit ajouté au panier !');
      setItems((prev) => [...prev, { ...product, quantity }]);
    }
    setIsOpen(true);
  }, [items]);

  const removeItem = useCallback((productId) => {
    setItems((prev) => prev.filter((i) => i.id !== productId));
    toast.success('Article retiré du panier.');
  }, []);

  const updateQuantity = useCallback((productId, quantity) => {
    if (quantity <= 0) {
      setItems((prev) => prev.filter((i) => i.id !== productId));
      return;
    }
    const item = items.find((i) => i.id === productId);
    if (item && item.stock !== undefined && quantity > Number(item.stock)) {
      toast.error('Stock insuffisant.');
      return;
    }
    setItems((prev) =>
      prev.map((i) => (i.id === productId ? { ...i, quantity } : i))
    );
  }, [items]);

  const clearCart = useCallback(() => {
    setItems([]);
    localStorage.removeItem(storageKey);
  }, [storageKey]);

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
