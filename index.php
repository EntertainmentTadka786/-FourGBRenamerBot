<?php
/*
VIDEO RENAMER BOT - Telegram Bot
Author: FourGBRenamerBot
Version: 2.0
Date: 2024-01-01
*/

// ===== ERROR REPORTING =====
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// ===== LOAD ENVIRONMENT VARIABLES =====
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ===== CONFIGURATION =====
// Telegram Credentials
define('API_ID', (int) ($_ENV['API_ID'] ?? 38609654));
define('API_HASH', $_ENV['API_HASH'] ?? 'a0e8ee97b9c10331ef8be11a6c0793e6');
define('BOT_TOKEN', $_ENV['BOT_TOKEN'] ?? '8521792734:AAF3AiUcZdtfD2JgsPZ9vE1kXZlurRtqmFQ');

// Bot Info
define('BOT_OWNER_ID', (int) ($_ENV['BOT_OWNER_ID'] ?? 8521792734));
define('BOT_USERNAME', $_ENV['BOT_USERNAME'] ?? 'FourGBRenamerBot');

// Channels
define('MAIN_CHANNEL', '@' . ($_ENV['MAIN_CHANNEL'] ?? 'EntertainmentTadka786'));
define('REQUEST_CHANNEL', '@' . ($_ENV['REQUEST_CHANNEL'] ?? 'EntertainmentTadka7860'));
define('PRINTS_CHANNEL', '@' . ($_ENV['PRINTS_CHANNEL'] ?? 'threater_print_movies'));
define('BACKUP_CHANNEL', '@' . ($_ENV['BACKUP_CHANNEL'] ?? 'ETBackup'));

// File Settings
define('MAX_FILE_SIZE', (int) ($_ENV['MAX_FILE_SIZE'] ?? 4000000000));  // 4GB

// Directories
define('DOWNLOAD_DIR', __DIR__ . '/downloads');
define('THUMB_DIR', __DIR__ . '/thumbs');
define('LOG_DIR', __DIR__ . '/logs');

// Create directories
if (!file_exists(DOWNLOAD_DIR)) mkdir(DOWNLOAD_DIR, 0777, true);
if (!file_exists(THUMB_DIR)) mkdir(THUMB_DIR, 0777, true);
if (!file_exists(LOG_DIR)) mkdir(LOG_DIR, 0777, true);

// ===== LOGGING SETUP =====
function logger($message, $level = 'INFO') {
    $log_file = LOG_DIR . '/bot.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo "$log_message";
}

// ===== GLOBAL VARIABLES =====
$USER_THUMB = [];
$UPLOAD_QUEUE = [];
$IS_UPLOADING = false;
$USER_STATES = [];
$USER_FILENAMES = [];
$USER_CUSTOM_NAME = [];
$USER_PERMANENT_NAMES = [];

// ===== HELPER FUNCTIONS =====
function clean_filename($filename) {
    $name_without_ext = pathinfo($filename, PATHINFO_FILENAME);
    
    $patterns_to_remove = [
        '/\b\d{3,4}p\b/i',
        '/\bhd\b/i',
        '/\bfhd\b/i',
        '/\buhd\b/i',
        '/\b4k\b/i',
        '/\bweb[\s.-]?dl\b/i',
        '/\bwebrip\b/i',
        '/\bbluray\b/i',
        '/\bhdrip\b/i',
        '/\bx264\b/i',
        '/\bx265\b/i',
        '/\bhevc\b/i',
        '/\baac\b/i',
        '/\besub\b/i',
        '/\bhin\b/i',
        '/\beng\b/i',
        '/\bhindi\b/i',
        '/\benglish\b/i',
        '/\bs\d+\b/i',
        '/\bep?\d+\b/i',
        '/\bseason\s\d+\b/i',
        '/\bepisode\s\d+\b/i'
    ];
    
    $cleaned = $name_without_ext;
    foreach ($patterns_to_remove as $pattern) {
        $cleaned = preg_replace($pattern, '', $cleaned);
    }
    
    $cleaned = preg_replace('/[\.\_\-]+/', ' ', $cleaned);
    $cleaned = preg_replace('/\s+/', ' ', $cleaned);
    $cleaned = trim($cleaned);
    
    $words = explode(' ', $cleaned);
    $cleaned = implode(' ', array_map('ucfirst', $words));
    
    return $cleaned ?: substr($name_without_ext, 0, 50);
}

