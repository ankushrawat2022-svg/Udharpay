import React, { useState } from 'react';
import { AuthProvider, useAuth } from './context/AuthContext';
import LoginPage from './pages/LoginPage';
import QrScanPage from './pages/QrScanPage';
import MerchantDashboard from './dashboards/MerchantDashboard';
import CustomerDashboard from './dashboards/CustomerDashboard';
import AdminDashboard from './dashboards/AdminDashboard';
import SupportDashboard from './dashboards/SupportDashboard';
import FinanceDashboard from './dashboards/FinanceDashboard';
import ManagerDashboard from './dashboards/ManagerDashboard';

function RoleRouter() {
  const { user, loading } = useAuth();
  const [qrView, setQrView] = useState(false);

  if (loading) return <div style={{ padding: 32 }}>Loading UdharPay...</div>;
  if (!user) {
    return qrView ? <QrScanPage onBack={() => setQrView(false)} /> : <LoginPage onOpenQrLookup={() => setQrView(true)} />;
  }

  switch (user.role) {
    case 'merchant': return <MerchantDashboard />;
    case 'customer': return <CustomerDashboard />;
    case 'super_admin': return <AdminDashboard />;
    case 'support': return <SupportDashboard />;
    case 'finance': return <FinanceDashboard />;
    case 'manager': return <ManagerDashboard />;
    default: return <CustomerDashboard />;
  }
}

export default function App() {
  return (
    <AuthProvider>
      <RoleRouter />
    </AuthProvider>
  );
}
