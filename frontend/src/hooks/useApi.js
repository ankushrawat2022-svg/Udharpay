import { useCallback, useEffect, useState } from 'react';
import { apiRequest } from '../services/api';

export function useApi(path) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const reload = useCallback(async () => {
    if (!path) return;
    setLoading(true);
    setError('');
    try {
      const res = await apiRequest(path);
      setData(res.data);
    } catch (err) {
      setError(err.message || 'Failed to load data');
    } finally {
      setLoading(false);
    }
  }, [path]);

  useEffect(() => { reload(); }, [reload]);

  return { data, loading, error, reload };
}
