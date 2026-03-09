import { useState, useEffect } from 'react';
import { api } from '../services/api';

/**
 * OrderForm — Create a new order with line items
 *
 * WHAT THIS COMPONENT DOES:
 * - Fetches products so the user can select items
 * - Lets user add line items with quantity
 * - Calculates a preview total on the frontend
 * - Sends everything to POST /api/orders
 *
 * WHAT STUDENTS NEED TO DO ON THE BACKEND:
 * - Exercise 5: Build POST /api/orders
 *   - Validate customer_name and items
 *   - Insert the order, then insert each item
 *   - Calculate total_amount on the BACKEND (don't trust frontend math)
 *   - Return the created order with 201 status
 */
export default function OrderForm({ onCreated = () => {} }) {
  const [products, setProducts] = useState([]);
  const [customerName, setCustomerName] = useState('');
  const [notes, setNotes] = useState('');
  const [items, setItems] = useState([]);
  const [message, setMessage] = useState(null);
  const [saving, setSaving] = useState(false);

  // Load products for the item selector
  useEffect(() => {
    api.getProducts()
      .then((data) => setProducts(Array.isArray(data) ? data : data.data || []))
      .catch(() => {});
  }, []);

  const addItem = () => {
    setItems([...items, { product_id: '', product_name: '', quantity: 1, unit_price: 0 }]);
  };

  const updateItem = (index, field, value) => {
    const updated = [...items];
    updated[index][field] = value;

    // When product changes, auto-fill name and price
    if (field === 'product_id') {
      const product = products.find((p) => p.id === value);
      if (product) {
        updated[index].product_name = product.name;
        updated[index].unit_price = parseFloat(product.price);
      }
    }

    setItems(updated);
  };

  const removeItem = (index) => {
    setItems(items.filter((_, i) => i !== index));
  };

  // Frontend preview total — the backend should calculate its own total (Exercise 5)
  const previewTotal = items.reduce((sum, item) => sum + item.quantity * item.unit_price, 0);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (items.length === 0) {
      setMessage({ type: 'error', text: 'Add at least one item' });
      return;
    }

    setSaving(true);
    setMessage(null);

    try {
      // Send to backend — it should validate everything and calculate the real total
      await api.createOrder({
        customer_name: customerName,
        notes: notes,
        items: items.map((item) => ({
          product_id: item.product_id,
          product_name: item.product_name,
          quantity: item.quantity,
          unit_price: item.unit_price,
        })),
      });

      setMessage({ type: 'success', text: 'Order created!' });
      setCustomerName('');
      setNotes('');
      setItems([]);
      onCreated();
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <h3>New Order</h3>

      {message && (
        <p style={{ color: message.type === 'error' ? 'red' : 'green' }}>{message.text}</p>
      )}

      <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '10px', maxWidth: '600px' }}>
        <input
          placeholder="Customer name *"
          value={customerName}
          onChange={(e) => setCustomerName(e.target.value)}
          required
        />
        <textarea
          placeholder="Notes (optional)"
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          rows={2}
        />

        <h4>Items</h4>
        {items.map((item, i) => (
          <div key={i} style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
            <select
              value={item.product_id}
              onChange={(e) => updateItem(i, 'product_id', e.target.value)}
              required
            >
              <option value="">Select product...</option>
              {products.map((p) => (
                <option key={p.id} value={p.id}>{p.name} ({p.price})</option>
              ))}
            </select>
            <input
              type="number"
              min="1"
              value={item.quantity}
              onChange={(e) => updateItem(i, 'quantity', parseInt(e.target.value) || 1)}
              style={{ width: '60px' }}
            />
            <span>{(item.quantity * item.unit_price).toFixed(2)}</span>
            <button type="button" onClick={() => removeItem(i)}>x</button>
          </div>
        ))}

        <button type="button" onClick={addItem}>+ Add Item</button>

        <p><strong>Preview Total: {previewTotal.toFixed(2)}</strong></p>

        <button type="submit" disabled={saving}>
          {saving ? 'Creating...' : 'Create Order'}
        </button>
      </form>
    </div>
  );
}
