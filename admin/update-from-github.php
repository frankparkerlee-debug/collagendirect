<!DOCTYPE html>
<html>
<head>
    <title>Update from GitHub</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 800px; }
        pre { background: #000; color: #0f0; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .success { color: #0a0; }
        .error { color: #f00; }
        .info { color: #00f; }
        button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
    </style>
</head>
<body>
<div class="container">
    <h1>Update from GitHub</h1>

<?php
if (isset($_POST['update'])) {
    echo "<pre>";

    // Change to repository root
    $repoRoot = dirname(__DIR__);
    chdir($repoRoot);

    echo "<span class='info'>Current directory: " . getcwd() . "</span>\n";
    echo "<span class='info'>Checking for .git directory...</span>\n";

    if (!is_dir('.git')) {
        echo "<span class='error'>ERROR: .git directory not found!</span>\n";
        echo "<span class='error'>This doesn't appear to be a git repository.</span>\n";
    } else {
        echo "<span class='success'>✓ Git repository found</span>\n\n";

        echo "<span class='info'>Executing: git pull origin main</span>\n";
        echo str_repeat('-', 60) . "\n";

        $output = array();
        $returnCode = 0;
        exec('git pull origin main 2>&1', $output, $returnCode);

        foreach ($output as $line) {
            if (strpos($line, 'error') !== false || strpos($line, 'fatal') !== false) {
                echo "<span class='error'>" . htmlspecialchars($line) . "</span>\n";
            } elseif (strpos($line, 'Already up to date') !== false || strpos($line, 'Fast-forward') !== false) {
                echo "<span class='success'>" . htmlspecialchars($line) . "</span>\n";
            } else {
                echo htmlspecialchars($line) . "\n";
            }
        }

        echo str_repeat('-', 60) . "\n";

        if ($returnCode === 0) {
            echo "\n<span class='success'>✓ SUCCESS: Code updated from GitHub</span>\n";

            // Clear opcache
            if (function_exists('opcache_reset')) {
                opcache_reset();
                echo "<span class='success'>✓ OPCache cleared</span>\n";
            }

            echo "\n<span class='info'>Deployment completed at " . date('Y-m-d H:i:s') . "</span>\n";
        } else {
            echo "\n<span class='error'>✗ FAILED: Git pull returned error code " . $returnCode . "</span>\n";
        }
    }

    echo "</pre>";
    echo "<p><a href='update-from-github.php'>← Back</a></p>";

} else {
    ?>
    <p>This will pull the latest code from the GitHub repository.</p>
    <form method="POST">
        <button type="submit" name="update" value="1">Update from GitHub Now</button>
    </form>
    <p style="color: #666; font-size: 12px;">
        Note: This will execute <code>git pull origin main</code> in the repository root.
    </p>
    <?php
}
?>

</div>
</body>
</html>
