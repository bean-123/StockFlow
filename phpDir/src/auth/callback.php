<?php
/**
 * OAuth Callback Handler
 *
 * This page handles the redirect from Supabase after Google authentication.
 *
 * HOW IT WORKS:
 * 1. User clicks "Sign in with Google"
 * 2. Browser goes to Google → user logs in
 * 3. Google redirects to Supabase
 * 4. Supabase redirects HERE with tokens in the URL fragment (#)
 * 5. JavaScript captures the fragment (PHP can't see it)
 * 6. JavaScript sends tokens to this page via form POST
 * 7. PHP stores tokens in session and redirects to the app
 *
 * WHY URL FRAGMENTS?
 * Supabase uses the fragment (#) for tokens because:
 * - Fragments are NOT sent to the server in HTTP requests
 * - This prevents tokens from appearing in server logs
 * - It's a security best practice for OAuth
 */

session_start();

// If we received tokens via POST (from our JavaScript)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_token'])) {
    // Include the auth class
    require_once 'SupabaseAuth.php';

    try {
        $auth = new SupabaseAuth();

        // Handle the callback with the received tokens
        $user = $auth->handleCallback(
            $_POST['access_token'],
            $_POST['refresh_token'] ?? null
        );

        if ($user) {
            // Success! Redirect to the main app
            header('Location: ../11-authentication.php');
            exit;
        } else {
            $error = "Failed to get user information";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check for error in URL params (Supabase sends errors this way)
$error = $_GET['error_description'] ?? $_GET['error'] ?? $error ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Authenticating...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .error {
            color: #e74c3c;
            background: #fdeaea;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        a {
            color: #3498db;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
            <h2>Authentication Error</h2>
            <div class="error"><?= htmlspecialchars($error) ?></div>
            <p><a href="../11-authentication.php">Back to Login</a></p>
        <?php else: ?>
            <div class="spinner"></div>
            <h2>Completing sign in...</h2>
            <p>Please wait while we finish authenticating you.</p>
            <noscript>
                <p class="error">JavaScript is required for authentication.</p>
            </noscript>
        <?php endif; ?>
    </div>

    <?php if (!$error): ?>
    <script>
        /**
         * Extract tokens from URL fragment and submit to PHP
         *
         * The URL looks like:
         * callback.php#access_token=xxx&refresh_token=yyy&expires_in=3600&token_type=bearer
         *
         * We need to:
         * 1. Parse the fragment
         * 2. Extract the tokens
         * 3. Send them to PHP via form POST
         */
        (function() {
            // Get the URL fragment (everything after #)
            const hash = window.location.hash.substring(1);

            if (!hash) {
                // No fragment - might be an error or direct access
                console.log('No hash fragment found');
                document.querySelector('.container').innerHTML =
                    '<h2>Authentication Error</h2>' +
                    '<p class="error">No authentication data received.</p>' +
                    '<p><a href="../11-authentication.php">Back to Login</a></p>';
                return;
            }

            // Parse the fragment into key-value pairs
            const params = new URLSearchParams(hash);
            const accessToken = params.get('access_token');
            const refreshToken = params.get('refresh_token');

            if (!accessToken) {
                // Check for error in fragment
                const error = params.get('error_description') || params.get('error');
                document.querySelector('.container').innerHTML =
                    '<h2>Authentication Error</h2>' +
                    '<p class="error">' + (error || 'No access token received') + '</p>' +
                    '<p><a href="../11-authentication.php">Back to Login</a></p>';
                return;
            }

            // Create a form to POST the tokens to PHP
            // (This keeps tokens out of the URL/server logs)
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'callback.php';

            // Add access token
            const accessInput = document.createElement('input');
            accessInput.type = 'hidden';
            accessInput.name = 'access_token';
            accessInput.value = accessToken;
            form.appendChild(accessInput);

            // Add refresh token
            if (refreshToken) {
                const refreshInput = document.createElement('input');
                refreshInput.type = 'hidden';
                refreshInput.name = 'refresh_token';
                refreshInput.value = refreshToken;
                form.appendChild(refreshInput);
            }

            // Submit the form
            document.body.appendChild(form);
            form.submit();
        })();
    </script>
    <?php endif; ?>
</body>
</html>