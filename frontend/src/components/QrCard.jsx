import React, { useEffect, useState } from 'react';
import QRCode from 'qrcode';

export default function QrCard({ shop }) {
  const [dataUrl, setDataUrl] = useState('');

  useEffect(() => {
    if (!shop?.qr_token) return;
    QRCode.toDataURL(shop.qr_payload_url || shop.qr_token, { width: 180, margin: 1 })
      .then(setDataUrl)
      .catch(() => setDataUrl(''));
  }, [shop]);

  if (!shop) return null;

  return (
    <div className="panel" style={{ display: 'flex', gap: 20, alignItems: 'center', flexWrap: 'wrap' }}>
      {dataUrl && <img src={dataUrl} alt={`Shop QR for ${shop.shop_name}`} width={160} height={160} />}
      <div>
        <h3 style={{ margin: '0 0 6px' }}>{shop.shop_name}</h3>
        <div className="muted">Code: {shop.shop_code} · {shop.is_verified ? 'Verified Shop' : 'Pending Verification'}</div>
        <div className="muted" style={{ marginTop: 4 }}>QR Token: <span className="tabular">{shop.qr_token}</span></div>
        <p className="muted" style={{ marginTop: 8 }}>
          Zero customer private data is embedded in this QR. Scanning displays shop details only and never creates debt automatically.
        </p>
      </div>
    </div>
  );
}