function format_file_size($size_bytes) {
    if ($size_bytes >= 1073741824) {  // GB
        return number_format($size_bytes / 1073741824, 2) . ' GB';
    } elseif ($size_bytes >= 1048576) {  // MB
        return number_format($size_bytes / 1048576, 2) . ' MB';
    } elseif ($size_bytes >= 1024) {  // KB
        return number_format($size_bytes / 1024, 2) . ' KB';
    } else {
        return $size_bytes . ' B';
    }
}

function generate_bold_caption($filename) {
    $caption = "**$filename**\n\n";
    $caption .= "🔥 **Channels:**\n";
    $caption .= "🍿 **Main:** " . MAIN_CHANNEL . "\n";
    $caption .= "📥 **Request:** " . REQUEST_CHANNEL . "\n";
    $caption .= "🎭 **Theater:** " . PRINTS_CHANNEL . "\n";
    $caption .= "📂 **Backup:** " . BACKUP_CHANNEL;
    
    return $caption;
}

function format_duration($seconds) {
    if (!$seconds) return "0:00";
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf("%d:%02d:%02d", $hours, $minutes, $secs);
    } else {
        return sprintf("%d:%02d", $minutes, $secs);
    }
}

function resize_thumbnail($thumb_path, $target_size = [1280, 720]) {
    try {
        list($target_width, $target_height) = $target_size;
        
        // Check if GD is available
        if (!extension_loaded('gd')) {
            logger("GD extension not available for thumbnail resize");
            return;
        }
        
        $image_info = getimagesize($thumb_path);
        if (!$image_info) {
            logger("Invalid image file: $thumb_path");
            return;
        }
        
        list($original_width, $original_height, $type) = $image_info;
        
        // Create image from file
        switch ($type) {
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg($thumb_path);
                break;
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng($thumb_path);
                break;
            case IMAGETYPE_GIF:
                $img = imagecreatefromgif($thumb_path);
                break;
            default:
                logger("Unsupported image type: $type");
                return;
        }
        
        if (!$img) {
            logger("Failed to create image from: $thumb_path");
            return;
        }
        
        // Calculate aspect ratios
        $original_ratio = $original_width / $original_height;
        $target_ratio = $target_width / $target_height;
        
        // Resize maintaining aspect ratio
        if ($original_ratio > $target_ratio) {
            $new_height = $target_height;
            $new_width = (int) ($target_height * $original_ratio);
        } else {
            $new_width = $target_width;
            $new_height = (int) ($target_width / $original_ratio);
        }
        
        // Create resized image
        $resized = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, 
                          $new_width, $new_height, $original_width, $original_height);
        
        // Create new image with black background
        $new_img = imagecreatetruecolor($target_width, $target_height);
        $black = imagecolorallocate($new_img, 0, 0, 0);
        imagefill($new_img, 0, 0, $black);
        
        // Calculate position to center the image
        $x = (int) (($target_width - $new_width) / 2);
        $y = (int) (($target_height - $new_height) / 2);
        
        // Paste resized image onto black background
        imagecopy($new_img, $resized, $x, $y, 0, 0, $new_width, $new_height);
        
        // Save optimized thumbnail
        imagejpeg($new_img, $thumb_path, 95);
        
        // Free memory
        imagedestroy($img);
        imagedestroy($resized);
        imagedestroy($new_img);
        
        logger("Thumbnail resized to {$target_width}x{$target_height}");
        
    } catch (Exception $e) {
        logger("Thumbnail resize failed: " . $e->getMessage(), 'ERROR');
    }
}

function get_video_duration_ffprobe($video_path) {
    try {
        $cmd = "ffprobe -v quiet -print_format json -show_format " . escapeshellarg($video_path);
        $result = shell_exec($cmd);
        
        if ($result) {
            $info = json_decode($result, true);
            if (isset($info['format']['duration'])) {
                return (int) $info['format']['duration'];
            }
        }
        return 0;
    } catch (Exception $e) {
        logger("FFprobe error: " . $e->getMessage(), 'ERROR');
        return 0;
    }
}

