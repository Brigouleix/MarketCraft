import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';

export interface CartItem {
  id: number;
  nom: string;
  prix: number;
  image?: string;
  boutique?: { nom: string };
  quantite: number;
}

interface CartContextValue {
  items: CartItem[];
  count: number;
  total: number;
  addItem: (product: Omit<CartItem, 'quantite'>, qty?: number) => void;
  removeItem: (id: number) => void;
  updateQty: (id: number, qty: number) => void;
  clearCart: () => void;
}

const CartContext = createContext<CartContextValue | null>(null);
const STORAGE_KEY = 'marketcraft_cart';

export function CartProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<CartItem[]>([]);

  useEffect(() => {
    AsyncStorage.getItem(STORAGE_KEY).then((raw) => {
      if (raw) setItems(JSON.parse(raw));
    });
  }, []);

  const persist = (next: CartItem[]) => {
    setItems(next);
    AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(next));
  };

  const addItem = (product: Omit<CartItem, 'quantite'>, qty = 1) => {
    setItems((prev) => {
      const existing = prev.find((i) => i.id === product.id);
      const next = existing
        ? prev.map((i) => i.id === product.id ? { ...i, quantite: i.quantite + qty } : i)
        : [...prev, { ...product, quantite: qty }];
      AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(next));
      return next;
    });
  };

  const removeItem = (id: number) => persist(items.filter((i) => i.id !== id));

  const updateQty = (id: number, qty: number) => {
    if (qty <= 0) { removeItem(id); return; }
    persist(items.map((i) => i.id === id ? { ...i, quantite: qty } : i));
  };

  const clearCart = () => persist([]);

  return (
    <CartContext.Provider value={{
      items,
      count: items.reduce((s, i) => s + i.quantite, 0),
      total: items.reduce((s, i) => s + i.prix * i.quantite, 0),
      addItem, removeItem, updateQty, clearCart,
    }}>
      {children}
    </CartContext.Provider>
  );
}

export const useCart = () => {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error('useCart must be used within CartProvider');
  return ctx;
};
