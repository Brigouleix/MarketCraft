import axios from 'axios';

const BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api';

const api = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json' },
  timeout: 15000,
});

// ── Request interceptor: attach JWT ──────────────────────────────────────────
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('mc_token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
  },
  (error) => Promise.reject(error)
);

// ── Response interceptor: handle 401 / token refresh ────────────────────────
let isRefreshing = false;
let failedQueue = [];

const processQueue = (error, token = null) => {
  failedQueue.forEach((prom) => (error ? prom.reject(error) : prom.resolve(token)));
  failedQueue = [];
};

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;

    if (error.response?.status === 401 && !originalRequest._retry) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        })
          .then((token) => {
            originalRequest.headers.Authorization = `Bearer ${token}`;
            return api(originalRequest);
          })
          .catch((err) => Promise.reject(err));
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        const refreshToken = localStorage.getItem('mc_refresh_token');
        if (!refreshToken) throw new Error('No refresh token');

        const { data } = await axios.post(`${BASE_URL}/auth/refresh`, {
          refresh_token: refreshToken,
        });

        localStorage.setItem('mc_token', data.access_token);
        if (data.refresh_token) localStorage.setItem('mc_refresh_token', data.refresh_token);

        api.defaults.headers.common.Authorization = `Bearer ${data.access_token}`;
        originalRequest.headers.Authorization = `Bearer ${data.access_token}`;
        processQueue(null, data.access_token);
        return api(originalRequest);
      } catch (refreshError) {
        processQueue(refreshError, null);
        localStorage.removeItem('mc_token');
        localStorage.removeItem('mc_refresh_token');
        localStorage.removeItem('mc_user');
        window.location.href = '/login';
        return Promise.reject(refreshError);
      } finally {
        isRefreshing = false;
      }
    }

    return Promise.reject(error);
  }
);

// ── Auth ─────────────────────────────────────────────────────────────────────
export const authAPI = {
  login:        (credentials) => api.post('/auth/login', credentials),
  register:     (userData)    => api.post('/auth/register', userData),
  captcha:      ()            => api.get('/auth/captcha'),
  me:           ()            => api.get('/auth/me'),
  updateMe:     (data)        => api.put('/auth/me', data),
  logout:       ()            => api.post('/auth/logout'),
  refreshToken: (token)       => api.post('/auth/refresh', { refresh_token: token }),
};

// ── Products ─────────────────────────────────────────────────────────────────
export const productsAPI = {
  getAll: (params) => api.get('/products', { params }),
  getById: (id) => api.get(`/products/${id}`),
  create: (data) => api.post('/products', data),
  update: (id, data) => api.put(`/products/${id}`, data),
  delete: (id) => api.delete(`/products/${id}`),
  getSimilar: (id) => api.get(`/products/${id}/similar`),
};

// ── Orders ───────────────────────────────────────────────────────────────────
export const ordersAPI = {
  getAll: (params) => api.get('/orders', { params }),
  getById: (id) => api.get(`/orders/${id}`),
  create: (data) => api.post('/orders', data),
  updateStatus: (id, status) => api.patch(`/orders/${id}/status`, { status }),
};

// ── Boutiques ─────────────────────────────────────────────────────────────────
export const boutiquesAPI = {
  getAll: (params) => api.get('/boutiques', { params }),
  getById: (id) => api.get(`/boutiques/${id}`),
  create: (data) => api.post('/boutiques', data),
  update: (id, data) => api.put(`/boutiques/${id}`, data),
  getProducts: (id, params) => api.get(`/boutiques/${id}/products`, { params }),
};

// ── Avis (Reviews) ───────────────────────────────────────────────────────────
export const avisAPI = {
  getByProduct: (productId, params) => api.get(`/products/${productId}/avis`, { params }),
  create: (productId, data) => api.post(`/products/${productId}/avis`, data),
  delete: (productId, avisId) => api.delete(`/products/${productId}/avis/${avisId}`),
};

// ── Dashboard ─────────────────────────────────────────────────────────────────
export const dashboardAPI = {
  getStats:    () => api.get('/dashboard/stats'),
  activityLog: (params) => api.get('/dashboard/activity-log', { params }),
};

// ── Vendor ────────────────────────────────────────────────────────────────────
export const vendorAPI = {
  getOrders: (params) => api.get('/vendor/orders', { params }),
  getProducts: (params) => api.get('/products', { params: { ...params, my: 'true' } }),
  getVendeurStats:  () => api.get('/dashboard/stats'),
  getAcheteurStats: () => api.get('/dashboard/acheteur'),
};

// ── Upload ───────────────────────────────────────────────────────────────────
export const uploadAPI = {
  image:  (file) => {
    const form = new FormData();
    form.append('image', file);
    return api.post('/upload/image', form, { headers: { 'Content-Type': 'multipart/form-data' } });
  },
  images: (files) => {
    const form = new FormData();
    files.forEach((f) => form.append('images[]', f));
    return api.post('/upload/images', form, { headers: { 'Content-Type': 'multipart/form-data' } });
  },
};

// ── AI Search ─────────────────────────────────────────────────────────────────
export const searchAPI = {
  aiSearch: (query) => api.post('/search/ai', { query }),
  search: (params) => api.get('/search', { params }),
};

export default api;
