import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { CartContext } from '../contexts/CartContext';
import { AuthContext } from '../contexts/AuthContext';
import { MemoryRouter } from 'react-router-dom';

jest.mock('react-hot-toast', () => ({ success: jest.fn(), error: jest.fn() }));

import CartPage from '../pages/CartPage';

const mockProduct = {
  id: 1,
  nom: 'Bol en noyer ciré',
  prix: 45.00,
  images: [],
  boutique: { nom: "L'Atelier de Paul" },
};

function renderCart(items = [], cartCtx = {}) {
  const defaultCart = {
    items,
    count: items.reduce((s, i) => s + i.quantity, 0),
    total: items.reduce((s, i) => s + i.prix * i.quantity, 0),
    addItem: jest.fn(),
    removeItem: jest.fn(),
    updateQuantity: jest.fn(),
    clearCart: jest.fn(),
    ...cartCtx,
  };

  return render(
    <AuthContext.Provider value={{ isAuthenticated: true, user: { prenom: 'Paul' }, loading: false }}>
      <CartContext.Provider value={defaultCart}>
        <MemoryRouter><CartPage /></MemoryRouter>
      </CartContext.Provider>
    </AuthContext.Provider>
  );
}

describe('CartPage', () => {
  it('affiche le panier vide si aucun article', () => {
    renderCart([]);
    expect(screen.getByText(/panier est vide/i)).toBeInTheDocument();
  });

  it('affiche les articles du panier', () => {
    renderCart([{ ...mockProduct, quantity: 2 }]);
    expect(screen.getByText('Bol en noyer ciré')).toBeInTheDocument();
    expect(screen.getByText(/90/)).toBeInTheDocument(); // 45 * 2
  });

  it('appelle removeItem au clic sur supprimer', () => {
    const removeItem = jest.fn();
    renderCart([{ ...mockProduct, quantity: 1 }], { removeItem });
    fireEvent.click(screen.getByRole('button', { name: /supprimer|retirer|remove/i }));
    expect(removeItem).toHaveBeenCalledWith(1);
  });
});
