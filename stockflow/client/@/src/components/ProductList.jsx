import { useState, useEffect } from 'react';
import { api } from '../services/api';

/**
 * ProductList — Displays products from the API
 *
 * WHAT THIS COMPONENT DOES:
 * - Fetches products from GET /api/products
 * - Shows them in a simple table
 * - Has filter inputs that send query params to the backend
 *
 * WHAT STUDENTS NEED TO DO ON THE BACKEND:
 * - Exercise 1: The table expects 'stock_status' and 'category_name' fields
 *   that don't exist yet — students must add them in PHP post-processing
 * - Exercise 2: The filters send query params — students must read and use them
 */
export default function ProductList() {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Filter state — these get sent as query params to the backend
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');
  const [status, setStatus] = useState('active');

  // Fetch products when filters change
  useEffect(() => {
    setLoading(true);
    setError(null);

    // Build query params from the filter state
    // These are sent to: GET /api/products?search=...&category=...&status=...
    // The backend needs to READ these params and FILTER the data (Exercise 2)
    const params = {};
    if (search) params.search = search;
    if (category) params.category = category;
    if (status) params.status = status;

    api.getProducts(params)
      .then((data) => {
        // The API might return { data: [...] } or just [...]
        // depending on how students structure the response
        setProducts(Array.isArray(data) ? data : data.data || []);
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [search, category, status]);

  return (
    <div>
      <h2>Products</h2>

      {/* Filter controls — Exercise 2: backend must handle these params */}
      <div style={{ display: 'flex', gap: '10px', marginBottom: '15px', flexWrap: 'wrap' }}>
        <input
          type="text"
          placeholder="Search products..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <select value={category} onChange={(e) => setCategory(e.target.value)}>
          <option value="">All Categories</option>
          <option value="Audio">Audio</option>
          <option value="Cables & Adapters">Cables & Adapters</option>
          <option value="Displays">Displays</option>
          <option value="Keyboards">Keyboards</option>
          <option value="Mice & Peripherals">Mice & Peripherals</option>
          <option value="Power & Charging">Power & Charging</option>
        </select>
        <select value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="active">Active</option>
          <option value="archived">Archived</option>
          <option value="">All</option>
        </select>
      </div>

      {loading && <p>Loading products...</p>}
      {error && <p style={{ color: 'red' }}>Error: {error}</p>}

      {!loading && !error && (
        <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
          <thead>
            <tr style={{ borderBottom: '2px solid #555' }}>
              <th></th>
              <th>Name</th>
              <th>SKU</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {products.map((product) => (
              <tr key={product.id} style={{ borderBottom: '1px solid #333' }}>
                {/*
                  Exercise 8: The backend should include 'image_url' in the response.
                  Once students implement the upload, products will have image URLs.
                  Exercise 1: When post-processing, keep image_url in the output!
                */}
                <td>
                  {product.image_url ? (
                    <img
                      src={product.image_url}
                      alt={product.name}
                      style={{ width: '40px', height: '40px', objectFit: 'cover', borderRadius: '4px' }}
                    />
                  ) : (
                    <span style={{ display: 'inline-block', width: '40px', height: '40px', background: '#333', borderRadius: '4px' }} />
                  )}
                </td>
                <td>{product.name}</td>
                <td>{product.sku}</td>
                {/*
                  Exercise 1: The backend should return 'category_name' as a flat string.
                  Currently, category comes as product.categories.name (nested object).
                  Once students add post-processing, it should be product.category_name.
                */}
                <td>{product.category_name || product.categories?.name || '—'}</td>
                <td>{product.price}</td>
                <td>{product.stock_quantity}</td>
                {/*
                  Exercise 1: The backend should return 'stock_status' as a string.
                  This field doesn't exist in the database — students must calculate it.
                  Values: 'in_stock', 'low_stock', 'out_of_stock'
                */}
                <td>
                  {product.stock_status === 'out_of_stock' && <span style={{ color: 'red' }}>Out of Stock</span>}
                  {product.stock_status === 'low_stock' && <span style={{ color: 'orange' }}>Low Stock</span>}
                  {product.stock_status === 'in_stock' && <span style={{ color: 'green' }}>In Stock</span>}
                  {!product.stock_status && <span style={{ color: 'gray' }}>—</span>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {!loading && !error && products.length === 0 && <p>No products found.</p>}
    </div>
  );
}
