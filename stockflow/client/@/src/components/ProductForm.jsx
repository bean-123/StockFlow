import { useState, useEffect } from "react";
import { api } from "../services/api";

export default function ProductForm({ product = null, onSaved = () => {} }) {
  const isEdit = !!product;

  const [form, setForm] = useState({
    name: product?.name || "",
    sku: product?.sku || "",
    price: product?.price || "",
    description: product?.description || "",
    category_id: product?.category_id ? String(product.category_id) : "",
  });

  const [imageFile, setImageFile] = useState(null);
  const [imagePreview, setImagePreview] = useState(product?.image_url || null);
  const [uploading, setUploading] = useState(false);
  const [categories, setCategories] = useState([]);
  const [message, setMessage] = useState(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    api
      .getCategories()
      .then((data) => {
        const fetched = Array.isArray(data) ? data : data.data || [];

        if (fetched.length > 0) {
          setCategories(fetched);
          return;
        }

        return api.seedCategories().then((seeded) => {
          const after = Array.isArray(seeded) ? seeded : seeded.data || [];
          if (after.length > 0) setCategories(after);
          else setCategories([{ id: "", name: "No categories available" }]);
        });
      })
      .catch(() =>
        setCategories([{ id: "", name: "No categories available" }]),
      );
  }, []);

  const handleChange = (e) =>
    setForm({ ...form, [e.target.name]: e.target.value });

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    setImageFile(file);
    setImagePreview(URL.createObjectURL(file));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setMessage(null);

    try {
      let imageUrl = product?.image_url || null;

      if (imageFile) {
        setUploading(true);
        const uploadResult = await api.uploadProductImage(imageFile);
        imageUrl = uploadResult.image_url;
        setUploading(false);
      }

      const productData = { ...form };
      if (imageUrl) productData.image_url = imageUrl;

      if (isEdit) {
        await api.updateProduct(product.id, productData);
        setMessage({ type: "success", text: "Product updated!" });
      } else {
        await api.createProduct(productData);
        setMessage({ type: "success", text: "Product created!" });
        setForm({
          name: "",
          sku: "",
          price: "",
          description: "",
          category_id: "",
        });
        setImageFile(null);
        setImagePreview(null);
      }

      onSaved();
    } catch (err) {
      setMessage({ type: "error", text: err.message });
      setUploading(false);
    } finally {
      setSaving(false);
    }
  };

  // 🔥 New: Delete handler
  const handleDelete = async () => {
    const confirmed = window.confirm(
      "Are you sure you want to delete this product?",
    );
    if (!confirmed) return;

    try {
      await api.deleteProduct(product.id);
      setMessage({ type: "success", text: "Product deleted!" });
      onSaved(); // Close form + refresh list
    } catch (err) {
      setMessage({ type: "error", text: err.message });
    }
  };

  return (
    <div>
      <h3>{isEdit ? "Edit Product" : "New Product"}</h3>

      {message && (
        <p style={{ color: message.type === "error" ? "red" : "green" }}>
          {message.text}
        </p>
      )}

      <form
        onSubmit={handleSubmit}
        style={{
          display: "flex",
          flexDirection: "column",
          gap: "8px",
          maxWidth: "400px",
        }}
      >
        <input
          name="name"
          placeholder="Product name *"
          value={form.name}
          onChange={handleChange}
          required
        />
        <input
          name="sku"
          placeholder="SKU (e.g. SKU-1234) *"
          value={form.sku}
          onChange={handleChange}
          required
        />
        <input
          name="price"
          type="number"
          step="0.01"
          placeholder="Price *"
          value={form.price}
          onChange={handleChange}
          required
        />
        <textarea
          name="description"
          placeholder="Description (optional)"
          value={form.description}
          onChange={handleChange}
          rows={3}
        />
        <select
          name="category_id"
          value={form.category_id}
          onChange={handleChange}
        >
          <option value="">Select category...</option>
          {categories.length > 0 ? (
            categories.map((cat) => (
              <option key={cat.id} value={String(cat.id)}>
                {cat.name}
              </option>
            ))
          ) : (
            <option value="" disabled>
              No categories available
            </option>
          )}
        </select>

        <label style={{ display: "flex", flexDirection: "column", gap: "4px" }}>
          <span>Product Image</span>
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp,image/gif"
            onChange={handleFileChange}
          />
        </label>

        {imagePreview && (
          <div>
            <img
              src={imagePreview}
              alt="Preview"
              style={{
                maxWidth: "200px",
                maxHeight: "200px",
                borderRadius: "8px",
                border: "1px solid #444",
              }}
            />
          </div>
        )}

        <button type="submit" disabled={saving || uploading}>
          {uploading
            ? "Uploading image..."
            : saving
              ? "Saving..."
              : isEdit
                ? "Update Product"
                : "Create Product"}
        </button>

        {/* 🔥 Delete button only when editing */}
        {isEdit && (
          <button
            type="button"
            onClick={handleDelete}
            style={{ marginTop: "10px", background: "#ff4d4f", color: "white" }}
          >
            Delete Product
          </button>
        )}
      </form>
    </div>
  );
}
