import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

const BASE_URL = 'http://localhost:8000/api';

const api = axios.create({ baseURL: BASE_URL });

api.interceptors.request.use(async (config) => {
  const token = await AsyncStorage.getItem('jwt_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (res) => res,
  async (err) => {
    if (err.response?.status === 401) {
      await AsyncStorage.removeItem('jwt_token');
    }
    return Promise.reject(err);
  },
);

export const authAPI = {
  login:    (data: { email: string; password: string }) => api.post('/auth/login', data),
  register: (data: object) => api.post('/auth/register', data),
  logout:   () => api.post('/auth/logout'),
  me:       () => api.get('/auth/me'),
};

export const productsAPI = {
  getAll:  (params?: object) => api.get('/products', { params }),
  getById: (id: number)      => api.get(`/products/${id}`),
  create:  (data: object)    => api.post('/products', data),
  update:  (id: number, data: object) => api.put(`/products/${id}`, data),
  delete:  (id: number)      => api.delete(`/products/${id}`),
  getAvis: (id: number)      => api.get(`/products/${id}/avis`),
  postAvis:(id: number, data: object) => api.post(`/products/${id}/avis`, data),
};

export const boutiquesAPI = {
  getAll:  (params?: object) => api.get('/boutiques', { params }),
  getById: (id: number)      => api.get(`/boutiques/${id}`),
  create:  (data: object)    => api.post('/boutiques', data),
  update:  (id: number, data: object) => api.put(`/boutiques/${id}`, data),
};

export const ordersAPI = {
  getAll:     ()              => api.get('/orders'),
  getById:    (id: number)    => api.get(`/orders/${id}`),
  create:     (data: object)  => api.post('/orders', data),
  updateStatus:(id: number, status: string) => api.put(`/orders/${id}/status`, { statut: status }),
};

export default api;