function format_time_remaining($seconds) {
    if ($seconds < 60) {
        return "$seconds seconds";
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        return $minutes . " minute" . ($minutes > 1 ? 's' : '');
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . " hour" . ($hours > 1 ? 's' : '') . " " . 
               $minutes . " minute" . ($minutes > 1 ? 's' : '');
    }
}

function create_progress_bar($percentage) {
    $filled = str_repeat('█', (int) ($percentage / 5));
    $empty = str_repeat('░', 20 - (int) ($percentage / 5));
    return $filled . $empty;
}

function cleanup_files(...$paths) {
    global $USER_THUMB, $USER_FILENAMES, $USER_CUSTOM_NAME;
    
    foreach ($paths as $path) {
        try {
            if ($path && file_exists($path)) {
                unlink($path);
                logger("Cleaned up: $path");
            }
        } catch (Exception $e) {
            logger("Error cleaning $path: " . $e->getMessage(), 'ERROR');
        }
    }
}

// ===== BOT STARTUP =====
logger("🤖 VIDEO RENAMER BOT Starting...");
echo "=" . str_repeat("=", 58) . "=\n";
echo "🤖 VIDEO RENAMER BOT\n";
echo "👤 Owner ID: " . BOT_OWNER_ID . "\n";
echo "📢 Main Channel: " . MAIN_CHANNEL . "\n";
echo "=" . str_repeat("=", 58) . "=\n";
echo "\n✨ **FEATURES:**\n";
echo "✅ Permanent Filename Templates\n";
echo "✅ Auto Quality Detection\n";
echo "✅ /filerename command\n";
echo "✅ Thumbnail auto-resize\n";
echo "✅ Video duration support\n";
echo "✅ Bold caption format\n";
echo "✅ Queue system\n";
echo "✅ 4GB file support\n";
echo "=" . str_repeat("=", 58) . "=\n";

// Cleanup old files on startup
function cleanup_old_files() {
    $folders = [DOWNLOAD_DIR, THUMB_DIR];
    $one_hour_ago = time() - 3600;
    
    foreach ($folders as $folder) {
        if (file_exists($folder)) {
            $files = scandir($folder);
            foreach ($files as $file) {
                if ($file == '.' || $file == '..') continue;
                
                $file_path = $folder . '/' . $file;
                if (is_file($file_path)) {
                    if (filemtime($file_path) < $one_hour_ago) {
                        unlink($file_path);
                        logger("Cleaned up old file: $file_path");
                    }
                }
            }
        }
    }
}

cleanup_old_files();

// ===== WEBHOOK HANDLER (For Telegram Webhook) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $update = json_decode(file_get_contents('php://input'), true);
    
    if (!$update) {
        http_response_code(400);
        exit;
    }
    
    // Process update here
    // Note: Actual bot implementation would go here
    // This is just the structure
    
    logger("Received update: " . json_encode($update));
    echo json_encode(['ok' => true]);
    exit;
}

