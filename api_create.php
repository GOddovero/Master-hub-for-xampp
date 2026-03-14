<?php
declare(strict_types=1);

// Configuration paths
$hub_dir = __DIR__;
$htdocs_dir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..') ?: dirname(__DIR__);
$config_path = $hub_dir . DIRECTORY_SEPARATOR . 'projects.json';

// Response helper
function fail(string $msg) {
    header('Location: index.php?error=' . urlencode($msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request method');
}

$folder_name = trim($_POST['folder_name'] ?? '');
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$group = trim($_POST['group'] ?? '');
$create_from = trim($_POST['create_from'] ?? '');

if ($folder_name === '') {
    fail('Folder name is required.');
}

if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $folder_name)) {
    fail('Folder name contains invalid characters. Use only letters, numbers, hyphens, and underscores.');
}

$new_dir = $htdocs_dir . DIRECTORY_SEPARATOR . $folder_name;

if (file_exists($new_dir)) {
    fail('A folder with that name already exists.');
}

// Function to recursively copy directories
function copy_dir_recursive(string $src, string $dst): bool {
    $dir = @opendir($src);
    if ($dir === false) return false;
    
    if (!@mkdir($dst, 0777, true) && !is_dir($dst)) {
        closedir($dir);
        return false;
    }
    
    $success = true;
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            $src_file = $src . DIRECTORY_SEPARATOR . $file;
            $dst_file = $dst . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($src_file)) {
                if (!copy_dir_recursive($src_file, $dst_file)) {
                    $success = false;
                }
            } else {
                if (!@copy($src_file, $dst_file)) {
                    $success = false;
                }
            }
        }
    }
    closedir($dir);
    return $success;
}

// Create or clone the directory
if ($create_from !== '') {
    $src_dir = $htdocs_dir . DIRECTORY_SEPARATOR . $create_from;
    if (!is_dir($src_dir)) {
        fail('Source folder to clone does not exist.');
    }
    if (!copy_dir_recursive($src_dir, $new_dir)) {
        fail('Failed to clone the directory. Check permissions.');
    }
} else {
    if (!@mkdir($new_dir, 0777, true) && !is_dir($new_dir)) {
        fail('Failed to create new directory. Check permissions.');
    }
}

// Handle logo
$logo_path = '';
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['logo']['tmp_name'];
    $file_name = $_FILES['logo']['name'];
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    $allowed_exts = ['png', 'jpg', 'jpeg', 'svg', 'gif', 'webp'];
    if (in_array($ext, $allowed_exts, true)) {
        $safe_logo_name = 'logo_' . $folder_name . '_' . time() . '.' . $ext;
        $dest_logo = $hub_dir . DIRECTORY_SEPARATOR . 'logos' . DIRECTORY_SEPARATOR . $safe_logo_name;
        
        if (@move_uploaded_file($tmp_name, $dest_logo)) {
            $logo_path = $safe_logo_name;
        }
    }
}

// Update projects.json
$config = [];
if (is_file($config_path)) {
    $json = file_get_contents($config_path);
    if ($json !== false) {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $config = $decoded;
        }
    }
}

if (!isset($config[$folder_name])) {
    $config[$folder_name] = [];
}
if ($title !== '') $config[$folder_name]['title'] = $title;
if ($description !== '') $config[$folder_name]['description'] = $description;
if ($group !== '') $config[$folder_name]['group'] = $group;
if ($logo_path !== '') {
    $config[$folder_name]['logo'] = $logo_path;
}

$json_opts = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
file_put_contents($config_path, json_encode($config, $json_opts));

header('Location: index.php?success=1');
exit;
