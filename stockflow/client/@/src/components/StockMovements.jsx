import { useState, useEffect } from 'react';
import { api } from '../services/api';

/**
 * StockMovements — View and record stock movements
 *
 * WHAT THIS COMPONENT DOES:
 * - Fetches stock movement history from GET /api/stock/movements
 * - Shows a form to record new movements (in/out/adjustment)
 * - Displays timestamps (formatted by the backend)
 *
 * WHAT STUDENTS NEED TO DO ON THE BACKEND:
 * - Exercise 3: Build GET and POST /api/stock/movements
 *   - Format dates and add relative time
 *   - Validate movement data
 *   - Update product stock_quantity after recording a movement
 */
export default function StockMovements() {
  const [movements, setMovements] = useState([]);
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Form state for recording a new movement
  const [form, setForm] = useState({
    product_id: '',
    quantity: '',
    movement_type: 'in',
    reason: '',
    notes: '',
  });
  const [message, setMessage] = useState(null);

  const fetchMovements = () => {
    setLoading(true);
    api.getStockMovements()
      .then((data) => setMovements(Array.isArray(data) ? data : data.data || []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchMovements();
    // Load products for the dropdown
    api.getProducts()
      .then((data) => setProducts(Array.isArray(data) ? data : data.data || []))
      .catch(() => {});
  }, []);

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setMessage(null);

    try {
      await api.createStockMovement({
        ...form,
        quantity: parseInt(form.quantity),
      });
      setMessage({ type: 'success', text: 'Movement recorded!' });
      setForm({ product_id: '', quantity: '', movement_type: 'in', reason: '', notes: '' });
      fetchMovements();
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    }
  };

  const typeColors = { in: '#44bb44', out: '#cc4444', adjustment: '#4488ff' };

  return (
    <div>
      <h2>Stock Movements</h2>

      {/* Record new movement form */}
      <div style={{ marginBottom: '20px', padding: '15px', border: '1px solid #444', borderRadius: '8px' }}>
        <h3>Record Movement</h3>
        {message && (
          <p style={{ color: message.type === 'error' ? 'red' : 'green' }}>{message.text}</p>
        )}
        <form onSubmit={handleSubmit} style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', alignItems: 'end' }}>
          <select name="product_id" value={form.product_id} onChange={handleChange} required>
            <option value="">Select product...</option>
            {products.map((p) => (
              <option key={p.id} value={p.id}>{p.name} (stock: {p.stock_quantity})</option>
            ))}
          </select>
          <select name="movement_type" value={form.movement_type} onChange={handleChange}>
            <option value="in">Stock In</option>
            <option value="out">Stock Out</option>
            <option value="adjustment">Adjustment</option>
          </select>
          <input name="quantity" type="number" min="1" placeholder="Qty" value={form.quantity} onChange={handleChange} required style={{ width: '70px' }} />
          <input name="reason" placeholder="Reason" value={form.reason} onChange={handleChange} />
          <button type="submit">Record</button>
        </form>
      </div>

      {/* Movement history */}
      {loading && <p>Loading movements...</p>}
      {error && <p style={{ color: 'red' }}>Error: {error}</p>}

      {!loading && !error && (
        <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
          <thead>
            <tr style={{ borderBottom: '2px solid #555' }}>
              <th>Product</th>
              <th>Type</th>
              <th>Qty</th>
              <th>Reason</th>
              <th>When</th>
            </tr>
          </thead>
          <tbody>
            {movements.map((m) => (
              <tr key={m.id} style={{ borderBottom: '1px solid #333' }}>
                {/*
                  Exercise 3: Backend should join product name via:
                  'select' => '*,products(name,sku)'
                  Then post-process to flatten it
                */}
                <td>{m.product_name || m.products?.name || '—'}</td>
                <td>
                  <span style={{ color: typeColors[m.movement_type] || '#888' }}>
                    {m.movement_type}
                  </span>
                </td>
                <td>{m.quantity}</td>
                <td>{m.reason || '—'}</td>
                {/*
                  Exercise 3: Backend should return 'created_ago' (e.g., "2 hours ago")
                  Falls back to raw created_at if not yet implemented
                */}
                <td>{m.created_ago || m.created_at}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {!loading && !error && movements.length === 0 && <p>No stock movements recorded yet.</p>}
    </div>
  );
}
