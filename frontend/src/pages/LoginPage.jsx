import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { apiRequest } from '../services/api';

const DEMO_ACCOUNTS = [
  { role: 'Merchant (DEMO)', identifier: '9800000002' },
  { role: 'Customer (DEMO)', identifier: '9800000003' },
  { role: 'Super Admin (DEMO)', identifier: '9800000001' },
  { role: 'Support (DEMO)', identifier: '9800000004' },
  { role: 'Finance (DEMO)', identifier: '9800000005' },
  { role: 'Manager (DEMO)', identifier: '9800000006' },
];

export default function LoginPage({ onOpenQrLookup }) {
  const { login, verifyOtp } = useAuth();
  const [mode, setMode] = useState('password');
  const [identifier, setIdentifier] = useState('9800000002');
  const [password, setPassword] = useState('Demo@1234');
  const [otp, setOtp] = useState('');
  const [demoOtpHint, setDemoOtpHint] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const handlePasswordLogin = async (e) => {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      await login(identifier, password);
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleRequestOtp = async () => {
    setError('');
    setBusy(true);
    try {
      const res = await apiRequest('/api/auth/otp/request', {
        method: 'POST',
        body: JSON.stringify({ phone: identifier }),
      });
      setDemoOtpHint(`DEMO OTP (SMS NOT CONFIGURED): ${res.data.demo_otp}`);
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleVerifyOtp = async (e) => {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      await verifyOtp(identifier, otp);
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 20 }}>
      <div className="panel" style={{ maxWidth: 480, width: '100%' }}>
        <h2 style={{ marginTop: 0 }}>UdharPay Sign In</h2>
        <p className="muted">Connected to Core PHP 8.3+ REST API · Safe DEMO Mode Enabled</p>
        {error && <div className="error-box">{error}</div>}
        {demoOtpHint && <div className="success-box">{demoOtpHint}</div>}

        <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
          <button type="button" className={`btn ${mode === 'password' ? 'btn-primary' : 'btn-outline'}`} onClick={() => setMode('password')}>
            Password Login
          </button>
          <button type="button" className={`btn ${mode === 'otp' ? 'btn-primary' : 'btn-outline'}`} onClick={() => setMode('otp')}>
            Mobile OTP Login
          </button>
          <button type="button" className="btn btn-outline" onClick={onOpenQrLookup}>
            Verify Shop QR
          </button>
        </div>

        {mode === 'password' ? (
          <form onSubmit={handlePasswordLogin} style={{ display: 'grid', gap: 12 }}>
            <input className="input" value={identifier} onChange={(e) => setIdentifier(e.target.value)} placeholder="Mobile number or email" required />
            <input className="input" type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="Password" required />
            <button type="submit" className="btn btn-emerald" disabled={busy}>
              {busy ? 'Signing in...' : 'Sign In'}
            </button>
          </form>
        ) : (
          <form onSubmit={handleVerifyOtp} style={{ display: 'grid', gap: 12 }}>
            <input className="input" value={identifier} onChange={(e) => setIdentifier(e.target.value)} placeholder="10-digit Indian mobile" required />
            <button type="button" className="btn btn-outline" onClick={handleRequestOtp} disabled={busy}>Send DEMO OTP</button>
            <input className="input" value={otp} onChange={(e) => setOtp(e.target.value)} placeholder="Enter 6-digit OTP" required />
            <button type="submit" className="btn btn-emerald" disabled={busy}>Verify &amp; Sign In</button>
          </form>
        )}

        <hr style={{ border: 'none', borderTop: '1px solid #e2e8f0', margin: '18px 0' }} />
        <div className="muted" style={{ marginBottom: 8 }}>Quick-fill DEMO Accounts (Password: Demo@1234):</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
          {DEMO_ACCOUNTS.map((acc) => (
            <button
              key={acc.identifier}
              type="button"
              className="btn btn-outline"
              onClick={() => { setIdentifier(acc.identifier); setPassword('Demo@1234'); }}
            >
              {acc.role}
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
