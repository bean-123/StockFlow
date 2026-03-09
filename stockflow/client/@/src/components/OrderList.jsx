import { useState, useEffect } from 'react';
import { api } from '../services/api';

/**
 * OrderList — Displays orders from the API
 *
 * WHAT THIS COMPONENT DOES:
 * - Fetches orders from GET /api/orders
 * - Shows them in a table with date and status
 * - Has a status filter dropdown
 *
 * WHAT STUDENTS NEED TO DO ON THE BACKEND:
 * - Exercise 3: Format dates (created_date, created_ago fields)
 * - Exercise 5: Build the status update and create endpoints
 */
export default function OrderList() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [statusFilter, setStatusFilter] = useState('');

  const fetchOrders = () => {
    setLoading(true);
    const params = {};
    if (statusFilter) params.status = statusFilter;

    api.getOrders(params)
      .then((data) => setOrders(Array.isArray(data) ? data : data.data || []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { fetchOrders(); }, [statusFilter]);

  // Status update handler — calls PUT /api/orders/{id}/status (Exercise 5)
  const handleStatusChange = async (orderId, newStatus) => {
    try {
      await api.updateOrderStatus(orderId, newStatus);
      fetchOrders(); // Refresh the list
    } catch (err) {
      alert('Error: ' + err.message);
    }
  };

  const statusColors = {
    draft: '#888',
    confirmed: '#4488ff',
    fulfilled: '#44bb44',
    cancelled: '#cc4444'
  };

  return (
    <div>
      <h2>Orders</h2>

      <div style={{ marginBottom: '15px' }}>
        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="confirmed">Confirmed</option>
          <option value="fulfilled">Fulfilled</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      {loading && <p>Loading orders...</p>}
      {error && <p style={{ color: 'red' }}>Error: {error}</p>}

      {!loading && !error && (
        <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
          <thead>
            <tr style={{ borderBottom: '2px solid #555' }}>
              <th>Customer</th>
              <th>Status</th>
              <th>Total</th>
              <th>Created</th>
              <th>Age</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {orders.map((order) => (
              <tr key={order.id} style={{ borderBottom: '1px solid #333' }}>
                <td>{order.customer_name}</td>
                <td>
                  <span style={{ color: statusColors[order.status] || '#888' }}>
                    {order.status}
                  </span>
                </td>
                <td>{order.total_amount}</td>
                {/*
                  Exercise 3: The backend should return formatted date fields:
                  - 'created_date': formatted date string (e.g., "9 Mar 2026, 14:30")
                  - 'created_ago': relative time (e.g., "2 days ago")
                  Until students implement this, it falls back to raw created_at
                */}
                <td>{order.created_date || order.created_at}</td>
                <td>{order.created_ago || '—'}</td>
                <td>
                  {/* Exercise 5: Status transitions — backend validates which are allowed */}
                  {order.status === 'draft' && (
                    <>
                      <button onClick={() => handleStatusChange(order.id, 'confirmed')}>Confirm</button>
                      <button onClick={() => handleStatusChange(order.id, 'cancelled')}>Cancel</button>
                    </>
                  )}
                  {order.status === 'confirmed' && (
                    <>
                      <button onClick={() => handleStatusChange(order.id, 'fulfilled')}>Fulfill</button>
                      <button onClick={() => handleStatusChange(order.id, 'cancelled')}>Cancel</button>
                    </>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {!loading && !error && orders.length === 0 && <p>No orders found.</p>}
    </div>
  );
}
