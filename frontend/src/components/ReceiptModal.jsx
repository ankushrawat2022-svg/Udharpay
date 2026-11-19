import React from 'react';
import { formatINR } from '../services/api';

export default function ReceiptModal({ receipt, onClose }) {
  if (!receipt) return null;
  return (
    <div style={{ position: 'fixed', inset: 0, background: 'rgba(15,23,42,0.55)', display: 'grid', placeItems: 'center', padding: 16, zIndex: 50 }}>
      <div className="panel" style={{ maxWidth: 460, width: '100%' }}>
        <h3 style={{ marginTop: 0 }}>UdharPay Payment Receipt (DEMO)</h3>
        <div className="muted">Receipt No: <span className="tabular">{receipt.receipt_number}</span></div>
        <hr style={{ border: 'none', borderTop: '1px solid #e2e8f0', margin: '12px 0' }} />
        <p><strong>Amount Paid:</strong> <span className="tabular">{formatINR(receipt.amount)}</span></p>
        <p><strong>Payment Ref:</strong> <span className="tabular">{receipt.payment_ref}</span></p>
        <p><strong>Bill Number:</strong> <span className="tabular">{receipt.bill_number || '-'}</span></p>
        <p><strong>Mode:</strong> {receipt.payment_mode} ({receipt.payment_type})</p>
        <div style={{ display: 'flex', gap: 10, marginTop: 18 }}>
          <button type="button" className="btn btn-primary" onClick={() => window.print()}>Print Receipt</button>
          <button type="button" className="btn btn-outline" onClick={onClose}>Close</button>
        </div>
      </div>
    </div>
  );
}
