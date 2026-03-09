import { useState, useEffect } from 'react';
import './App.css';
import Login from './components/Login';
import ProductList from './components/ProductList';
import ProductForm from './components/ProductForm';
import OrderList from './components/OrderList';
import OrderForm from './components/OrderForm';
import StockMovements from './components/StockMovements';
import AIPanel from './components/AIPanel';
import Dashboard from './components/Dashboard';
import { api } from './services/api';

/**
 * App — Main application with auth handling and tab navigation
 *
 * Auth flow:
 * 1. After Google OAuth, Supabase redirects back with #access_token=... in the URL
 * 2. This component reads the hash, stores the token, and clears the URL
 * 3. It then fetches the user info to confirm the token is valid
 * 4. The token is stored in localStorage so it persists across page refreshes
 */

const TABS = [
  { id: 'products', label: 'Products', requiresAuth: false },
  { id: 'new-product', label: '+ Product', requiresAuth: true },
  { id: 'orders', label: 'Orders', requiresAuth: true },
  { id: 'new-order', label: '+ Order', requiresAuth: true },
  { id: 'stock', label: 'Stock', requiresAuth: true },
  { id: 'ai', label: 'AI', requiresAuth: true },
  { id: 'dashboard', label: 'Dashboard', requiresAuth: true },
];

function App() {
  const [activeTab, setActiveTab] = useState('products');
  const [user, setUser] = useState(null);
  const [authLoading, setAuthLoading] = useState(true);

  useEffect(() => {
    // Step 1: Check if Supabase redirected back with a token in the URL hash
    // The hash looks like: #access_token=eyJ...&token_type=bearer&expires_in=3600&...
    const hash = window.location.hash;
    if (hash && hash.includes('access_token')) {
      const params = new URLSearchParams(hash.substring(1)); // remove the '#'
      const token = params.get('access_token');
      if (token) {
        localStorage.setItem('supabase_token', token);
        // Clean the URL so the token isn't visible in the address bar
        window.history.replaceState(null, '', window.location.pathname);
      }
    }

    // Step 2: If we have a stored token, try to fetch the user info
    const token = localStorage.getItem('supabase_token');
    if (token) {
      api.getUser()
        .then((data) => setUser(data.user))
        .catch(() => {
          // Token is invalid or expired — clear it
          localStorage.removeItem('supabase_token');
        })
        .finally(() => setAuthLoading(false));
    } else {
      setAuthLoading(false);
    }
  }, []);

  const handleLogout = () => {
    localStorage.removeItem('supabase_token');
    setUser(null);
    setActiveTab('products');
  };

  // Show loading while checking auth
  if (authLoading) {
    return <p>Loading...</p>;
  }

  // Filter tabs based on auth state
  const visibleTabs = TABS.filter((tab) => !tab.requiresAuth || user);

  return (
    <div>
      {/* Header with auth controls */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
        <h1 style={{ margin: 0 }}>StockFlow</h1>
        <div>
          {user ? (
            <span>
              {user.email}{' '}
              <button onClick={handleLogout} style={{ marginLeft: '10px' }}>
                Sign out
              </button>
            </span>
          ) : (
            <Login />
          )}
        </div>
      </div>

      {/* Tab navigation */}
      <nav style={{ display: 'flex', gap: '5px', marginBottom: '20px', flexWrap: 'wrap' }}>
        {visibleTabs.map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            style={{
              fontWeight: activeTab === tab.id ? 'bold' : 'normal',
              borderColor: activeTab === tab.id ? '#646cff' : 'transparent',
            }}
          >
            {tab.label}
          </button>
        ))}
      </nav>

      {/* Tab content */}
      {activeTab === 'products' && <ProductList />}
      {activeTab === 'new-product' && <ProductForm onSaved={() => setActiveTab('products')} />}
      {activeTab === 'orders' && <OrderList />}
      {activeTab === 'new-order' && <OrderForm onCreated={() => setActiveTab('orders')} />}
      {activeTab === 'stock' && <StockMovements />}
      {activeTab === 'ai' && <AIPanel />}
      {activeTab === 'dashboard' && <Dashboard />}
    </div>
  );
}

export default App;
