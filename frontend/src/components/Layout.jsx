import React from 'react';
import { useAuth } from '../context/AuthContext';

export default function Layout({ title, navItems, activeTab, onSelectTab, children }) {
  const { user, logout } = useAuth();
  return (
    <div className="app-shell">
      <aside className="sidebar">
        <h1>UdharPay</h1>
        <div className="muted" style={{ color: '#94a3b8', marginBottom: 12 }}>
          {user?.role?.replace('_', ' ')} · {user?.is_demo ? 'DEMO' : 'Live'}
        </div>
        {navItems.map((item) => (
          <button
            key={item.id}
            type="button"
            className={`nav-btn ${activeTab === item.id ? 'active' : ''}`}
            onClick={() => onSelectTab(item.id)}
          >
            {item.label}
          </button>
        ))}
      </aside>
      <div className="main-area">
        <header className="topbar">
          <div>
            <strong>{title}</strong>
            <span className="muted"> · {user?.full_name} ({user?.phone})</span>
          </div>
          <button type="button" className="btn btn-outline" onClick={logout}>Sign Out</button>
        </header>
        <main className="content">{children}</main>
      </div>
    </div>
  );
}
