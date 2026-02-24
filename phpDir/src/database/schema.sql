-- ============================================================
-- StockFlow — Database Schema
-- ============================================================
-- Run this in Supabase SQL Editor
-- Role-based access control (admin, manager, staff)
-- ============================================================

-- Drop tables if they exist (careful in production!)
DROP TABLE IF EXISTS order_items CASCADE;
DROP TABLE IF EXISTS orders CASCADE;
DROP TABLE IF EXISTS stock_movements CASCADE;
DROP TABLE IF EXISTS products CASCADE;
DROP TABLE IF EXISTS categories CASCADE;
DROP TABLE IF EXISTS user_roles CASCADE;

-- Drop old functions if they exist
DROP FUNCTION IF EXISTS update_updated_at CASCADE;
DROP FUNCTION IF EXISTS get_user_role CASCADE;
DROP FUNCTION IF EXISTS is_admin CASCADE;
DROP FUNCTION IF EXISTS is_admin_or_manager CASCADE;

-- ============================================================
-- USER ROLES (simple role assignment)
-- ============================================================
CREATE TABLE user_roles (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE UNIQUE,
    role TEXT NOT NULL CHECK (role IN ('admin', 'manager', 'staff')) DEFAULT 'staff',
    created_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX idx_user_roles_user ON user_roles(user_id);

-- ============================================================
-- CATEGORIES
-- ============================================================
CREATE TABLE categories (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    created_at TIMESTAMPTZ DEFAULT now()
);

-- ============================================================
-- PRODUCTS
-- ============================================================
CREATE TABLE products (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    name TEXT NOT NULL,
    sku TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT '',
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category_id UUID REFERENCES categories(id) ON DELETE SET NULL,
    image_url TEXT,
    status TEXT NOT NULL CHECK (status IN ('active', 'archived')) DEFAULT 'active',
    stock_quantity INTEGER NOT NULL DEFAULT 0,
    reorder_threshold INTEGER NOT NULL DEFAULT 10,
    supplier TEXT DEFAULT '',
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_status ON products(status);

-- ============================================================
-- STOCK MOVEMENTS
-- ============================================================
CREATE TABLE stock_movements (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    quantity INTEGER NOT NULL,
    movement_type TEXT NOT NULL CHECK (movement_type IN ('in', 'out', 'adjustment')),
    reason TEXT DEFAULT '',
    notes TEXT DEFAULT '',
    created_by UUID REFERENCES auth.users(id),
    created_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX idx_stock_movements_product ON stock_movements(product_id);

-- ============================================================
-- ORDERS
-- ============================================================
CREATE TABLE orders (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    customer_name TEXT NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('draft', 'confirmed', 'fulfilled', 'cancelled')) DEFAULT 'draft',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT '',
    created_by UUID REFERENCES auth.users(id),
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX idx_orders_status ON orders(status);

-- ============================================================
-- ORDER ITEMS
-- ============================================================
CREATE TABLE order_items (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id UUID REFERENCES products(id) ON DELETE SET NULL,
    product_name TEXT NOT NULL,
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX idx_order_items_order ON order_items(order_id);

-- ============================================================
-- AUTO-UPDATE TIMESTAMPS
-- ============================================================
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_products_updated
    BEFORE UPDATE ON products
    FOR EACH ROW EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_orders_updated
    BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- HELPER FUNCTIONS FOR RLS
-- ============================================================

-- Get current user's role
CREATE OR REPLACE FUNCTION get_user_role()
RETURNS TEXT AS $$
DECLARE
    user_role TEXT;
BEGIN
    SELECT role INTO user_role FROM user_roles WHERE user_id = auth.uid();
    RETURN COALESCE(user_role, 'staff');
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Check if user is admin
CREATE OR REPLACE FUNCTION is_admin()
RETURNS BOOLEAN AS $$
BEGIN
    RETURN get_user_role() = 'admin';
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Check if user is admin or manager
CREATE OR REPLACE FUNCTION is_admin_or_manager()
RETURNS BOOLEAN AS $$
BEGIN
    RETURN get_user_role() IN ('admin', 'manager');
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ============================================================
-- ROW LEVEL SECURITY (RLS) POLICIES
-- ============================================================

-- Enable RLS on all tables
ALTER TABLE user_roles ENABLE ROW LEVEL SECURITY;
ALTER TABLE categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
ALTER TABLE stock_movements ENABLE ROW LEVEL SECURITY;
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE order_items ENABLE ROW LEVEL SECURITY;

-- User roles policies
CREATE POLICY "Users can view their own role" ON user_roles FOR SELECT USING (user_id = auth.uid());
CREATE POLICY "Admins can manage roles" ON user_roles FOR ALL USING (is_admin());

-- Categories policies (everyone can view, admin/manager can modify)
CREATE POLICY "Anyone can view categories" ON categories FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Admin/manager can manage categories" ON categories FOR INSERT WITH CHECK (is_admin_or_manager());
CREATE POLICY "Admin/manager can update categories" ON categories FOR UPDATE USING (is_admin_or_manager());
CREATE POLICY "Admin/manager can delete categories" ON categories FOR DELETE USING (is_admin_or_manager());

-- Products policies (everyone can view, admin/manager can modify)
CREATE POLICY "Anyone can view products" ON products FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Admin/manager can create products" ON products FOR INSERT WITH CHECK (is_admin_or_manager());
CREATE POLICY "Admin/manager can update products" ON products FOR UPDATE USING (is_admin_or_manager());
CREATE POLICY "Admin/manager can delete products" ON products FOR DELETE USING (is_admin_or_manager());

-- Stock movements policies (everyone can view and create)
CREATE POLICY "Anyone can view stock movements" ON stock_movements FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Anyone can create stock movements" ON stock_movements FOR INSERT WITH CHECK (auth.uid() IS NOT NULL);

-- Orders policies (everyone can view and manage)
CREATE POLICY "Anyone can view orders" ON orders FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Anyone can create orders" ON orders FOR INSERT WITH CHECK (auth.uid() IS NOT NULL);
CREATE POLICY "Anyone can update orders" ON orders FOR UPDATE USING (auth.uid() IS NOT NULL);

-- Order items policies
CREATE POLICY "Anyone can view order items" ON order_items FOR SELECT USING (auth.uid() IS NOT NULL);
CREATE POLICY "Anyone can manage order items" ON order_items FOR INSERT WITH CHECK (auth.uid() IS NOT NULL);
CREATE POLICY "Anyone can update order items" ON order_items FOR UPDATE USING (auth.uid() IS NOT NULL);
CREATE POLICY "Anyone can delete order items" ON order_items FOR DELETE USING (auth.uid() IS NOT NULL);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Categories
INSERT INTO categories (name) VALUES
    ('Audio'),
    ('Cables & Adapters'),
    ('Displays'),
    ('Keyboards'),
    ('Mice & Peripherals'),
    ('Power & Charging');

-- Get category IDs for products
DO $$
DECLARE
    cat_audio UUID;
    cat_cables UUID;
    cat_displays UUID;
    cat_keyboards UUID;
    cat_mice UUID;
    cat_power UUID;
BEGIN
    SELECT id INTO cat_audio FROM categories WHERE name = 'Audio';
    SELECT id INTO cat_cables FROM categories WHERE name = 'Cables & Adapters';
    SELECT id INTO cat_displays FROM categories WHERE name = 'Displays';
    SELECT id INTO cat_keyboards FROM categories WHERE name = 'Keyboards';
    SELECT id INTO cat_mice FROM categories WHERE name = 'Mice & Peripherals';
    SELECT id INTO cat_power FROM categories WHERE name = 'Power & Charging';

    -- Products
    INSERT INTO products (name, sku, description, price, category_id, status, stock_quantity, reorder_threshold, supplier) VALUES
    ('Wireless Earbuds Pro', 'SKU-1923', 'Bluetooth 5.3 with ANC, 28h battery', 129.00, cat_audio, 'active', 5, 15, 'SoundTech GmbH'),
    ('Studio Monitor Headphones', 'SKU-1940', 'Over-ear, flat frequency response, 3m cable', 199.00, cat_audio, 'active', 22, 10, 'SoundTech GmbH'),
    ('Portable Bluetooth Speaker', 'SKU-1955', 'IPX7 waterproof, 12h battery, USB-C', 79.00, cat_audio, 'active', 38, 10, 'SoundTech GmbH'),
    ('USB-C Hub Pro 7-in-1', 'SKU-2847', 'HDMI 4K, SD, microSD, 3x USB-A, PD 100W', 79.00, cat_cables, 'active', 2, 10, 'CableWorks AB'),
    ('Thunderbolt 4 Cable 1m', 'SKU-2860', '40Gbps, 100W PD, USB4 compatible', 45.00, cat_cables, 'active', 64, 20, 'CableWorks AB'),
    ('HDMI 2.1 Cable 2m', 'SKU-2871', '48Gbps, 8K/60Hz, eARC support', 24.00, cat_cables, 'active', 120, 30, 'CableWorks AB'),
    ('4K Monitor 27"', 'SKU-3201', 'IPS, 144Hz, USB-C, height adjustable', 349.00, cat_displays, 'active', 42, 8, 'DisplayPro Oy'),
    ('Ultrawide 34" Curved', 'SKU-3215', 'WQHD, 165Hz, 1ms, HDR400', 549.00, cat_displays, 'active', 11, 5, 'DisplayPro Oy'),
    ('Portable Monitor 15.6"', 'SKU-3228', 'FHD, USB-C, 590g, cover stand', 219.00, cat_displays, 'active', 0, 10, 'DisplayPro Oy'),
    ('Mechanical Keyboard 75%', 'SKU-0412', 'Hot-swappable, RGB, Gateron Brown', 159.00, cat_keyboards, 'active', 8, 20, 'KeyCraft Ltd'),
    ('Wireless Split Keyboard', 'SKU-0430', 'Ergonomic, Bluetooth + 2.4GHz, tenting', 229.00, cat_keyboards, 'active', 15, 8, 'KeyCraft Ltd'),
    ('Compact 60% Keyboard', 'SKU-0445', 'Cherry MX Red, PBT keycaps, USB-C', 119.00, cat_keyboards, 'archived', 0, 10, 'KeyCraft Ltd'),
    ('Ergonomic Vertical Mouse', 'SKU-5501', 'Wireless, 6 buttons, DPI 800-2400', 69.00, cat_mice, 'active', 35, 15, 'PeripheralPlus'),
    ('Gaming Mouse Lightweight', 'SKU-5520', '58g, PAW3395, wireless, 200h battery', 99.00, cat_mice, 'active', 27, 10, 'PeripheralPlus'),
    ('XL Desk Mat 900x400', 'SKU-5550', 'Stitched edge, non-slip rubber, machine washable', 29.00, cat_mice, 'active', 85, 20, 'PeripheralPlus'),
    ('Power Bank 20000mAh', 'SKU-7802', '65W PD, USB-C + USB-A, LED display', 45.00, cat_power, 'active', 19, 15, 'ChargeCo'),
    ('GaN Charger 65W', 'SKU-7820', '2x USB-C, foldable prongs, compact', 39.00, cat_power, 'active', 52, 20, 'ChargeCo'),
    ('Wireless Charging Pad', 'SKU-7835', '15W Qi2, MagSafe compatible, LED indicator', 35.00, cat_power, 'active', 41, 15, 'ChargeCo');

    -- Sample Orders
    INSERT INTO orders (customer_name, status, total_amount, notes, created_at) VALUES
    ('Kesko Oyj', 'confirmed', 1152.00, 'Bulk order for Q1 restocking', now() - interval '1 day'),
    ('S-Group', 'fulfilled', 890.00, '', now() - interval '2 days'),
    ('Tokmanni', 'draft', 2136.00, 'Awaiting stock confirmation for monitors', now() - interval '2 days'),
    ('Clas Ohlson', 'fulfilled', 560.00, 'Shipped via DHL Express', now() - interval '3 days'),
    ('Verkkokauppa.com', 'cancelled', 430.00, 'Customer cancelled', now() - interval '4 days');
END $$;

-- ============================================================
-- IMPORTANT: After running this, set yourself as admin:
--
-- INSERT INTO user_roles (user_id, role)
-- SELECT id, 'admin' FROM auth.users WHERE email = 'your-email@gmail.com'
-- ON CONFLICT (user_id) DO UPDATE SET role = 'admin';
-- ============================================================