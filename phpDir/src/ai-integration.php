<?php
/**
 * Lesson 12: AI Integration with Google Gemini
 *
 * A simple demonstration of calling the Gemini API from PHP.
 */

require_once 'ai/GeminiAI.php';

$error = null;
$recommendations = null;
$genre = '';
$configError = null;

// Initialize Gemini
$ai = null;
try {
    $ai = new GeminiAI();
} catch (Exception $e) {
    $configError = $e->getMessage();
}

// Handle form submission
if ($ai && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['genre'])) {
    $genre = trim($_POST['genre']);

    try {
        // Create a specific prompt for book recommendations
        $prompt = "Write me a small story about the topic {$genre}";

        $recommendations = $ai->ask($prompt);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson 12: AI Integration</title>
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
            border-bottom: 3px solid #9b59b6;
            padding-bottom: 10px;
        }
        .nav-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #9b59b6;
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
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            background: #9b59b6;
            color: white;
        }
        .btn:hover {
            background: #8e44ad;
        }
        .response {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #9b59b6;
            white-space: pre-wrap;
        }
        code {
            background: #ecf0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .genre-examples {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .genre-example {
            background: #ecf0f1;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 14px;
            color: #7f8c8d;
            cursor: pointer;
        }
        .genre-example:hover {
            background: #9b59b6;
            color: white;
        }
    </style>
</head>
<body>
    <a href="home.php" class="nav-link">← Back to Home</a>

    <h1>Lesson 12: AI Integration</h1>

    <?php if ($configError): ?>
        <div class="card">
            <div class="error">
                <strong>Setup Required:</strong> <?= htmlspecialchars($configError) ?>
            </div>
            <p>To get your Gemini API key:</p>
            <ol>
                <li>Go to <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a></li>
                <li>Click "Create API Key"</li>
                <li>Add the key to your <code>.env</code> file as <code>GEMINI_API_KEY</code></li>
            </ol>
        </div>

    <?php else: ?>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Book Recommendations</h2>
            <p>Enter a genre and get 3 book recommendations from AI:</p>

            <form method="POST">
                <div class="form-group">
                    <label for="genre">Genre</label>
                    <input type="text" id="genre" name="genre" required
                           placeholder="Enter a genre..."
                           value="<?= htmlspecialchars($genre) ?>">
                    <div class="genre-examples">
                        <span class="genre-example" onclick="document.getElementById('genre').value='Science Fiction'">Science Fiction</span>
                        <span class="genre-example" onclick="document.getElementById('genre').value='Mystery'">Mystery</span>
                        <span class="genre-example" onclick="document.getElementById('genre').value='Fantasy'">Fantasy</span>
                        <span class="genre-example" onclick="document.getElementById('genre').value='Historical Fiction'">Historical Fiction</span>
                    </div>
                </div>
                <button type="submit" class="btn">Get Recommendations</button>
            </form>
        </div>

        <?php if ($recommendations): ?>
            <div class="card">
                <h2>AI Recommendations</h2>
                <div class="response"><?= nl2br(htmlspecialchars($recommendations)) ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>