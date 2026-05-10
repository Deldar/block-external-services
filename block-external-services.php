<?php
/**
 * Plugin Name: Block External Services (مدیریت دامنه‌های مسدود)
 * Description: مسدودسازی فونت‌های گوگل، المنتور، Gravatar و هر دامنه دلخواه دیگر با امکان مدیریت از پیشخوان توسط سیب هاست
 * Version: 2.0.1
 * Author: SIB HOST
 * Text Domain: block-external-services
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================
// ۱. مقداردهی اولیه و ذخیره در دیتابیس
// ============================================
function bes_get_blocked_domains() {
    $defaults = array(
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'elementor.com',
        'gravatar.com',
		'wordpress.org',
    );
    return get_option( 'bes_blocked_domains', $defaults );
}

function bes_save_blocked_domains( $domains ) {
    $domains = array_map( 'sanitize_text_field', $domains );
    $domains = array_filter( $domains ); // حذف خالی‌ها
    $domains = array_unique( $domains );
    update_option( 'bes_blocked_domains', $domains );
}

// ============================================
// ۲. منوی مدیریت در پیشخوان (تنظیمات)
// ============================================
add_action( 'admin_menu', 'bes_add_admin_menu' );
function bes_add_admin_menu() {
    add_options_page(
        'مدیریت دامنه‌های مسدود',
        'مسدودسازی سرویس‌ها',
        'manage_options',
        'bes-blocked-domains',
        'bes_render_admin_page'
    );
}

function bes_render_admin_page() {
    // پردازش فرم اضافه کردن
    if ( isset( $_POST['bes_add_domain'] ) && check_admin_referer( 'bes_domains_action', 'bes_nonce' ) ) {
        $new_domain = sanitize_text_field( $_POST['bes_new_domain'] );
        if ( ! empty( $new_domain ) ) {
            $domains = bes_get_blocked_domains();
            if ( ! in_array( $new_domain, $domains ) ) {
                $domains[] = $new_domain;
                bes_save_blocked_domains( $domains );
                echo '<div class="notice notice-success"><p>دامنه با موفقیت اضافه شد.</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>این دامنه قبلاً وجود دارد.</p></div>';
            }
        }
    }

    // پردازش حذف دامنه
    if ( isset( $_GET['bes_remove'] ) && isset( $_GET['bes_nonce'] ) && wp_verify_nonce( $_GET['bes_nonce'], 'bes_remove_domain' ) ) {
        $remove = sanitize_text_field( $_GET['bes_remove'] );
        $domains = bes_get_blocked_domains();
        $key = array_search( $remove, $domains );
        if ( $key !== false ) {
            unset( $domains[ $key ] );
            bes_save_blocked_domains( array_values( $domains ) );
            echo '<div class="notice notice-success"><p>دامنه با موفقیت حذف شد.</p></div>';
        }
    }

    $domains = bes_get_blocked_domains();
    ?>
    <div class="wrap">
        <h1>مدیریت دامنه‌های مسدود شده</h1>
        <p>درخواست‌های خروجی به دامنه‌های زیر کاملاً مسدود می‌شوند.</p>

        <!-- فرم افزودن دامنه جدید -->
        <h2>افزودن دامنه جدید</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'bes_domains_action', 'bes_nonce' ); ?>
            <input type="text" name="bes_new_domain" placeholder="مثال: example.com" style="width: 300px;" required>
            <input type="submit" name="bes_add_domain" class="button button-primary" value="افزودن دامنه">
        </form>

        <hr>

        <!-- لیست دامنه‌های موجود -->
        <h2>دامنه‌های مسدود شده فعلی</h2>
        <?php if ( empty( $domains ) ) : ?>
            <p>هیچ دامنه‌ای مسدود نیست.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 70%">دامنه</th>
                        <th style="width: 30%">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $domains as $domain ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $domain ); ?></code></td>
                            <td>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'bes_remove', $domain ), 'bes_remove_domain', 'bes_nonce' ) ); ?>" 
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('آیا از حذف دامنه <?php echo esc_js( $domain ); ?> مطمئن هستید؟');">
                                    حذف
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="description">⚠️ توجه: دامنه‌های پیش‌فرض (fonts.googleapis.com و ...) را حذف نکنید مگر اینکه آگاه باشید.</p>
    </div>
    <?php
}

// ============================================
// ۳. توابع مسدودسازی (با استفاده از لیست داینامیک)
// ============================================

// ۳.۱ مسدودسازی در خروجی نهایی HTML (حذف تگ‌ها)
function bes_block_google_fonts_output_buffer() {
    // فقط در صورتی اجرا شود که دامنه مربوطه در لیست باشد (بهینه‌سازی)
    $domains = bes_get_blocked_domains();
    $has_font_domains = false;
    foreach ( $domains as $domain ) {
        if ( strpos( $domain, 'fonts.' ) !== false || strpos( $domain, 'google' ) !== false ) {
            $has_font_domains = true;
            break;
        }
    }
    if ( ! $has_font_domains ) {
        return;
    }

    ob_start( function( $buffer ) use ( $domains ) {
        // ساخت الگوی regex برای تمام دامنه‌های مسدود (اختیاری، برای سرعت فقط روی دامنه‌های فونت تمرکز می‌کنیم)
        $pattern = '/<link[^>]*href=["\'][^"\']*fonts\.(googleapis|gstatic)\.com[^"\']*["\'][^>]*>/i';
        $buffer = preg_replace( $pattern, '', $buffer );
        $buffer = preg_replace( '/<style[^>]*>[^<]*fonts\.(googleapis|gstatic)\.com[^<]*<\/style>/i', '', $buffer );
        $buffer = preg_replace( '/<script[^>]*src=["\'][^"\']*fonts\.(googleapis|gstatic)\.com[^"\']*["\'][^>]*><\/script>/i', '', $buffer );
        $buffer = preg_replace( '/<link[^>]*rel=["\'](dns-prefetch|preconnect)["\'][^>]*href=["\'][^"\']*google[^"\']*["\'][^>]*>/i', '', $buffer );
        return $buffer;
    } );
}
add_action( 'init', 'bes_block_google_fonts_output_buffer' );

// ۳.۲ حذف از enqueue
function bes_disable_google_fonts_from_enqueue() {
    $domains = bes_get_blocked_domains();
    global $wp_styles;
    if ( ! is_object( $wp_styles ) || empty( $wp_styles->registered ) ) {
        return;
    }
    foreach ( $wp_styles->registered as $handle => $style ) {
        $src = $style->src;
        if ( ! $src ) continue;
        foreach ( $domains as $domain ) {
            if ( strpos( $src, $domain ) !== false ) {
                wp_dequeue_style( $handle );
                wp_deregister_style( $handle );
                break;
            }
        }
    }
}
add_action( 'wp_enqueue_scripts', 'bes_disable_google_fonts_from_enqueue', 9999 );
add_action( 'admin_enqueue_scripts', 'bes_disable_google_fonts_from_enqueue', 9999 );
add_action( 'login_enqueue_scripts', 'bes_disable_google_fonts_from_enqueue', 9999 );

// ۳.۳ فیلتر src استایل‌ها
function bes_filter_style_src( $src, $handle ) {
    $domains = bes_get_blocked_domains();
    foreach ( $domains as $domain ) {
        if ( strpos( $src, $domain ) !== false ) {
            return false;
        }
    }
    return $src;
}
add_filter( 'style_loader_src', 'bes_filter_style_src', 9999, 2 );

// ۳.۴ حذف resource hints
function bes_remove_resource_hints( $hints, $relation_type ) {
    $domains = bes_get_blocked_domains();
    if ( ! in_array( $relation_type, array( 'dns-prefetch', 'preconnect' ) ) ) {
        return $hints;
    }
    $new_hints = array();
    foreach ( $hints as $url ) {
        $blocked = false;
        foreach ( $domains as $domain ) {
            if ( strpos( $url, $domain ) !== false ) {
                $blocked = true;
                break;
            }
        }
        if ( ! $blocked ) {
            $new_hints[] = $url;
        }
    }
    return $new_hints;
}
add_filter( 'wp_resource_hints', 'bes_remove_resource_hints', 10, 2 );

// ۳.۵ مسدودسازی درخواست‌های HTTP خروجی (قدرتمند)
function bes_block_http_requests( $preempt, $args, $url ) {
    $domains = bes_get_blocked_domains();
    foreach ( $domains as $domain ) {
        if ( strpos( $url, $domain ) !== false ) {
            return new WP_Error( 'blocked_domain', "درخواست به {$domain} مسدود شده است." );
        }
    }
    return $preempt;
}
add_filter( 'pre_http_request', 'bes_block_http_requests', 10, 3 );

// ۳.۶ مسدودسازی Gravatar (اگر دامنه gravatar.com در لیست باشد)
function bes_disable_gravatar( $avatar, $id_or_email, $size, $default, $alt ) {
    $domains = bes_get_blocked_domains();
    if ( in_array( 'gravatar.com', $domains ) ) {
        // بازگرداندن تصویر خالی
        return '<img alt="' . esc_attr( $alt ) . '" src="' . esc_url( get_avatar_url( $id_or_email, [ 'size' => $size, 'default' => 'blank' ] ) ) . '" class="avatar avatar-' . $size . ' photo" height="' . $size . '" width="' . $size . '" loading="lazy" />';
    }
    return $avatar;
}
add_filter( 'get_avatar', 'bes_disable_gravatar', 10, 5 );

// ============================================
// ۴. پیوند تنظیمات در صفحه افزونه‌ها
// ============================================
function bes_add_settings_link( $links ) {
    $settings_link = '<a href="options-general.php?page=bes-blocked-domains">تنظیمات</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bes_add_settings_link' );

// ============================================
// ۵. نمایش پیام هشدار در پیشخوان برای اطلاع از فعال بودن افزونه
// ============================================
function bes_show_admin_notice() {
    // فقط به مدیران سایت نمایش داده شود
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $domains   = bes_get_blocked_domains();
    $count     = count( $domains );
    $domains_list = implode( '، ', array_map( function( $d ) {
        return '<code>' . esc_html( $d ) . '</code>';
    }, $domains ) );
    
    echo '<div class="notice notice-warning is-dismissible">
        <p>⚠️ <strong>افزونه مسدودسازی سرویس‌های خارجی فعال است.</strong> در حال حاضر ' . $count . ' دامنه مسدود می‌شود: ' . $domains_list . '</p>
    </div>';
}
add_action( 'admin_notices', 'bes_show_admin_notice' );
