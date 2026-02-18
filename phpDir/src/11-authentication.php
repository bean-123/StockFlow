<?php
/**
 * Lesson 11: Supabase Authentication Demo
 *
 * A simple demonstration of Google OAuth with Supabase and PHP.
 */

require_once 'auth/SupabaseAuth.php';

$auth = null;
$error = null;
$success = null;
$notes = [];
$user = null;
$configError = null;

// Initialize Supabase connection
try {
    $auth = new SupabaseAuth();
    $user = $auth->getCurrentUser();
} catch (Exception $e) {
    $configError = $e->getMessage();
}

// Handle form actions
if ($auth && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'logout') {
            $auth->logout();
            header('Location: 11-authentication.php');
            exit;
        }

        if (isset($_POST['action']) && $_POST['action'] === 'add_note' && $auth->isLoggedIn()) {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (empty($title)) {
                $error = "Title is required";
            } else {
                $auth->insert('user_notes', [
                    'user_id' => $user['id'],
                    'title' => $title,
                    'content' => $content
                ]);
                $success = "Note added!";
            }
        }

        if (isset($_POST['action']) && $_POST['action'] === 'delete_note' && $auth->isLoggedIn()) {
            $noteId = (int)$_POST['note_id'];
            $auth->delete('user_notes', 'id=eq.' . $noteId);
            $success = "Note deleted!";
        }

        $user = $auth->getCurrentUser();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Load user's notes
if ($auth && $auth->isLoggedIn()) {
    try {
        $notes = $auth->query('user_notes', ['order' => 'created_at.desc']);
        if (!is_array($notes)) {
            $notes = [];
        }
    } catch (Exception $e) {
        $error = "Failed to load notes: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson 11: Supabase Authentication</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
            background: #f8f9fa;
            color: #333;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .nav-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .error {
            background: #fdeaea;
            color: #c0392b;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .success {
            background: #e8f8f5;
            color: #1e8449;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
        }
        .btn-google {
            background: #4285f4;
            color: white;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            font-size: 14px;
        }
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #e8f8f5;
            border-radius: 8px;
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .note {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 4px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .note-content { flex: 1; }
        .note h3 { margin: 0 0 5px 0; color: #2c3e50; }
        .note p { margin: 0; color: #7f8c8d; }
        .note small { color: #bdc3c7; font-size: 12px; }
        .login-container { text-align: center; padding: 40px 20px; }
        code {
            background: #ecf0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <a href="home.php" class="nav-link">← Back to Home</a>

    <h1>Lesson 11: Supabase Authentication</h1>

    <?php if ($configError): ?>
        <div class="card">
            <div class="error">
                <strong>Setup Required:</strong> <?= htmlspecialchars($configError) ?>
            </div>
            <p>To complete setup:</p>
            <ol>
                <li>Copy <code>.env.example</code> to <code>.env</code></li>
                <li>Add your Supabase URL and anon key</li>
                <li>Run <code>docs/database_setup.sql</code> in Supabase</li>
                <li>Enable Google Auth in Supabase Dashboard</li>
            </ol>
        </div>

    <?php elseif (!$auth->isLoggedIn()): ?>
        <div class="card login-container">
            <h2>Sign In</h2>
            <p>Click below to sign in with your Google account:</p>
            <a href="<?= htmlspecialchars($auth->getGoogleSignInUrl()) ?>" class="btn btn-google">
                Sign in with Google
            </a>
        </div>

    <?php else: ?>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- User Info -->
        <div class="card">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (!empty($user['user_metadata']['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($user['user_metadata']['avatar_url']) ?>" alt="Avatar">
                    <?php endif; ?>
                </div>
                <div>
                    <strong><?= htmlspecialchars($user['user_metadata']['full_name'] ?? 'User') ?></strong><br>
                    <small><?= htmlspecialchars($user['email'] ?? '') ?></small>
                </div>
                <form method="POST" style="margin-left: auto;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-secondary">Sign Out</button>
                </form>
            </div>
        </div>

        <!-- Add Note -->
        <div class="card">
            <h2>Add a Note</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_note">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required placeholder="Enter note title">
                </div>
                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" placeholder="Enter note content (optional)"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Add Note</button>
            </form>
        </div>

        <!-- Notes List -->
        <div class="card">
            <h2>Your Notes</h2>
            <?php if (empty($notes)): ?>
                <p style="color: #7f8c8d;">No notes yet. Add your first note above!</p>
            <?php else: ?>
                <?php foreach ($notes as $note): ?>
                    <div class="note">
                        <div class="note-content">
                            <h3><?= htmlspecialchars($note['title']) ?></h3>
                            <?php if (!empty($note['content'])): ?>
                                <p><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                            <?php endif; ?>
                            <small><?= date('M j, Y g:i A', strtotime($note['created_at'])) ?></small>
                        </div>
                        <form method="POST" onsubmit="return confirm('Delete this note?');">
                            <input type="hidden" name="action" value="delete_note">
                            <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>