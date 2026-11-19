import React, { useState } from 'react';
import Layout from '../components/Layout';
import QrCard from '../components/QrCard';
import ReceiptModal from '../components/ReceiptModal';
import { useApi } from '../hooks/useApi';
import { apiRequest, formatINR } from '../services/api';

export default function MerchantDashboard() {
  const [tab, setTab] = useState('overview');
  const [selectedShopId, setSelectedShopId] = useState('');
  const { data, loading, error, reload } = useApi(selectedShopId ? `/api/merchant/dashboard?shop_id=${selectedShopId}` : '/api/merchant/dashboard');
  const [customerId, setCustomerId] = useState('');
  const [itemName, setItemName] = useState('');
  const [qty, setQty] = useState('1');
  const [price, setPrice] = useState('');
  const [dueDate, setDueDate] = useState(() => new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10));
  const [feedback, setFeedback] = useState('');
  const [receipt, setReceipt] = useState(null);

  const shops = data?.shops || [];
  const activeShop = shops.find((s) => String(s.id) === String(data?.active_shop_id)) || shops[0];

  const handleCreateBill = async (e) => {
    e.preventDefault();
    setFeedback('');
    try {
      const res = await apiRequest('/api/bills', {
        method: 'POST',
        headers: { 'Idempotency-Key': `bill-${Date.now()}-${Math.random().toString(16).slice(2)}` },
        body: JSON.stringify({
          shop_id: activeShop.id,
          customer_id: Number(customerId || data.customers[0]?.id),
          due_date: dueDate,
          items: [{ item_name: itemName, quantity: Number(qty), unit: 'pcs', unit_price: Number(price) }],
        }),
      });
      setFeedback(`Bill ${res.data.bill_number} sent to customer for explicit approval.`);
      setItemName('');
      setPrice('');
      reload();
    } catch (err) {
      setFeedback(err.message);
    }
  };

  return (
    <Layout
      title="Merchant Panel"
      activeTab={tab}
      onSelectTab={setTab}
      navItems={[
        { id: 'overview', label: 'Overview & Shop QR' },
        { id: 'create-bill', label: 'Create Credit Bill' },
        { id: 'customers', label: 'Customers & Limits' },
        { id: 'ledger', label: 'Append-Only Ledger' },
      ]}
    >
      {error && <div className="error-box">{error}</div>}
      {feedback && <div className="success-box">{feedback}</div>}
      {loading && <p className="muted">Loading merchant workspace...</p>}

      {data && (
        <>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
            <select className="select" style={{ maxWidth: 320 }} value={data.active_shop_id || ''} onChange={(e) => setSelectedShopId(e.target.value)}>
              {shops.map((s) => <option key={s.id} value={s.id}>{s.shop_name} ({s.shop_code})</option>)}
            </select>
            <button type="button" className="btn btn-outline" onClick={reload}>Refresh</button>
          </div>

          <div className="grid-4">
            <div className="panel"><div className="muted">Total Outstanding</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{formatINR(data.summary.total_outstanding)}</div></div>
            <div className="panel"><div className="muted">Total Repaid</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700, color: '#059669' }}>{formatINR(data.summary.total_paid)}</div></div>
            <div className="panel"><div className="muted">Pending Customer Approval</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{data.summary.pending_approval_bills}</div></div>
            <div className="panel"><div className="muted">Customers Linked</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{data.summary.active_customers}</div></div>
          </div>

          {tab === 'overview' && (
            <>
              <QrCard shop={activeShop} />
              <div className="panel">
                <h3>Recent Credit Bills</h3>
                <div className="table-wrap">
                  <table>
                    <thead><tr><th>Bill No</th><th>Customer</th><th>Total</th><th>Pending</th><th>Due Date</th><th>Status</th></tr></thead>
                    <tbody>
                      {data.bills.map((b) => (
                        <tr key={b.id}>
                          <td className="tabular">{b.bill_number}</td>
                          <td>{b.customer_name}</td>
                          <td className="tabular">{formatINR(b.total_amount)}</td>
                          <td className="tabular">{formatINR(b.pending_amount)}</td>
                          <td className="tabular">{b.due_date}</td>
                          <td>{b.status}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </>
          )}

          {tab === 'create-bill' && (
            <div className="panel">
              <h3>Create Customer Credit Bill</h3>
              <p className="muted">Bills start in pending_customer_approval status until the customer explicitly accepts.</p>
              <form onSubmit={handleCreateBill} style={{ display: 'grid', gap: 12, maxWidth: 520 }}>
                <select className="select" value={customerId || data.customers[0]?.id || ''} onChange={(e) => setCustomerId(e.target.value)}>
                  {data.customers.map((c) => <option key={c.id} value={c.id}>{c.full_name} ({c.phone}) — Limit {formatINR(c.credit_limit)}</option>)}
                </select>
                <input className="input" value={itemName} onChange={(e) => setItemName(e.target.value)} placeholder="Product / Item name" required />
                <div style={{ display: 'flex', gap: 10 }}>
                  <input className="input" type="number" min="1" step="0.5" value={qty} onChange={(e) => setQty(e.target.value)} placeholder="Qty" required />
                  <input className="input" type="number" min="1" step="0.01" value={price} onChange={(e) => setPrice(e.target.value)} placeholder="Unit Price (₹)" required />
                </div>
                <input className="input" type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} required />
                <button type="submit" className="btn btn-emerald">Send Bill for Customer Approval</button>
              </form>
            </div>
          )}

          {tab === 'customers' && (
            <div className="panel">
              <h3>Shop Customers &amp; Credit Limits</h3>
              <div className="table-wrap">
                <table>
                  <thead><tr><th>Customer</th><th>Phone</th><th>Outstanding</th><th>Credit Limit</th><th>Status</th><th>Risk Review</th></tr></thead>
                  <tbody>
                    {data.customers.map((c) => (
                      <tr key={c.id}>
                        <td>{c.full_name}</td>
                        <td className="tabular">{c.phone}</td>
                        <td className="tabular">{formatINR(c.outstanding_balance)}</td>
                        <td className="tabular">{formatINR(c.credit_limit)}</td>
                        <td>{c.is_credit_blocked ? 'Blocked' : 'Active'}</td>
                        <td>{c.risk_review?.basis_summary}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {tab === 'ledger' && (
            <div className="panel">
              <h3>Append-Only Financial Ledger</h3>
              <div className="table-wrap">
                <table>
                  <thead><tr><th>Ref</th><th>Entry Type</th><th>Customer</th><th>Amount</th><th>Running Balance</th><th>Description</th></tr></thead>
                  <tbody>
                    {data.ledger.map((l) => (
                      <tr key={l.id}>
                        <td className="tabular">{l.reference_number}</td>
                        <td>{l.entry_type}</td>
                        <td>{l.customer_name}</td>
                        <td className="tabular">{formatINR(l.amount)}</td>
                        <td className="tabular">{formatINR(l.running_balance)}</td>
                        <td>{l.description}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </>
      )}
      <ReceiptModal receipt={receipt} onClose={() => setReceipt(null)} />
    </Layout>
  );
}
