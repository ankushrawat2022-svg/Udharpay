import React, { useState } from 'react';
import Layout from '../components/Layout';
import { useApi } from '../hooks/useApi';
import { formatINR } from '../services/api';

export default function FinanceDashboard() {
  const [tab, setTab] = useState('revenue');
  const { data, loading, error } = useApi('/api/finance/overview');

  return (
    <Layout title="Finance Panel" activeTab={tab} onSelectTab={setTab} navItems={[{ id: 'revenue', label: 'Revenue & GST Invoices' }]}>
      {error && <div className="error-box">{error}</div>}
      {loading && <p className="muted">Loading financial records...</p>}
      {data && (
        <>
          <div className="grid-4">
            <div className="panel"><div className="muted">Platform Revenue</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{formatINR(data.summary.platform_revenue)}</div></div>
            <div className="panel"><div className="muted">GST Collected</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{formatINR(data.summary.gst_tax_collected)}</div></div>
            <div className="panel"><div className="muted">Settlement Volume</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{formatINR(data.summary.merchant_settlement_volume)}</div></div>
            <div className="panel"><div className="muted">Partner Charges Est.</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{formatINR(data.summary.partner_charges_estimated)}</div></div>
          </div>
          <div className="panel">
            <h3>Merchant GST Invoices</h3>
            <div className="table-wrap">
              <table>
                <thead><tr><th>Invoice</th><th>Merchant</th><th>GSTIN</th><th>Subtotal</th><th>GST</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                  {data.invoices.map((inv) => (
                    <tr key={inv.id}>
                      <td className="tabular">{inv.invoice_number}</td>
                      <td>{inv.business_legal_name}</td>
                      <td className="tabular">{inv.gstin || '-'}</td>
                      <td className="tabular">{formatINR(inv.subtotal)}</td>
                      <td className="tabular">{formatINR(inv.gst_amount)}</td>
                      <td className="tabular">{formatINR(inv.total_amount)}</td>
                      <td>{inv.status}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </Layout>
  );
}
