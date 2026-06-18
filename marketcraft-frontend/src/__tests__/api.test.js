import axios from 'axios';
import { authAPI, productsAPI, ordersAPI, dashboardAPI } from '../services/api';

jest.mock('axios');

// Simule un token en localStorage
beforeEach(() => {
  localStorage.setItem('mc_token', 'fake-jwt-token');
  axios.create.mockReturnValue(axios);
  axios.interceptors = {
    request:  { use: jest.fn() },
    response: { use: jest.fn() },
  };
});

afterEach(() => {
  localStorage.clear();
  jest.clearAllMocks();
});

describe('authAPI', () => {
  it('login envoie les bons paramètres', async () => {
    axios.post = jest.fn().mockResolvedValue({
      data: { access_token: 'tok', refresh_token: 'ref', user: { id: 1 } },
    });

    const res = await authAPI.login({ email: 'paul@test.com', password: 'pass' });
    expect(axios.post).toHaveBeenCalledWith('/auth/login', {
      email: 'paul@test.com',
      password: 'pass',
    });
    expect(res.data.access_token).toBe('tok');
  });
});

describe('productsAPI', () => {
  it('getAll envoie les filtres en params', async () => {
    axios.get = jest.fn().mockResolvedValue({ data: { data: [] } });
    await productsAPI.getAll({ categorie: 'bois', page: 1 });
    expect(axios.get).toHaveBeenCalledWith('/products', {
      params: { categorie: 'bois', page: 1 },
    });
  });

  it('getById envoie le bon id', async () => {
    axios.get = jest.fn().mockResolvedValue({ data: { data: { id: 5 } } });
    await productsAPI.getById(5);
    expect(axios.get).toHaveBeenCalledWith('/products/5');
  });
});

describe('ordersAPI', () => {
  it('create envoie les données de commande', async () => {
    axios.post = jest.fn().mockResolvedValue({ data: { success: true } });
    const payload = { items: [{ produit_id: 1, quantite: 2 }], adresse_livraison_id: 3 };
    await ordersAPI.create(payload);
    expect(axios.post).toHaveBeenCalledWith('/orders', payload);
  });
});

describe('dashboardAPI', () => {
  it('getVendeurStats appelle la bonne route', async () => {
    axios.get = jest.fn().mockResolvedValue({ data: {} });
    await dashboardAPI.getVendeurStats();
    expect(axios.get).toHaveBeenCalledWith('/dashboard/stats');
  });

  it('getAcheteurStats appelle la bonne route', async () => {
    axios.get = jest.fn().mockResolvedValue({ data: {} });
    await dashboardAPI.getAcheteurStats();
    expect(axios.get).toHaveBeenCalledWith('/dashboard/acheteur');
  });
});