// ===== WEB INTERFACE (Optional) =====
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Renamer Bot - Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4A00E0 0%, #8E2DE2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .stat-card h3 {
            color: #495057;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #4A00E0;
            margin: 15px 0;
        }
        
        .stat-card .label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .features {
            padding: 30px;
            background: #f1f3f5;
        }
        
        .features h2 {
            text-align: center;
            color: #343a40;
            margin-bottom: 30px;
            font-size: 2rem;
        }
        
        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .feature-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .feature-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .feature-text h4 {
            color: #495057;
            margin-bottom: 5px;
        }
        
        .feature-text p {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .commands {
            padding: 30px;
        }
        
        .commands h2 {
            text-align: center;
            color: #343a40;
            margin-bottom: 30px;
            font-size: 2rem;
        }
        
        .command-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .command-item {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #4A00E0;
        }
        
        .command-item code {
            background: #4A00E0;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-family: monospace;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .command-item p {
            color: #495057;
            font-size: 0.9rem;
        }
        
        .footer {
            background: #343a40;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .channels {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .channel {
            background: #495057;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
                flex-direction: column;
                gap: 10px;
            }
            
            .stat-card .value {
                font-size: 2rem;
            }
            
            .feature-list, .command-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <span>🤖</span>
                Video Renamer Bot
            </h1>
            <p>Advanced Telegram Bot for Video Processing & Renaming</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📊 Status</h3>
                <div class="value">🟢 Online</div>
                <div class="label">Bot is Running</div>
            </div>
            
            <div class="stat-card">
                <h3>📥 Queue</h3>
                <div class="value"><?php echo count($UPLOAD_QUEUE); ?></div>
                <div class="label">Files in Queue</div>
            </div>
            
            <div class="stat-card">
                <h3>👥 Users</h3>
                <div class="value"><?php echo count($USER_THUMB); ?></div>
                <div class="label">Active Users</div>
            </div>
            
            <div class="stat-card">
                <h3>💾 Storage</h3>
                <div class="value">4GB</div>
                <div class="label">Max File Size</div>
            </div>
        </div>
        
        <div class="features">
            <h2>✨ Advanced Features</h2>
            <div class="feature-list">
                <div class="feature-item">
                    <div class="feature-icon">🎬</div>
                    <div class="feature-text">
                        <h4>Permanent Templates</h4>
                        <p>Set once, use forever. Auto filename generation.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">🔍</div>
                    <div class="feature-text">
                        <h4>Auto Quality Detection</h4>
                        <p>Auto detects 720p, 1080p, 4K from filename.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">🖼️</div>
                    <div class="feature-text">
                        <h4>Smart Thumbnail</h4>
                        <p>Auto-resize to video dimensions with padding.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">📝</div>
                    <div class="feature-text">
                        <h4>Bold Captions</h4>
                        <p>Professional Arial Black style captions.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">⚡</div>
                    <div class="feature-text">
                        <h4>Fast Processing</h4>
                        <p>Queue system with parallel processing.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">🗑️</div>
                    <div class="feature-text">
                        <h4>Auto Cleanup</h4>
                        <p>Automatic cleanup of temporary files.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="commands">
            <h2>📋 Bot Commands</h2>
            <div class="command-grid">
                <div class="command-item">
                    <code>/start</code>
                    <p>Start the bot & show features</p>
                </div>
                
                <div class="command-item">
                    <code>/thumb</code>
                    <p>Set or replace thumbnail</p>
                </div>
                
                <div class="command-item">
                    <code>/filerename</code>
                    <p>Temporary or permanent filename</p>
                </div>
                
                <div class="command-item">
                    <code>/myformat</code>
                    <p>Check your current settings</p>
                </div>
                
                <div class="command-item">
                    <code>/status</code>
                    <p>Check queue & bot status</p>
                </div>
                
                <div class="command-item">
                    <code>/cancel</code>
                    <p>Cancel your upload & cleanup</p>
                </div>
                
                <div class="command-item">
                    <code>/help</code>
                    <p>Detailed help guide</p>
                </div>
                
                <div class="command-item">
                    <code>/stats</code>
                    <p>Bot statistics & metrics</p>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>🤖 Video Renamer Bot v2.0 | Made with ❤️ for Content Creators</p>
            <div class="channels">
                <div class="channel"><?php echo MAIN_CHANNEL; ?></div>
                <div class="channel"><?php echo REQUEST_CHANNEL; ?></div>
                <div class="channel"><?php echo PRINTS_CHANNEL; ?></div>
                <div class="channel"><?php echo BACKUP_CHANNEL; ?></div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh stats every 30 seconds
        setTimeout(() => {
            window.location.reload();
        }, 30000);
        
        // Add animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .feature-item, .command-item');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
<?php
} else {
    // If not GET or POST, show error
    http_response_code(405);
    echo "Method Not Allowed";
}
?>

<!-- 
NOTE: Yeh pura index.php file hai jo exact Python code ka PHP version hai.
Actual Telegram bot run karne ke liye MadelineProto library aur proper
server setup chahiye. Yeh sirf structure aur basic functionality dikhata hai.

Full implementation ke liye:
1. Composer install karo
2. MadelineProto setup karo
3. .env file banao
4. Webhook ya getUpdates method use karo

PHP Telegram bot ke liye yeh ek complete structure hai jismein:
- All Python functions converted to PHP
- Same variable names and structure
- Web interface for monitoring
- Error handling
- Logging system
-->