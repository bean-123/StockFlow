import { useState, useEffect } from "react";
import { api } from "../services/api";
import ProductForm from "./ProductForm";

/**
 * ProductList — Displays products from the API
 *
 * WHAT THIS COMPONENT DOES:
 * - Fetches products from GET /api/products
 * - Shows them in a simple table
 * - Has filter inputs that send query params to the backend
 * - Lets users edit product details inline via ProductForm (except stock/status)
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
  const [editingProduct, setEditingProduct] = useState(null);
  const [refreshKey, setRefreshKey] = useState(0);

  // Filter state — these get sent as query params to the backend
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("");
  const [status, setStatus] = useState("active");

  const fetchProducts = () => {
    setLoading(true);
    setError(null);

    const params = {};
    if (search) params.search = search;
    if (category) params.category = category;
    if (status) params.status = status;

    api
      .getProducts(params)
      .then((data) => {
        setProducts(Array.isArray(data) ? data : data.data || []);
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  };

  // Fetch products when filters or refresh key change
  useEffect(() => {
    fetchProducts();
  }, [search, category, status, refreshKey]);

  return (
    <div>
      <h2>Products</h2>

      {/* Filter controls — Exercise 2: backend must handle these params */}
      <div
        style={{
          display: "flex",
          gap: "10px",
          marginBottom: "15px",
          flexWrap: "wrap",
        }}
      >
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
      {error && <p style={{ color: "red" }}>Error: {error}</p>}

      {!loading && !error && (
        <div style={{ display: "flex", gap: "24px", alignItems: "flex-start" }}>
          <div style={{ flex: 2 }}>
            <table
              style={{
                width: "100%",
                borderCollapse: "collapse",
                textAlign: "left",
              }}
            >
              <thead>
                <tr style={{ borderBottom: "2px solid #555" }}>
                  <th></th>
                  <th>Name</th>
                  <th>SKU</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Stock</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {products.map((product) => (
                  <tr
                    key={product.id}
                    style={{ borderBottom: "1px solid #333" }}
                  >
                    <td>
                      {product.image_url ? (
                        <img
                          src={product.image_url}
                          alt={product.name}
                          style={{
                            width: "40px",
                            height: "40px",
                            objectFit: "cover",
                            borderRadius: "4px",
                          }}
                        />
                      ) : (
                        <span
                          style={{
                            display: "inline-block",
                            width: "40px",
                            height: "40px",
                            background: "#333",
                            borderRadius: "4px",
                          }}
                        />
                      )}
                    </td>
                    <td>{product.name}</td>
                    <td>{product.sku}</td>
                    <td>
                      {product.category_name || product.categories?.name || "—"}
                    </td>
                    <td>{product.price}</td>
                    <td>{product.stock_quantity}</td>
                    <td>
                      {product.stock_status === "out_of_stock" && (
                        <span style={{ color: "red" }}>Out of Stock</span>
                      )}
                      {product.stock_status === "low_stock" && (
                        <span style={{ color: "orange" }}>Low Stock</span>
                      )}
                      {product.stock_status === "in_stock" && (
                        <span style={{ color: "green" }}>In Stock</span>
                      )}
                      {!product.stock_status && (
                        <span style={{ color: "gray" }}>—</span>
                      )}
                    </td>
                    <td>
                      <button
                        onClick={() => setEditingProduct(product)}
                        style={{ marginLeft: "10px" }}
                      >
                        Edit
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div style={{ flex: 1, minWidth: "320px" }}>
            {editingProduct ? (
              <div
                style={{
                  padding: "16px",
                  border: "1px solid #ddd",
                  borderRadius: "10px",
                }}
              >
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    marginBottom: "10px",
                  }}
                >
                  <h3 style={{ margin: 0 }}>Edit {editingProduct.name}</h3>
                  <button onClick={() => setEditingProduct(null)}>
                    Cancel
                  </button>
                </div>

                <ProductForm
                  product={editingProduct}
                  onSaved={() => {
                    setEditingProduct(null);
                    setRefreshKey((prev) => prev + 1);
                  }}
                />

                <p style={{ marginTop: "8px", color: "#666" }}>
                  Note: stock and status remain read-only (managed by stock
                  movements/status flows).
                </p>
              </div>
            ) : (
              <p
                style={{
                  color: "#666",
                  padding: "16px",
                  border: "1px dashed #ccc",
                  borderRadius: "8px",
                }}
              >
                Select a product row and click Edit. You can update name, sku,
                price, description, category, and image; stock/status cannot be
                changed here.
              </p>
            )}
          </div>
        </div>
      )}
      {!loading && !error && products.length === 0 && <p>No products found.</p>}
    </div>
  );
}
