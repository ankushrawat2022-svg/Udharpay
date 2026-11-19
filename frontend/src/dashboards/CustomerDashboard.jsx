import React, { useState } from 'react';
import Layout from '../components/Layout';
import ReceiptModal from '../components/ReceiptModal';
import { useApi } from '../hooks/useApi';
import { apiRequest, formatINR } from '../services/api';

export default function CustomerDashboard() {
  const [tab, setTab] = useState('bills');
  const { data, loading, error, reload } = useApi('/api/customer/dashboard');
  const [feedback, setFeedback] = useState('');
  const [receipt, setReceipt] = useState(null);

  const decideBill = async (billId, decision) => {
    setFeedback('');
    try {
      const res = await apiRequest(`/api/bills/${billId}/decide`, {
        method: 'POST',
        body: JSON.stringify({ decision }),
      });
      setFeedback(res.message);
      reload();
    } catch (err) {
      setFeedback(err.message);
    }
  };

  const repayBill = async (bill, paymentType) => {
    setFeedback('');
    const amount = paymentType === 'full' ? Number(bill.pending_amount) : Math.max(1, Math.round(Number(bill.pending_amount) / 2));
    try {
      const res = await apiRequest('/api/payments/repay', {
        method: 'POST',
        headers: { 'Idempotency-Key': `repay-${bill.id}-${Date.now()}` },
        body: JSON.stringify({ bill_id: bill.id, amount, payment_type: paymentType, payment_mode: 'demo_upi' }),
      });
      setReceipt({ ...res.data, bill_number: bill.bill_number, payment_mode: 'demo_upi', payment_type: paymentType });
      setFeedback(res.message);
      reload();
    } catch (err) {
      setFeedback(err.message);
    }
  };

  const cancelMandate = async (mandateId) => {
    setFeedback('');
    try {
      const res = await apiRequest(`/api/mandates/${mandateId}/cancel`, {
        method: 'POST',
        body: JSON.stringify({ reason: 'Customer revoked AutoPay mandate via dashboard' }),
      });
      setFeedback(res.message);
      reload();
    } catch (err) {
      setFeedback(err.message);
    }
  };

  return (
    <Layout
      title="Customer Panel"
      activeTab={tab}
      onSelectTab={setTab}
      navItems={[
        { id: 'bills', label: 'Bills & Approvals' },
        { id: 'shops', label: 'Connected Shops' },
        { id: 'mandates', label: 'AutoPay Mandates' },
        { id: 'payments', label: 'Payment History' },
      ]}
    >
      {error && <div className="error-box">{error}</div>}
      {feedback && <div className="success-box">{feedback}</div>}
      {loading && <p className="muted">Loading customer account...</p>}

      {data && (
        <>
          <div className="grid-4">
            <div className="panel"><div className="muted">Total Outstanding Credit</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{formatINR(data.summary.total_outstanding)}</div></div>
            <div className="panel"><div className="muted">Connected Shops</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{data.summary.connected_shops_count}</div></div>
            <div className="panel"><div className="muted">Pending Bill Approvals</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{data.summary.pending_approvals}</div></div>
            <div className="panel"><div className="muted">Active Mandates</div><div className="tabular" style={{ fontSize: 22, fontWeight: 700 }}>{data.summary.active_mandates}</div></div>
          </div>

          {tab === 'bills' && (
            <div className="panel">
              <h3>Credit Bills (Explicit Approval Required)</h3>
              <div className="table-wrap">
                <table>
                  <thead><tr><th>Bill No</th><th>Shop</th><th>Total</th><th>Pending</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
                  <tbody>
                    {data.bills.map((b) => (
                      <tr key={b.id}>
                        <td className="tabular">{b.bill_number}</td>
                        <td>{b.shop_name}</td>
                        <td className="tabular">{formatINR(b.total_amount)}</td>
                        <td className="tabular">{formatINR(b.pending_amount)}</td>
                        <td className="tabular">{b.due_date}</td>
                        <td>{b.status}</td>
                        <td style={{ display: 'flex', gap: 6 }}>
                          {b.status === 'pending_customer_approval' && (
                            <>
                              <button type="button" className="btn btn-emerald" onClick={() => decideBill(b.id, 'accept')}>Accept</button>
                              <button type="button" className="btn btn-danger" onClick={() => decideBill(b.id, 'reject')}>Reject</button>
                            </>
                          )}
                          {(b.status === 'active' || b.status === 'partially_paid') && (
                            <>
                              <button type="button" className="btn btn-primary" onClick={() => repayBill(b, 'full')}>Pay Full (DEMO)</button>
                              <button type="button" className="btn btn-outline" onClick={() => repayBill(b, 'partial')}>Pay 50% (DEMO)</button>
                            </>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {tab === 'shops' && (
            <div className="panel">
              <h3>Connected Shops &amp; Balances</h3>
              <div className="table-wrap">
                <table>
                  <thead><tr><th>Shop</th><th>City</th><th>Total Credited</th><th>Total Repaid</th><th>Outstanding</th><th>Limit</th></tr></thead>
                  <tbody>
                    {data.connected_shops.map((s) => (
                      <tr key={s.shop_id}>
                        <td>{s.shop_name}</td>
                        <td>{s.city}</td>
                        <td className="tabular">{formatINR(s.total_credited)}</td>
                        <td className="tabular">{formatINR(s.total_repaid)}</td>
                        <td className="tabular">{formatINR(s.outstanding_balance)}</td>
                        <td className="tabular">{formatINR(s.credit_limit)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {tab === 'mandates' && (
            <div className="panel">
              <h3>UPI AutoPay Mandates (RBI / NPCI Compliant)</h3>
              <p className="muted">Cancelling a mandate preserves your outstanding Udhar balance, notifies the merchant, and allows repayment via UPI or counter cash.</p>
              <div className="table-wrap">
                <table>
                  <thead><tr><th>Mandate Ref</th><th>Shop</th><th>Max Amount</th><th>Frequency</th><th>Status</th><th>Action</th></tr></thead>
                  <tbody>
                    {data.mandates.map((m) => (
                      <tr key={m.id}>
                        <td className="tabular">{m.mandate_ref}</td>
                        <td>{m.shop_name}</td>
                        <td className="tabular">{formatINR(m.max_amount)}</td>
                        <td>{m.frequency}</td>
                        <td>{m.status}</td>
                        <td>
                          {m.status === 'active' && (
                            <button type="button" className="btn btn-danger" onClick={() => cancelMandate(m.id)}>Request Cancellation</button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {tab === 'payments' && (
            <div className="panel">
              <h3>Payment History</h3>
              <div className="table-wrap">
                <table>
                  <thead><tr><th>Receipt</th><th>Shop</th><th>Bill</th><th>Amount</th><th>Mode</th><th>Status</th></tr></thead>
                  <tbody>
                    {data.payments.map((p) => (
                      <tr key={p.id}>
                        <td className="tabular">{p.receipt_number}</td>
                        <td>{p.shop_name}</td>
                        <td className="tabular">{p.bill_number}</td>
                        <td className="tabular">{formatINR(p.amount)}</td>
                        <td>{p.payment_mode}</td>
                        <td>{p.status}</td>
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
