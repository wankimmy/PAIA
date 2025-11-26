<?php
/**
 * Laravel Setup Script for Shared Hosting (No SSH Access)
 * 
 * SECURITY WARNING: Delete this file after setup is complete!
 * 
 * Usage:
 * 1. Upload this file to your server
 * 2. Access via: https://kawan.safwanhakim.com/api/setup.php
 * 3. Enter the setup password (set below)
 * 4. Run the necessary commands
 * 5. DELETE THIS FILE after setup is complete!
 */

// ============================================
// SECURITY CONFIGURATION
// ============================================
// Set a strong password here before uploading
define('SETUP_PASSWORD', 'Kimi5527@@');

// Optional: Restrict access by IP (leave empty to allow all IPs)
// Example: define('ALLOWED_IPS', ['123.456.789.0', '98.76.54.32']);
define('ALLOWED_IPS', []);

// ============================================
// DO NOT MODIFY BELOW THIS LINE
// ============================================


// Check IP restriction
if (!empty(ALLOWED_IPS) && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ALLOWED_IPS)) {
    die('❌ Access denied from this IP address.');
}

// Check authentication
session_start();
$authenticated = isset($_SESSION['setup_authenticated']) && $_SESSION['setup_authenticated'] === true;

if (isset($_POST['password'])) {
    if ($_POST['password'] === SETUP_PASSWORD) {
        $_SESSION['setup_authenticated'] = true;
        $authenticated = true;
    } else {
        $error = 'Invalid password!';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: setup.php');
    exit;
}

if (!$authenticated) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Laravel Setup - Authentication</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; }
            .form-group { margin-bottom: 15px; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input[type="password"] { width: 100%; padding: 10px; font-size: 14px; }
            button { background: #4f46e5; color: white; padding: 10px 20px; border: none; cursor: pointer; }
            button:hover { background: #4338ca; }
            .error { color: red; margin-top: 10px; }
            .warning { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <h1>🔐 Laravel Setup Authentication</h1>
        <div class="warning">
            <strong>⚠️ Security Notice:</strong> This is a setup script. Delete this file after completing setup!
        </div>
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="password">Setup Password:</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            <button type="submit">Authenticate</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Bootstrap Laravel
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Handle command execution
$output = '';
$command = $_GET['cmd'] ?? '';
$success = false;

if ($command && in_array($command, [
    'key:generate',
    'migrate',
    'migrate:fresh',
    'config:clear',
    'route:clear',
    'cache:clear',
    'view:clear',
    'config:cache',
    'route:cache',
    'optimize',
    'check',
])) {
    try {
        // For optimize command, ensure resources/views directory exists (API-only apps may not have it)
        if ($command === 'optimize') {
            $resourcesViewsPath = __DIR__ . '/../resources/views';
            if (!is_dir($resourcesViewsPath)) {
                @mkdir($resourcesViewsPath, 0755, true);
                $output = "Created resources/views directory (required for optimize command)\n\n";
            } else {
                $output = '';
            }
        } else {
            $output = '';
        }
        
        ob_start();
        $exitCode = Artisan::call($command);
        $output .= ob_get_clean();
        $output .= "\n" . Artisan::output();
        $success = $exitCode === 0;
    } catch (\Exception $e) {
        $output = 'Error: ' . $e->getMessage();
        $success = false;
    }
}

// Get system information
$phpVersion = PHP_VERSION;
$laravelVersion = app()->version();
$envFileExists = file_exists(__DIR__ . '/../.env');
$storageWritable = is_writable(__DIR__ . '/../storage');
$bootstrapCacheWritable = is_writable(__DIR__ . '/../bootstrap/cache');
$databaseExists = false;
$databasePath = '';

if (config('database.default') === 'sqlite') {
    $databasePath = config('database.connections.sqlite.database');
    $databaseExists = file_exists($databasePath);
    $databaseWritable = $databaseExists ? is_writable($databasePath) : is_writable(dirname($databasePath));
} else {
    try {
        DB::connection()->getPdo();
        $databaseExists = true;
        $databaseWritable = true;
    } catch (\Exception $e) {
        $databaseExists = false;
        $databaseWritable = false;
        $databaseError = $e->getMessage();
    }
}

$appKey = config('app.key');
$appKeyExists = !empty($appKey) && $appKey !== 'base64:';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Laravel Setup - Control Panel</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            max-width: 900px; 
            margin: 20px auto; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-top: 0; }
        h2 { color: #4f46e5; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 4px; font-weight: bold; margin-left: 10px; }
        .status.ok { background: #d1fae5; color: #065f46; }
        .status.error { background: #fee2e2; color: #991b1b; }
        .status.warning { background: #fef3c7; color: #92400e; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
        .info-item { padding: 15px; background: #f9fafb; border-radius: 5px; }
        .info-label { font-weight: bold; color: #6b7280; font-size: 12px; text-transform: uppercase; }
        .info-value { font-size: 16px; color: #111827; margin-top: 5px; }
        .commands { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 20px 0; }
        .cmd-btn { 
            background: #4f46e5; 
            color: white; 
            padding: 12px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: background 0.2s;
        }
        .cmd-btn:hover { background: #4338ca; }
        .cmd-btn.danger { background: #dc2626; }
        .cmd-btn.danger:hover { background: #b91c1c; }
        .output { 
            background: #1f2937; 
            color: #f9fafb; 
            padding: 15px; 
            border-radius: 5px; 
            font-family: 'Courier New', monospace; 
            font-size: 13px; 
            white-space: pre-wrap; 
            max-height: 400px; 
            overflow-y: auto;
            margin-top: 20px;
        }
        .output.success { border-left: 4px solid #10b981; }
        .output.error { border-left: 4px solid #ef4444; }
        .warning-box { 
            background: #fef3c7; 
            border: 1px solid #f59e0b; 
            padding: 15px; 
            margin: 20px 0; 
            border-radius: 5px; 
        }
        .section { margin-bottom: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Laravel Setup Control Panel <a href="?logout=1" style="float: right; color: #6b7280; text-decoration: none;">Logout</a></h1>
        
        <div class="warning-box">
            <strong>⚠️ SECURITY WARNING:</strong> Delete this file (setup.php) after completing setup! 
            This script provides administrative access to your Laravel application.
        </div>

        <!-- System Information -->
        <div class="section">
            <h2>System Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">PHP Version</div>
                    <div class="info-value"><?= htmlspecialchars($phpVersion) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Laravel Version</div>
                    <div class="info-value"><?= htmlspecialchars($laravelVersion) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Environment File</div>
                    <div class="info-value">
                        <?= $envFileExists ? '<span class="status ok">✓ Exists</span>' : '<span class="status error">✗ Missing</span>' ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Application Key</div>
                    <div class="info-value">
                        <?= $appKeyExists ? '<span class="status ok">✓ Set</span>' : '<span class="status error">✗ Not Set</span>' ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Storage Writable</div>
                    <div class="info-value">
                        <?= $storageWritable ? '<span class="status ok">✓ Yes</span>' : '<span class="status error">✗ No</span>' ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Bootstrap Cache Writable</div>
                    <div class="info-value">
                        <?= $bootstrapCacheWritable ? '<span class="status ok">✓ Yes</span>' : '<span class="status error">✗ No</span>' ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Database</div>
                    <div class="info-value">
                        <?php if ($databaseExists): ?>
                            <span class="status ok">✓ Connected</span>
                        <?php else: ?>
                            <span class="status error">✗ Not Connected</span>
                            <?php if (isset($databaseError)): ?>
                                <br><small style="color: #991b1b;"><?= htmlspecialchars($databaseError) ?></small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (config('database.default') === 'sqlite'): ?>
                <div class="info-item">
                    <div class="info-label">SQLite Database</div>
                    <div class="info-value">
                        <?php if ($databaseExists): ?>
                            <span class="status ok">✓ Exists</span>
                        <?php else: ?>
                            <span class="status warning">⚠ Not Created</span>
                        <?php endif; ?>
                        <br><small style="color: #6b7280;"><?= htmlspecialchars($databasePath) ?></small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Setup Commands -->
        <div class="section">
            <h2>Setup Commands</h2>
            <div class="commands">
                <?php if (!$appKeyExists): ?>
                    <a href="?cmd=key:generate" class="cmd-btn">Generate App Key</a>
                <?php endif; ?>
                <a href="?cmd=migrate" class="cmd-btn">Run Migrations</a>
                <a href="?cmd=config:clear" class="cmd-btn">Clear Config</a>
                <a href="?cmd=route:clear" class="cmd-btn">Clear Routes</a>
                <a href="?cmd=cache:clear" class="cmd-btn">Clear Cache</a>
                <a href="?cmd=view:clear" class="cmd-btn">Clear Views</a>
                <a href="?cmd=optimize" class="cmd-btn">Optimize</a>
                <a href="?cmd=check" class="cmd-btn">Check System</a>
            </div>
        </div>

        <!-- Maintenance Commands -->
        <div class="section">
            <h2>Maintenance Commands</h2>
            <div class="commands">
                <a href="?cmd=config:cache" class="cmd-btn">Cache Config</a>
                <a href="?cmd=route:cache" class="cmd-btn">Cache Routes</a>
                <a href="?cmd=migrate:fresh" class="cmd-btn danger" onclick="return confirm('⚠️ WARNING: This will drop all tables and re-run migrations. Are you sure?')">Fresh Migrations</a>
            </div>
        </div>

        <!-- Command Output -->
        <?php if ($command): ?>
            <div class="section">
                <h2>Command Output: <code><?= htmlspecialchars($command) ?></code></h2>
                <div class="output <?= $success ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($output ?: 'No output') ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Setup Guide -->
        <div class="section">
            <h2>Quick Setup Guide</h2>
            <ol>
                <li><strong>Generate App Key:</strong> Click "Generate App Key" if not set</li>
                <li><strong>Run Migrations:</strong> Click "Run Migrations" to create database tables</li>
                <li><strong>Clear Caches:</strong> Click "Clear Config", "Clear Routes", and "Clear Cache"</li>
                <li><strong>Optimize:</strong> Click "Optimize" for production performance</li>
                <li><strong>Delete this file:</strong> After setup, delete <code>setup.php</code> for security!</li>
            </ol>
        </div>
    </div>
</body>
</html>

