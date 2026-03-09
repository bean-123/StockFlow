import { useState } from 'react';
import { api } from '../services/api';

/**
 * Login — Google OAuth via Supabase
 *
 * The login flow:
 * 1. User clicks "Sign in with Google"
 * 2. Frontend asks the PHP backend for the OAuth URL (GET /api/auth/login-url)
 * 3. Browser redirects to Google → Supabase → back to our app
 * 4. Supabase puts the access_token in the URL hash (#access_token=...)
 * 5. App.jsx reads the hash and stores the token in localStorage
 * 6. All subsequent API calls include the token in the Authorization header
 */
export default function Login() {
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    setLoading(true);
    try {
      const data = await api.getLoginUrl();
      window.location.href = data.url;
    } catch (err) {
      alert('Login failed: ' + err.message);
      setLoading(false);
    }
  };

  return (
    <button onClick={handleLogin} disabled={loading}>
      {loading ? 'Redirecting...' : 'Sign in with Google'}
    </button>
  );
}
