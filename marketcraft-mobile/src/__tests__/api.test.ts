import { authAPI, productsAPI, ordersAPI, dashboardAPI } from '../services/api';

// Mock axios
jest.mock('axios', () => {
  const mockAxios = {
    create: jest.fn(() => mockAxios),
    get:    jest.fn(),
    post:   jest.fn(),
    put:    jest.fn(),
    delete: jest.fn(),
    interceptors: {
      request:  { use: jest.fn() },
      response: { use: jest.fn() },
    },
  };
  return { default: mockAxios, ...mockAxios };
});

// Mock AsyncStorage
jest.mock('@react-native-async-storage/async-storage', () => ({
  getItem:    jest.fn().mockResolvedValue('fake-token'),
  setItem:    jest.fn(),
  removeItem: jest.fn(),
}));

import axios from 'axios';
const mockedAxios = axios as jest.Mocked<typeof axios>;

describe('authAPI', () => {
  it('login POST /auth/login', async () => {
    mockedAxios.post = jest.fn().mockResolvedValue({
      data: { access_token: 'tok', refresh_token: 'ref', user: { id: 1 } },
    });
    const res = await authAPI.login({ email: 'paul@test.com', password: 'pass' });
    expect(mockedAxios.post).toHaveBeenCalledWith('/auth/login', {
      email: 'paul@test.com',
      password: 'pass',
    });
    expect(res.data.access_token).toBe('tok');
  });

  it('logout POST /auth/logout', async () => {
    mockedAxios.post = jest.fn().mockResolvedValue({ data: { success: true } });
    await authAPI.logout();
    expect(mockedAxios.post).toHaveBeenCalledWith('/auth/logout');
  });
});

describe('productsAPI', () => {
  it('getAll GET /products avec params', async () => {
    mockedAxios.get = jest.fn().mockResolvedValue({ data: { data: [] } });
    await productsAPI.getAll({ categorie: 'bois' });
    expect(mockedAxios.get).toHaveBeenCalledWith('/products', { params: { categorie: 'bois' } });
  });

  it('getById GET /products/:id', async () => {
    mockedAxios.get = jest.fn().mockResolvedValue({ data: { data: { id: 3 } } });
    await productsAPI.getById(3);
    expect(mockedAxios.get).toHaveBeenCalledWith('/products/3');
  });

  it('delete DELETE /products/:id', async () => {
    mockedAxios.delete = jest.fn().mockResolvedValue({ data: { success: true } });
    await productsAPI.delete(7);
    expect(mockedAxios.delete).toHaveBeenCalledWith('/products/7');
  });
});

describe('dashboardAPI', () => {
  it('getVendeurStats GET /dashboard/stats', async () => {
    mockedAxios.get = jest.fn().mockResolvedValue({ data: {} });
    await dashboardAPI.getVendeurStats();
    expect(mockedAxios.get).toHaveBeenCalledWith('/dashboard/stats');
  });

  it('getAcheteurStats GET /dashboard/acheteur', async () => {
    mockedAxios.get = jest.fn().mockResolvedValue({ data: {} });
    await dashboardAPI.getAcheteurStats();
    expect(mockedAxios.get).toHaveBeenCalledWith('/dashboard/acheteur');
  });
});

describe('ordersAPI', () => {
  it('create POST /orders', async () => {
    mockedAxios.post = jest.fn().mockResolvedValue({ data: { success: true } });
    const payload = { items: [{ produit_id: 1, quantite: 2 }] };
    await ordersAPI.create(payload);
    expect(mockedAxios.post).toHaveBeenCalledWith('/orders', payload);
  });

  it('updateStatus PUT /orders/:id/status', async () => {
    mockedAxios.put = jest.fn().mockResolvedValue({ data: { success: true } });
    await ordersAPI.updateStatus(5, 'expediee');
    expect(mockedAxios.put).toHaveBeenCalledWith('/orders/5/status', { statut: 'expediee' });
  });
});
