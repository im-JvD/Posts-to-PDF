<?php
if (!defined('ABSPATH')) exit;

/**
 * Downloads and extracts TCPDF into the plugin directory under ./tcpdf
 * Returns true on success, or error string on failure.
 */
function pepdf_install_tcpdf() {
    $dest_dir = trailingslashit(plugin_dir_path(__FILE__)) . '../tcpdf';
    if (file_exists($dest_dir . '/tcpdf.php')) {
        return true;
    }

    // GitHub ZIP of TCPDF main branch
    $zip_url = 'https://github.com/tecnickcom/TCPDF/archive/refs/heads/main.zip';

    // Download to tmp
    $tmp = download_url($zip_url);
    if (is_wp_error($tmp)) {
        return 'دانلود نشد: ' . $tmp->get_error_message();
    }

    // Unzip to a temp dir
    $unzipped = unzip_file($tmp, WP_CONTENT_DIR . '/uploads');
    @unlink($tmp);
    if (is_wp_error($unzipped)) {
        return 'بازکردن فایل زیپ ناموفق بود: ' . $unzipped->get_error_message();
    }

    // Find extracted folder (TCPDF-main or similar)
    $uploads_dir = WP_CONTENT_DIR . '/uploads/';
    $candidate = $uploads_dir . 'TCPDF-main';
    if (!file_exists($candidate)) {
        // Try another common name
        $dirs = glob($uploads_dir . 'TCPDF-*', GLOB_ONLYDIR);
        if (!empty($dirs)) {
            $candidate = $dirs[0];
        } else {
            return 'پوشه TCPDF پیدا نشد بعد از استخراج.';
        }
    }

    // Move / copy the tcpdf folder content
    if (!file_exists($dest_dir)) {
        wp_mkdir_p($dest_dir);
    }

    // In TCPDF repo, the main php is in the root folder. We will copy the necessary files/folders.
    // Copy whole extracted folder content into $dest_dir
    $result = pepdf_recursive_copy($candidate, $dest_dir);
    // Cleanup extracted
    pepdf_recursive_delete($candidate);

    if (!$result) {
        return 'کپی فایل‌های TCPDF ناموفق بود.';
    }

    // Ensure main file exists
    if (!file_exists($dest_dir . '/tcpdf.php')) {
        // Sometimes structure is tcpdf/tcpdf.php
        if (file_exists($dest_dir . '/tcpdf/tcpdf.php')) {
            // Move inner tcpdf up
            pepdf_recursive_copy($dest_dir . '/tcpdf', $dest_dir);
        } else {
            return 'فایل tcpdf.php یافت نشد.';
        }
    }

    return true;
}

function pepdf_recursive_copy($src, $dst) {
    $dir = opendir($src);
    if (!$dir) return false;
    @mkdir($dst, 0755, true);
    while(false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                if (!pepdf_recursive_copy($src . '/' . $file, $dst . '/' . $file)) return false;
            } else {
                if (!copy($src . '/' . $file, $dst . '/' . $file)) return false;
            }
        }
    }
    closedir($dir);
    return true;
}

function pepdf_recursive_delete($dir) {
    if (!file_exists($dir)) return;
    if (is_file($dir)) { @unlink($dir); return; }
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            pepdf_recursive_delete($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}
