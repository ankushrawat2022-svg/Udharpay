import React, { useState } from 'react';
import Layout from '../components/Layout';
import { useApi } from '../hooks/useApi';

export default function ManagerDashboard() {
  const [tab, setTab] = useState('shops');
  const { data, loading, error } = useApi('/api/manager/shops');

  return (
    <Layout title="Manager & Staff Panel" activeTab={tab} onSelectTab={setTab} navItems={[{ id: 'shops', label: 'Assigned Shops' }]}>
      {error && <div className="error-box">{error}</div>}
      {loading && <p className="muted">Loading assigned shops...</p>}
      {data && (
        <div className="panel">
          <h3>Assigned Shops &amp; Scoped Permissions</h3>
          <div className="table-wrap">
            <table>
              <thead><tr><th>Shop</th><th>Role</th><th>Create Bill</th><th>Record Payment</th><th>Change Limit</th><th>Verified</th></tr></thead>
              <tbody>
                {data.assigned_shops.map((s) => (
                  <tr key={s.shop_id}>
                    <td>{s.shop_name} ({s.shop_code})</td>
                    <td>{s.staff_role}</td>
                    <td>{s.can_create_bill ? 'Allowed' : 'Restricted'}</td>
                    <td>{s.can_record_payment ? 'Allowed' : 'Restricted'}</td>
                    <td>{s.can_change_limit ? 'Allowed' : 'Restricted'}</td>
                    <td>{s.is_verified ? 'Verified' : 'Pending'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </Layout>
  );
}
