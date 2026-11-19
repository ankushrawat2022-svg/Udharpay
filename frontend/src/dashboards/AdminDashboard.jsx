import React, { useState } from 'react';
import Layout from '../components/Layout';
import { useApi } from '../hooks/useApi';
import { apiRequest, formatINR } from '../services/api';

export default function AdminDashboard() {
  const [tab, setTab] = useState('overview');
  const { data, loading, error, reload } = useApi('/api/admin/overview');
  const [msg, setMsg] = useState('');

  const updateMerchant = async (id, status) => {
    try {
      const res = await apiRequest(`/api/admin/merchants/${id}/verify`, {
        method: 'POST',
        body: JSON.stringify({ status }),
      });
      setMsg(res.message);
      reload();
    } catch (err) {
      setMsg(err.message);
    }
  };

  return (
    <Layout
      title="Super Admin Panel"
      activeTab={tab}
      onSelectTab={setTab}
      navItems={[
        { id: 'overview', label: 'Platform Overview' },
        { id: 'merchants', label: 'Merchant Verification' },
        { id: 'partners', label: 'Partners & DB Status' },
        { id: 'audit', label: 'Audit Logs' },
      ]}
    >
      {error && <div className="error-box">{error}</div>}
      {msg && <div className="success-box">{msg}</div>}
      {loading && <p className="muted">Loading platform overview...</p>}

      {data && (
        <>
          <div className="grid-4">
            <div className="panel"><div className="muted">Total Merchants</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{data.metrics.total_merchants}</div></div>
            <div className="panel"><div className="muted">Total Customers</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{data.metrics.total_customers}</div></div>
            <div className="panel"><div className="muted">Total Outstanding</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{formatINR(data.metrics.total_outstanding_credit)}</div></div>
            <div className="panel"><div className="muted">Total Repayments</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700, color: '#059669' }}>{formatINR(data.metrics.total_repayments)}</div></div>
          </div>

          {(tab === 'overview' || tab === 'merchants') && (
            <div className="panel">
              <h3>Merchant Verification Queue</h3>
              <div className="table-wrap">
                <table>
                  <thead><tr><th>Business</th><th>Owner</th><th>Shops</th><th>Status</th><th>Action</th></tr></thead>
                  <tbody>
                    {data.merchants.map((m) => (
                      <tr key={m.id}>
                        <td>{m.business_legal_name}</td>
                        <td>{m.full_name} ({m.phone})</td>
                        <td className="tabular">{m.shop_count}</td>
                        <td>{m.verification_status}</td>
                        <td style={{ display: 'flex', gap: 6 }}>
                          <button type="button" className="btn btn-emerald" onClick={() => updateMerchant(m.id, 'verified')}>Verify</button>
                          <button type="button" className="btn btn-outline" onClick={() => updateMerchant(m.id, 'suspended')}>Suspend</button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {tab === 'partners' && (
            <div className="panel">
              <h3>Payment Partners &amp; Database Health</h3>
              <p className="muted">Database: {data.database_status?.engine} · Connected: {String(data.database_status?.connected)} · Tables: {data.database_status?.tables_count}</p>
              <div className="table-wrap">
                <table>
                  <thead><tr><th>Partner</th><th>Capability</th><th>Environment</th><th>Configuration Status</th></tr></thead>
                  <tbody>
                    {data.partners.map((p) => (
                      <tr key={p.id}>
                        <td>{p.partner_name}</td>
                        <td>{p.capability}</td>
                        <td>{p.environment}</td>
                        <td>{p.configuration_status}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {tab === 'audit' && (
            <div className="panel">
              <h3>Immutable Audit Logs</h3>
              <div className="table-wrap">
                <table>
                  <thead><tr><th>Action</th><th>Role</th><th>Entity</th><th>IP</th><th>Timestamp</th></tr></thead>
                  <tbody>
                    {data.audit_logs.map((a) => (
                      <tr key={a.id}>
                        <td className="tabular">{a.action}</td>
                        <td>{a.actor_role}</td>
                        <td className="tabular">{a.entity_type}#{a.entity_id}</td>
                        <td className="tabular">{a.ip_address}</td>
                        <td className="tabular">{a.created_at}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </>
      )}
    </Layout>
  );
}
