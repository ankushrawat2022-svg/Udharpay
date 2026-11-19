import React, { useState } from 'react';
import Layout from '../components/Layout';
import { useApi } from '../hooks/useApi';
import { apiRequest } from '../services/api';

export default function SupportDashboard() {
  const [tab, setTab] = useState('tickets');
  const { data, loading, error, reload } = useApi('/api/support/tickets');
  const [replyText, setReplyText] = useState('');
  const [msg, setMsg] = useState('');

  const updateTicket = async (id, status) => {
    try {
      await apiRequest(`/api/support/tickets/${id}`, {
        method: 'PATCH',
        body: JSON.stringify({ status, reply: replyText || `Updated status to ${status}` }),
      });
      setMsg(`Ticket #${id} updated to ${status}.`);
      setReplyText('');
      reload();
    } catch (err) {
      setMsg(err.message);
    }
  };

  return (
    <Layout title="Support Panel" activeTab={tab} onSelectTab={setTab} navItems={[{ id: 'tickets', label: 'Support Tickets' }]}>
      {error && <div className="error-box">{error}</div>}
      {msg && <div className="success-box">{msg}</div>}
      {loading && <p className="muted">Loading tickets...</p>}
      {data && (
        <div className="panel">
          <h3>Tickets &amp; Complaints Queue</h3>
          <input className="input" style={{ marginBottom: 12 }} value={replyText} onChange={(e) => setReplyText(e.target.value)} placeholder="Type reply note before resolving or escalating..." />
          <div className="table-wrap">
            <table>
              <thead><tr><th>Ticket</th><th>Requester</th><th>Category</th><th>Subject</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                {data.tickets.map((t) => (
                  <tr key={t.id}>
                    <td className="tabular">{t.ticket_number}</td>
                    <td>{t.requester_name} ({t.requester_role})</td>
                    <td>{t.category}</td>
                    <td>{t.subject}</td>
                    <td>{t.status}</td>
                    <td style={{ display: 'flex', gap: 6 }}>
                      <button type="button" className="btn btn-emerald" onClick={() => updateTicket(t.id, 'resolved')}>Resolve</button>
                      <button type="button" className="btn btn-outline" onClick={() => updateTicket(t.id, 'escalated')}>Escalate</button>
                    </td>
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
