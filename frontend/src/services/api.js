const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080/UdharPay/backend';

export function getAuthHeaders(extra = {}) {
  const token = localStorage.getItem('udharpay_token');
  const csrf = localStorage.getItem('udharpay_csrf');
  const headers = { 'Content-Type': 'application/json', ...extra };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (csrf) headers['X-CSRF-Token'] = csrf;
  return headers;
}

export async function apiRequest(path, options = {}) {
  const res = await fetch(`${API_BASE}${path}`, {
    ...options,
    credentials: 'include',
    headers: getAuthHeaders(options.headers || {}),
  });
  const json = await res.json().catch(() => ({ success: false, message: 'Invalid JSON response from PHP API.' }));
  if (!res.ok || !json.success) {
    throw new Error(json.message || `API Error (${res.status})`);
  }
  return json;
}

export function formatINR(value) {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(value || 0));
}
