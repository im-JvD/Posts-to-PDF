<?php
/*
Plugin Name:        Export Post's to PDF
Plugin URI:         https://github.com/im-JvD/Posts-to-PDF
Description:        افزونه اختصاصی برای خروجی PDF از لیست نوشته‌ها (نام، لینک، دسته‌بندی، تاریخ انتشار) + نصب خودکار TCPDF.
Version:            1.0.0
Author:             محمد جواد کریمی
 Author URI:        https://mohamadjavadkarimi.ir/
*/

if (!defined('ABSPATH')) exit;

define('PEPDF_VERSION', '1.0.0');
define('PEPDF_PATH', plugin_dir_path(__FILE__));
define('PEPDF_URL', plugin_dir_url(__FILE__));
define('PEPDF_TCPDF_PATH', PEPDF_PATH . 'tcpdf/tcpdf.php');

require_once PEPDF_PATH . 'includes/installer.php';

/**
 * Admin Menu
 */
add_action('admin_menu', function(){
    add_menu_page(
        __('Post to PDF', 'posts-export-pdf'),
        __('Post to PDF', 'posts-export-pdf'),
        'manage_options',
        'pepdf',
        'pepdf_render_page',
        'dashicons-media-document',
        26
    );
});

/**
 * Admin Page Renderer
 */
function pepdf_render_page(){
    if (!current_user_can('manage_options')) return;
    $tcpdf_ready = file_exists(PEPDF_TCPDF_PATH);

    if (isset($_GET['pepdf_notice'])) {
        echo '<div class="notice notice-success is-dismissible"><p>'.esc_html($_GET['pepdf_notice']).'</p></div>';
    }
    if (isset($_GET['pepdf_error'])) {
        echo '<div class="notice notice-error is-dismissible"><p>'.esc_html($_GET['pepdf_error']).'</p></div>';
    }

    echo '<div class="wrap">';
    echo '<h1>خروجی گرفتن از نوشته‌ها به PDF</h1>';
    echo '<p>این افزونه یک فایل PDF شامل نام نوشته، لینک، دسته‌بندی و تاریخ انتشار می‌سازد و لینک دانلود می‌دهد.</p>';

    if (!$tcpdf_ready) {
        echo '<div class="notice notice-warning"><p>کتابخانه TCPDF هنوز نصب نشده است.</p></div>';
        $install_url = wp_nonce_url(admin_url('admin-post.php?action=pepdf_install_tcpdf'), 'pepdf_install_tcpdf');
        echo '<p><a class="button button-secondary" href="'.$install_url.'">نصب خودکار TCPDF</a></p>';
        echo '<p>در صورت خطا در نصب خودکار، می‌توانید پوشه <code>tcpdf</code> را به صورت دستی در مسیر افزونه کپی کنید.</p>';
    }

    $gen_url = wp_nonce_url(admin_url('admin-post.php?action=pepdf_generate_pdf'), 'pepdf_generate_pdf');
    echo '<p><a class="button button-primary" href="'.$gen_url.'">تولید و دانلود PDF</a></p>';

    echo '</div>';
}

/**
 * Generate PDF handler
 */
add_action('admin_post_pepdf_generate_pdf', function(){
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('pepdf_generate_pdf');

    // Fetch posts
    $posts = get_posts([
        'numberposts' => -1,
        'post_type'   => 'post',
        'post_status' => 'publish',
        'orderby'     => 'date',
        'order'       => 'DESC'
    ]);

    // Build HTML table (RTL + UTF-8)
    $html  = '<style> table{border-collapse:collapse;width:100%;direction:rtl;font-family: DejaVu Sans, DejaVuSans, helvetica;}';
    $html .= 'th,td{border:1px solid #666;padding:6px;font-size:11px;vertical-align:top;} th{background:#f0f0f0;}';
    $html .= 'a{word-break:break-all;}</style>';
    $html .= '<h2 style="direction:rtl;text-align:right;">لیست نوشته‌های سایت</h2>';
    $html .= '<table><thead><tr>';
    $html .= '<th style="width:28%;">نام نوشته</th>';
    $html .= '<th style="width:34%;">لینک نوشته</th>';
    $html .= '<th style="width:20%;">دسته‌بندی</th>';
    $html .= '<th style="width:18%;">تاریخ انتشار</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($posts as $p) {
        $cats = wp_get_post_terms($p->ID, 'category', ['fields' => 'names']);
        $cats_str = is_wp_error($cats) ? '' : implode(', ', $cats);
        $title = esc_html(get_the_title($p->ID));
        $link  = esc_url(get_permalink($p->ID));
        $date  = esc_html(get_the_date('Y-m-d', $p->ID));

        $html .= '<tr>';
        $html .= '<td>'.$title.'</td>';
        $html .= '<td><a href="'.$link.'">'.$link.'</a></td>';
        $html .= '<td>'.esc_html($cats_str).'</td>';
        $html .= '<td>'.$date.'</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    // Ensure uploads dir
    $upload_dir = wp_upload_dir();
    $dir = trailingslashit($upload_dir['basedir']) . 'posts-export-pdf/';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }
    $filename = 'posts-' . date('Ymd-His') . '.pdf';
    $filepath = $dir . $filename;

    if (file_exists(PEPDF_TCPDF_PATH)) {
        // Use TCPDF
        require_once PEPDF_TCPDF_PATH;
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('WordPress');
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('لیست نوشته‌ها');
        $pdf->SetMargins(10, 12, 10);
        $pdf->AddPage();
        // Use a Unicode font (DejaVuSans shipped with TCPDF)
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filepath, 'F'); // Save to file

        // Provide download
        $download_url = trailingslashit($upload_dir['baseurl']) . 'posts-export-pdf/' . $filename;
        // Force download if requested
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            readfile($filepath);
            exit;
        } else {
            // Fallback to redirect with link
            wp_safe_redirect(admin_url('admin.php?page=pepdf&pepdf_notice=' . urlencode('PDF ساخته شد: ' . $download_url)));
            exit;
        }
    } else {
        // TCPDF missing
        wp_safe_redirect(admin_url('admin.php?page=pepdf&pepdf_error=' . urlencode('TCPDF نصب نیست. ابتدا روی "نصب خودکار TCPDF" کلیک کنید.')));
        exit;
    }
});

/**
 * Install TCPDF (auto-download from GitHub)
 */
add_action('admin_post_pepdf_install_tcpdf', function(){
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('pepdf_install_tcpdf');
    $result = pepdf_install_tcpdf();
    if ($result === true) {
        wp_safe_redirect(admin_url('admin.php?page=pepdf&pepdf_notice=' . urlencode('TCPDF با موفقیت نصب شد. حالا می‌توانید PDF بسازید.')));
    } else {
        wp_safe_redirect(admin_url('admin.php?page=pepdf&pepdf_error=' . urlencode('نصب TCPDF ناموفق بود: ' . $result)));
    }
    exit;
});
