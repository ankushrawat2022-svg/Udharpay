import React, { useState } from 'react';
import { apiRequest } from '../services/api';

export default function QrScanPage({ onBack }) {
  const [token, setToken] = useState('QR-SHOP-SHP-DL-1001-VERIFIED');
  const [shop, setShop] = useState(null);
  const [error, setError] = useState('');

  const handleLookup = async (e) => {
    e.preventDefault();
    setError('');
    try {
      const res = await apiRequest(`/api/qr/shop/${encodeURIComponent(token.trim())}`);
      setShop(res.data.shop);
    } catch (err) {
      setShop(null);
      setError(err.message);
    }
  };

  return (
    <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 20 }}>
      <div className="panel" style={{ maxWidth: 520, width: '100%' }}>
        <button type="button" className="btn btn-outline" onClick={onBack} style={{ marginBottom: 14 }}>Back to Sign In</button>
        <h2 style={{ marginTop: 0 }}>Shop QR Verification</h2>
        <p className="muted">Scanning a shop QR displays verified shop identity only. It never creates Udhar debt automatically.</p>
        {error && <div className="error-box">{error}</div>}
        <form onSubmit={handleLookup} style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
          <input className="input" value={token} onChange={(e) => setToken(e.target.value)} placeholder="Enter Shop QR Token" required />
          <button type="submit" className="btn btn-primary">Verify QR</button>
        </form>
        {shop && (
          <div className="success-box">
            <strong>{shop.shop_name}</strong> ({shop.shop_code})<br />
            {shop.address}, {shop.city}, {shop.state} — {shop.pincode}<br />
            Status: {shop.is_verified ? 'Verified Merchant Shop' : 'Pending Verification'} · Debt created on scan: No
          </div>
        )}
      </div>
    </div>
  );
}
