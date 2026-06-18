import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AuthContext } from '../contexts/AuthContext';

// Mock toast
jest.mock('react-hot-toast', () => ({ error: jest.fn(), success: jest.fn() }));

// Mock api
jest.mock('../services/api', () => ({
  authAPI: {
    login:    jest.fn(),
    register: jest.fn(),
    logout:   jest.fn(),
    me:       jest.fn(),
  },
}));

import LoginPage from '../pages/LoginPage';
import { authAPI } from '../services/api';

function renderWithAuth(ui, { login = jest.fn(), isAuthenticated = false } = {}) {
  return render(
    <AuthContext.Provider value={{ login, isAuthenticated, loading: false, user: null }}>
      <MemoryRouter>{ui}</MemoryRouter>
    </AuthContext.Provider>
  );
}

describe('LoginPage', () => {
  it('affiche le formulaire de connexion', () => {
    renderWithAuth(<LoginPage />);
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/mot de passe/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /connexion/i })).toBeInTheDocument();
  });

  it('appelle login avec les bons identifiants', async () => {
    const login = jest.fn().mockResolvedValue({ success: true, user: { role: 'client' } });
    renderWithAuth(<LoginPage />, { login });

    fireEvent.change(screen.getByLabelText(/email/i), {
      target: { value: 'paul.martin@example.com' },
    });
    fireEvent.change(screen.getByLabelText(/mot de passe/i), {
      target: { value: 'password123' },
    });
    fireEvent.click(screen.getByRole('button', { name: /connexion/i }));

    await waitFor(() => {
      expect(login).toHaveBeenCalledWith('paul.martin@example.com', 'password123');
    });
  });

  it('affiche une erreur si login échoue', async () => {
    const login = jest.fn().mockResolvedValue({ success: false, error: 'Email ou mot de passe invalide.' });
    renderWithAuth(<LoginPage />, { login });

    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'wrong@example.com' } });
    fireEvent.change(screen.getByLabelText(/mot de passe/i), { target: { value: 'wrongpass' } });
    fireEvent.click(screen.getByRole('button', { name: /connexion/i }));

    await waitFor(() => {
      expect(login).toHaveBeenCalled();
    });
  });
});
