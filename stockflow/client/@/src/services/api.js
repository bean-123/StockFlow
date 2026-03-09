const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost:8005/api';

async function fetchApi(endpoint, options = {}) {
  const token = localStorage.getItem('supabase_token');

  // Don't set Content-Type for FormData — the browser sets it automatically
  // with the correct multipart boundary
  const isFormData = options.body instanceof FormData;

  const headers = {
    ...(!isFormData ? { 'Content-Type': 'application/json' } : {}),
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };

  const res = await fetch(`${API_BASE}${endpoint}`, { ...options, headers });

  if (!res.ok) {
    const error = await res.json().catch(() => ({ error: `HTTP ${res.status}` }));
    throw new Error(error.error || error.message || `API error: ${res.status}`);
  }

  return res.json();
}

export const api = {
  // Auth
  getLoginUrl: () => fetchApi('/auth/login-url'),
  getUser: () => fetchApi('/auth/user'),

  // Products (Exercise 1, 2, 4)
  getProducts: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return fetchApi(`/products${query ? '?' + query : ''}`);
  },
  getProduct: (id) => fetchApi(`/products/${id}`),
  createProduct: (data) =>
    fetchApi('/products', { method: 'POST', body: JSON.stringify(data) }),
  updateProduct: (id, data) =>
    fetchApi(`/products/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  deleteProduct: (id) =>
    fetchApi(`/products/${id}`, { method: 'DELETE' }),
  uploadProductImage: (file) => {
    const formData = new FormData();
    formData.append('image', file);
    return fetchApi('/products/upload-image', { method: 'POST', body: formData });
  },

  // Orders (Exercise 3, 5)
  getOrders: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return fetchApi(`/orders${query ? '?' + query : ''}`);
  },
  getOrder: (id) => fetchApi(`/orders/${id}`),
  createOrder: (data) =>
    fetchApi('/orders', { method: 'POST', body: JSON.stringify(data) }),
  updateOrderStatus: (id, status) =>
    fetchApi(`/orders/${id}/status`, { method: 'PUT', body: JSON.stringify({ status }) }),

  // Stock Movements (Exercise 3)
  getStockMovements: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return fetchApi(`/stock/movements${query ? '?' + query : ''}`);
  },
  createStockMovement: (data) =>
    fetchApi('/stock/movements', { method: 'POST', body: JSON.stringify(data) }),

  // AI (Exercise 6)
  generateDescription: (productId) =>
    fetchApi('/ai/describe', { method: 'POST', body: JSON.stringify({ product_id: productId }) }),
  getStockAdvice: () =>
    fetchApi('/ai/stock-advice', { method: 'POST' }),
  summarizeOrders: () =>
    fetchApi('/ai/summarize-orders', { method: 'POST' }),

  // Dashboard (Exercise 7)
  getDashboardSummary: () => fetchApi('/dashboard/summary'),
};
