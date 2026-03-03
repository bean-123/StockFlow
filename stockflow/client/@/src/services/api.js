const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost:8005/api';

async function fetchApi(endpoint, options = {}) {
  const token = localStorage.getItem('supabase_token');
  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
  const res = await fetch(`${API_BASE}${endpoint}`, { ...options, headers });
  if (!res.ok) throw new Error(`API error: ${res.status}`);
  return res.json();
}

export const api = {
    getOrders: () => fetchApi('/orders'),
    getProducts: () => fetchApi('/products'),
    getNotes: () => fetchApi('/notes'),
    addNote: (data) =>
      fetchApi('/notes', { method: 'POST', body: JSON.stringify(data) }),
    deleteNote: (id) =>
      fetchApi(`/notes/${id}`, { method: 'DELETE' }),
    generateStory: (genre) =>
      fetchApi('/ai/generate', { method: 'POST', body: JSON.stringify({ genre }) }),
    getLoginUrl: () => fetchApi('/auth/login-url'),
  };