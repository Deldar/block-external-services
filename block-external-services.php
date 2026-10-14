<?php
/**
 * Plugin Name: Block External Services (مدیریت دامنه‌های مسدود)
 * Plugin URI: https://github.com/Deldar/block-external-services
 * Description: مسدودسازی فونت‌های گوگل، المنتور، Gravatar، آپدیت‌های وردپرس و هر دامنه دلخواه دیگر برای افزایش چشمگیر سرعت پیشخوان در مواقع اضطراری و اختلالات شبکه.
 * Version: 2.1.0
 * Author: SIB HOST & Deldar
 * Author URI: https://github.com/Deldar
 * Text Domain: block-external-services
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BES_VERSION', '2.1.0' );

// ============================================
// ۱. تنظیمات و مقداردهی اولیه
// ============================================

/**
 * بررسی فعال بودن حالت مسدودسازی اضطراری (Master Switch)
 */
function bes_is_active() {
    return (bool) get_option( 'bes_emergency_mode_enabled', true );
}

/**
 * دریافت لیست دامنه‌های مسدود شده
 */
function bes_get_blocked_domains() {
    static $cached_domains = null;
    if ( null !== $cached_domains ) {
        return $cached_domains;
    }

    $defaults = array(
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'elementor.com',
        'gravatar.com',
        'wordpress.org',
    );

    $domains = get_option( 'bes_blocked_domains', $defaults );
    if ( ! is_array( $domains ) ) {
        $domains = $defaults;
    }

    $clean = array();
    foreach ( $domains as $d ) {
        $normalized = bes_normalize_domain( $d );
        if ( ! empty( $normalized ) ) {
            $clean[] = $normalized;
        }
    }

    $cached_domains = array_values( array_unique( $clean ) );
    return $cached_domains;
}

/**
 * ذخیره لیست دامنه‌های مسدود شده
 */
function bes_save_blocked_domains( $domains ) {
    if ( ! is_array( $domains ) ) {
        $domains = array();
    }
    $clean = array();
    foreach ( $domains as $domain ) {
        $normalized = bes_normalize_domain( $domain );
        if ( ! empty( $normalized ) ) {
            $clean[] = $normalized;
        }
    }
    $clean = array_values( array_unique( $clean ) );
    update_option( 'bes_blocked_domains', $clean );
}

/**
 * نرمال‌سازی دامنه (حذف پروتکل، پورت، مسیر و تبدیل به حروف کوچک)
 */
function bes_normalize_domain( $domain ) {
    $domain = trim( (string) $domain );
    if ( empty( $domain ) ) {
        return '';
    }
    if ( strpos( $domain, '//' ) === false ) {
        $domain = 'http://' . $domain;
    }
    $host = wp_parse_url( $domain, PHP_URL_HOST );
    if ( ! $host ) {
        $parts = explode( '/', preg_replace( '#^https?://#i', '', $domain ) );
        $host = trim( $parts[0] );
    }
    $host = strtolower( $host );
    $host = preg_replace( '/^www\./i', '', $host );
    // حذف پورت احتمالی
    $host_parts = explode( ':', $host );
    return sanitize_text_field( $host_parts[0] );
}

/**
 * تطابق دقیق دامنه یا زیردامنه با لیست مسدود
 *
 * @param string $url آدرس کامل یا هاست
 * @param array|null $blocked_domains
 * @return string|false نام دامنه تطابق یافته یا false
 */
function bes_find_matching_blocked_domain( $url, $blocked_domains = null ) {
    if ( empty( $url ) ) {
        return false;
    }

    if ( null === $blocked_domains ) {
        $blocked_domains = bes_get_blocked_domains();
    }

    if ( empty( $blocked_domains ) ) {
        return false;
    }

    $host = $url;
    if ( strpos( $url, '://' ) !== false || strpos( $url, '//' ) === 0 ) {
        $parsed_host = wp_parse_url( $url, PHP_URL_HOST );
        if ( $parsed_host ) {
            $host = $parsed_host;
        }
    } else {
        $parts = explode( '/', $url );
        $host = $parts[0];
    }

    $host = strtolower( trim( $host ) );
    $host = preg_replace( '/^www\./i', '', $host );
    $host_parts = explode( ':', $host );
    $host = $host_parts[0];

    if ( empty( $host ) ) {
        return false;
    }

    foreach ( $blocked_domains as $blocked ) {
        $blocked = strtolower( trim( $blocked ) );
        if ( empty( $blocked ) ) {
            continue;
        }

        // تطابق کامل هاست
        if ( $host === $blocked ) {
            return $blocked;
        }

        // تطابق زیردامنه (مثلاً api.wordpress.org با wordpress.org)
        $suffix = '.' . $blocked;
        if ( strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix ) {
            return $blocked;
        }
    }

    return false;
}

/**
 * ثبت در لاگ آخرین درخواست‌های مسدود شده
 */
function bes_log_blocked_request( $url, $matched_domain ) {
    $log = get_option( 'bes_blocked_requests_log', array() );
    if ( ! is_array( $log ) ) {
        $log = array();
    }

    $entry = array(
        'time'   => current_time( 'timestamp' ),
        'domain' => sanitize_text_field( $matched_domain ),
        'url'    => esc_url_raw( $url ),
    );

    array_unshift( $log, $entry );
    // نگهداری حداکثر ۳۰ لاگ اخیر
    if ( count( $log ) > 30 ) {
        $log = array_slice( $log, 0, 30 );
    }

    update_option( 'bes_blocked_requests_log', $log, false );
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
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // ۱. پردازش سوییچ فعال/غیرفعال کردن حالت اضطراری
    if ( isset( $_POST['bes_toggle_mode'] ) && check_admin_referer( 'bes_toggle_mode_action', 'bes_toggle_nonce' ) ) {
        $current_status = bes_is_active();
        update_option( 'bes_emergency_mode_enabled', ! $current_status );
        echo '<div class="notice notice-success is-dismissible"><p>' . ( ! $current_status ? 'حالت اضطراری فعال شد.' : 'مسدودسازی اضطراری غیرفعال (متوقف) شد.' ) . '</p></div>';
    }

    // ۲. پردازش افزودن دامنه جدید
    if ( isset( $_POST['bes_add_domain'] ) && check_admin_referer( 'bes_domains_action', 'bes_nonce' ) ) {
        $new_domain = bes_normalize_domain( $_POST['bes_new_domain'] ?? '' );
        if ( ! empty( $new_domain ) ) {
            $domains = bes_get_blocked_domains();
            if ( ! in_array( $new_domain, $domains, true ) ) {
                $domains[] = $new_domain;
                bes_save_blocked_domains( $domains );
                echo '<div class="notice notice-success is-dismissible"><p>دامنه <code>' . esc_html( $new_domain ) . '</code> با موفقیت به لیست مسدودها اضافه شد.</p></div>';
            } else {
                echo '<div class="notice notice-warning is-dismissible"><p>این دامنه از قبل در لیست مسدودها وجود دارد.</p></div>';
            }
        }
    }

    // ۳. پردازش حذف دامنه
    if ( isset( $_POST['bes_remove_domain_action'] ) && check_admin_referer( 'bes_remove_action', 'bes_remove_nonce' ) ) {
        $remove = bes_normalize_domain( $_POST['bes_remove_domain_target'] ?? '' );
        $domains = bes_get_blocked_domains();
        $key = array_search( $remove, $domains, true );
        if ( $key !== false ) {
            unset( $domains[ $key ] );
            bes_save_blocked_domains( array_values( $domains ) );
            echo '<div class="notice notice-success is-dismissible"><p>دامنه <code>' . esc_html( $remove ) . '</code> حذف شد.</p></div>';
        }
    }

    // ۴. پردازش پاکسازی لاگ
    if ( isset( $_POST['bes_clear_log'] ) && check_admin_referer( 'bes_clear_log_action', 'bes_log_nonce' ) ) {
        update_option( 'bes_blocked_requests_log', array(), false );
        echo '<div class="notice notice-success is-dismissible"><p>گزارش درخواست‌های مسدود شده پاکسازی شد.</p></div>';
    }

    $is_active   = bes_is_active();
    $domains     = bes_get_blocked_domains();
    $blocked_log = get_option( 'bes_blocked_requests_log', array() );
    ?>
    <div class="wrap bes-admin-wrap" style="max-width: 960px;">
        <h1>🛡️ مدیریت دامنه‌های مسدود شده (Block External Services)</h1>
        <p class="description">این افزونه برای زمان‌های اضطراری (اختلال شبکه، فیلترینگ، تحریم یا کندی پیشخوان) طراحی شده است تا با قطع درخواست‌های خارجی، وردپرس را فوراً سریع کند.</p>

        <!-- باکس وضعیت اصلی و سوئیچ اضطراری -->
        <div style="background: #fff; border-left: 4px solid <?php echo $is_active ? '#46b450' : '#dc3232'; ?>; padding: 15px 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border-radius: 4px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h3 style="margin: 0 0 6px 0; font-size: 16px;">
                        وضعیت فعلی سیستم: 
                        <?php if ( $is_active ) : ?>
                            <span style="color: #46b450; font-weight: bold;">● فعال (درحال مسدودسازی دامنه‌ها)</span>
                        <?php else : ?>
                            <span style="color: #dc3232; font-weight: bold;">○ غیرفعال (متوقف شده)</span>
                        <?php endif; ?>
                    </h3>
                    <p style="margin: 0; color: #666; font-size: 13px;">
                        <?php if ( $is_active ) : ?>
                            تمام درخواست‌های خروجی HTTP، اسکریپت‌ها و فونت‌های دامنه‌های زیر مسدود می‌شوند.
                        <?php else : ?>
                            مسدودسازی موقتاً خاموش است و تمام درخواست‌ها طبق روال عادی ارسال می‌شوند.
                        <?php endif; ?>
                    </p>
                </div>
                <form method="post" action="">
                    <?php wp_nonce_field( 'bes_toggle_mode_action', 'bes_toggle_nonce' ); ?>
                    <button type="submit" name="bes_toggle_mode" class="button <?php echo $is_active ? 'button-secondary' : 'button-primary'; ?>" style="font-weight: bold;">
                        <?php echo $is_active ? '⏸️ متوقف کردن مسدودسازی' : '▶️ فعال‌سازی حالت اضطراری'; ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- فرم افزودن دامنه و پیش‌تنظیم‌ها -->
        <div style="background: #fff; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 25px;">
            <h2 style="margin-top: 0;">➕ افزودن دامنه جدید به لیست مسدود</h2>
            <form method="post" action="" style="margin-bottom: 15px;">
                <?php wp_nonce_field( 'bes_domains_action', 'bes_nonce' ); ?>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <input type="text" name="bes_new_domain" placeholder="مثال: example.com یا api.service.com" style="min-width: 320px; padding: 6px 10px;" required>
                    <input type="submit" name="bes_add_domain" class="button button-primary" value="افزودن دامنه">
                </div>
                <p class="description" style="margin-top: 8px;">پروتکل (https://) یا مسیر بعد از دامنه به طور خودکار حذف و نرمال‌سازی می‌شوند.</p>
            </form>

            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">

            <!-- میانبرهای آماده -->
            <div>
                <strong>میانبر افزودن سرویس‌های پرکاربرد:</strong>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                    <?php
                    $common_presets = array(
                        'fonts.googleapis.com' => 'فونت گوگل',
                        'fonts.gstatic.com'    => 'گوگل استاتیک',
                        'gravatar.com'         => 'گراواتار',
                        'elementor.com'        => 'المنتور',
                        'wordpress.org'        => 'مخزن وردپرس',
                    );
                    foreach ( $common_presets as $preset_domain => $preset_label ) :
                        $already_added = in_array( $preset_domain, $domains, true );
                        ?>
                        <?php if ( ! $already_added ) : ?>
                            <form method="post" action="" style="display: inline-block; margin: 0;">
                                <?php wp_nonce_field( 'bes_domains_action', 'bes_nonce' ); ?>
                                <input type="hidden" name="bes_new_domain" value="<?php echo esc_attr( $preset_domain ); ?>">
                                <button type="submit" name="bes_add_domain" class="button button-small" title="افزودن <?php echo esc_attr( $preset_domain ); ?>">
                                    + <?php echo esc_html( $preset_label ); ?> (<code><?php echo esc_html( $preset_domain ); ?></code>)
                                </button>
                            </form>
                        <?php else : ?>
                            <span class="button button-small disabled" style="opacity: 0.6; cursor: default;">
                                ✓ <?php echo esc_html( $preset_label ); ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- جدول دامنه‌های مسدود شده -->
        <div style="background: #fff; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 25px;">
            <h2 style="margin-top: 0; display: flex; justify-content: space-between; align-items: center;">
                <span>📋 دامنه‌های مسدود شده فعلی (<?php echo count( $domains ); ?>)</span>
            </h2>

            <?php if ( empty( $domains ) ) : ?>
                <p>هیچ دامنه‌ای در لیست مسدود قرار ندارد.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped" style="border: 1px solid #e5e5e5;">
                    <thead>
                        <tr>
                            <th style="width: 70%;">دامنه یا هاست هدف</th>
                            <th style="width: 30%; text-align: left;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $domains as $domain ) : ?>
                            <tr>
                                <td>
                                    <code style="font-size: 13px; font-weight: bold;"><?php echo esc_html( $domain ); ?></code>
                                    <span style="color: #888; font-size: 12px; margin-right: 8px;">(و کلیه زیردامنه‌ها)</span>
                                </td>
                                <td style="text-align: left;">
                                    <form method="post" action="" style="display: inline;" onsubmit="return confirm('آیا از حذف دامنه <?php echo esc_js( $domain ); ?> مطمئن هستید؟');">
                                        <?php wp_nonce_field( 'bes_remove_action', 'bes_remove_nonce' ); ?>
                                        <input type="hidden" name="bes_remove_domain_target" value="<?php echo esc_attr( $domain ); ?>">
                                        <button type="submit" name="bes_remove_domain_action" class="button button-small button-link-delete" style="color: #a00;">
                                            حذف دامنه
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- جدول لاگ درخواست‌های مسدود شده -->
        <div style="background: #fff; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h2 style="margin: 0;">📊 آخرین درخواست‌های مسدود شده (Log)</h2>
                <?php if ( ! empty( $blocked_log ) ) : ?>
                    <form method="post" action="" onsubmit="return confirm('آیا از پاکسازی لاگ مطمئن هستید؟');" style="margin: 0;">
                        <?php wp_nonce_field( 'bes_clear_log_action', 'bes_log_nonce' ); ?>
                        <button type="submit" name="bes_clear_log" class="button button-small">
                            🗑️ پاکسازی لاگ
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="description">این لیست به شما نشان می‌دهد کدام درخواست‌ها با مسدودسازی مواجه شده و مانع از هدر رفتن زمان پیشخوان شده‌اند.</p>

            <?php if ( empty( $blocked_log ) ) : ?>
                <p style="color: #777;">تاکنون هیچ درخواستی در لاگ ثبت نشده است.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped" style="border: 1px solid #e5e5e5; font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="width: 20%;">زمان</th>
                            <th style="width: 25%;">دامنه مسدود شده</th>
                            <th style="width: 55%;">آدرس کامل درخواست (URL)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $blocked_log as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( 'Y-m-d H:i:s', $entry['time'] ?? time() ) ); ?></td>
                                <td><code><?php echo esc_html( $entry['domain'] ?? '-' ); ?></code></td>
                                <td style="word-break: break-all; color: #555;">
                                    <?php echo esc_html( $entry['url'] ?? '-' ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// ============================================
// ۳. توابع مسدودسازی (با تطابق هوشمند و دقیق)
// ============================================

// ۳.۱ مسدودسازی در خروجی نهایی HTML (حذف فونت‌های خارجی از تگ‌های لینک و استایل)
function bes_block_google_fonts_output_buffer() {
    if ( ! bes_is_active() ) {
        return;
    }

    if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return;
    }

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

    ob_start( function( $buffer ) {
        if ( empty( $buffer ) || ! is_string( $buffer ) ) {
            return $buffer;
        }
        $pattern = '/<link[^>]*href=["\'][^"\']*fonts\.(googleapis|gstatic)\.com[^"\']*["\'][^>]*>/i';
        $buffer = preg_replace( $pattern, '', $buffer );
        $buffer = preg_replace( '/<style[^>]*>[^<]*fonts\.(googleapis|gstatic)\.com[^<]*<\/style>/is', '', $buffer );
        $buffer = preg_replace( '/<script[^>]*src=["\'][^"\']*fonts\.(googleapis|gstatic)\.com[^"\']*["\'][^>]*><\/script>/is', '', $buffer );
        $buffer = preg_replace( '/<link[^>]*rel=["\'](dns-prefetch|preconnect)["\'][^>]*href=["\'][^"\']*fonts\.(googleapis|gstatic)\.com[^"\']*["\'][^>]*>/i', '', $buffer );
        return $buffer;
    } );
}
add_action( 'template_redirect', 'bes_block_google_fonts_output_buffer', 1 );
add_action( 'admin_init', 'bes_block_google_fonts_output_buffer', 1 );

// ۳.۲ حذف استایل‌ها (CSS) و اسکریپت‌ها (JS) از صف وردپرس (Enqueue)
function bes_disable_external_enqueued_assets() {
    if ( ! bes_is_active() ) {
        return;
    }

    $domains = bes_get_blocked_domains();
    if ( empty( $domains ) ) {
        return;
    }

    // ۱. بررسی استایل‌ها
    global $wp_styles;
    if ( is_object( $wp_styles ) && ! empty( $wp_styles->registered ) ) {
        foreach ( $wp_styles->registered as $handle => $style ) {
            if ( ! empty( $style->src ) && bes_find_matching_blocked_domain( $style->src, $domains ) ) {
                wp_dequeue_style( $handle );
                wp_deregister_style( $handle );
            }
        }
    }

    // ۲. بررسی اسکریپت‌ها
    global $wp_scripts;
    if ( is_object( $wp_scripts ) && ! empty( $wp_scripts->registered ) ) {
        foreach ( $wp_scripts->registered as $handle => $script ) {
            if ( ! empty( $script->src ) && bes_find_matching_blocked_domain( $script->src, $domains ) ) {
                wp_dequeue_script( $handle );
                wp_deregister_script( $handle );
            }
        }
    }
}
add_action( 'wp_enqueue_scripts', 'bes_disable_external_enqueued_assets', 9999 );
add_action( 'admin_enqueue_scripts', 'bes_disable_external_enqueued_assets', 9999 );
add_action( 'login_enqueue_scripts', 'bes_disable_external_enqueued_assets', 9999 );

// ۳.۳ فیلتر src استایل‌ها
function bes_filter_style_src( $src, $handle ) {
    if ( ! bes_is_active() || ! $src ) {
        return $src;
    }
    if ( bes_find_matching_blocked_domain( $src ) ) {
        return false;
    }
    return $src;
}
add_filter( 'style_loader_src', 'bes_filter_style_src', 9999, 2 );

// ۳.۴ فیلتر src اسکریپت‌ها
function bes_filter_script_src( $src, $handle ) {
    if ( ! bes_is_active() || ! $src ) {
        return $src;
    }
    if ( bes_find_matching_blocked_domain( $src ) ) {
        return false;
    }
    return $src;
}
add_filter( 'script_loader_src', 'bes_filter_script_src', 9999, 2 );

// ۳.۵ حذف Resource Hints (dns-prefetch, preconnect, prerender)
function bes_remove_resource_hints( $hints, $relation_type ) {
    if ( ! bes_is_active() || empty( $hints ) ) {
        return $hints;
    }
    if ( ! in_array( $relation_type, array( 'dns-prefetch', 'preconnect', 'prerender' ), true ) ) {
        return $hints;
    }

    $domains = bes_get_blocked_domains();
    $clean_hints = array();

    foreach ( $hints as $url ) {
        if ( ! bes_find_matching_blocked_domain( $url, $domains ) ) {
            $clean_hints[] = $url;
        }
    }

    return $clean_hints;
}
add_filter( 'wp_resource_hints', 'bes_remove_resource_hints', 10, 2 );

// ۳.۶ مسدودسازی قطعی درخواست‌های HTTP خروجی (pre_http_request)
function bes_block_http_requests( $preempt, $args, $url ) {
    if ( ! bes_is_active() ) {
        return $preempt;
    }

    $domains = bes_get_blocked_domains();
    $matched = bes_find_matching_blocked_domain( $url, $domains );

    if ( $matched ) {
        // ثبت در لاگ
        bes_log_blocked_request( $url, $matched );

        return new WP_Error(
            'bes_blocked_external_service',
            sprintf( 'درخواست به %s توسط افزونه Block External Services مسدود شد.', esc_html( $matched ) ),
            array(
                'status'         => 403,
                'blocked_domain' => $matched,
                'url'            => $url,
            )
        );
    }

    return $preempt;
}
add_filter( 'pre_http_request', 'bes_block_http_requests', 10, 3 );

// ۳.۷ مسدودسازی ۱۰۰٪ محلی گراواتار بدون ارسال ریکوئست به سرورهای خارجی
function bes_get_offline_avatar_svg() {
    return 'data:image/svg+xml;utf8,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 24 24" fill="#a0a5aa"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>'
    );
}

function bes_disable_gravatar_data( $args, $id_or_email ) {
    if ( ! bes_is_active() ) {
        return $args;
    }
    $domains = bes_get_blocked_domains();
    if ( in_array( 'gravatar.com', $domains, true ) ) {
        $args['url']          = bes_get_offline_avatar_svg();
        $args['found_avatar'] = true;
    }
    return $args;
}
add_filter( 'pre_get_avatar_data', 'bes_disable_gravatar_data', 10, 2 );

function bes_disable_gravatar_html( $avatar, $id_or_email, $size, $default, $alt ) {
    if ( ! bes_is_active() ) {
        return $avatar;
    }
    $domains = bes_get_blocked_domains();
    if ( in_array( 'gravatar.com', $domains, true ) ) {
        $svg_url = bes_get_offline_avatar_svg();
        return '<img alt="' . esc_attr( $alt ) . '" src="' . esc_url( $svg_url ) . '" class="avatar avatar-' . esc_attr( $size ) . ' photo" height="' . esc_attr( $size ) . '" width="' . esc_attr( $size ) . '" loading="lazy" />';
    }
    return $avatar;
}
add_filter( 'get_avatar', 'bes_disable_gravatar_html', 10, 5 );

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
// ۵. نمایش پیام وضعیت در بالای پیشخوان
// ============================================
function bes_show_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // عدم نمایش در خود صفحه تنظیمات افزونه برای جلوگیری از تکرار
    $screen = get_current_screen();
    if ( $screen && 'settings_page_bes-blocked-domains' === $screen->id ) {
        return;
    }

    $is_active = bes_is_active();
    if ( ! $is_active ) {
        echo '<div class="notice notice-info is-dismissible">
            <p>ℹ️ <strong>افزونه مسدودسازی سرویس‌های خارجی موقتاً متوقف است.</strong> برای فعال‌سازی حالت اضطراری به <a href="' . esc_url( admin_url( 'options-general.php?page=bes-blocked-domains' ) ) . '">تنظیمات افزونه</a> مراجعه کنید.</p>
        </div>';
        return;
    }

    $domains      = bes_get_blocked_domains();
    $count        = count( $domains );
    $domains_list = implode( '، ', array_map( function( $d ) {
        return '<code>' . esc_html( $d ) . '</code>';
    }, array_slice( $domains, 0, 5 ) ) );

    if ( $count > 5 ) {
        $domains_list .= ' و ' . ( $count - 5 ) . ' دامنه دیگر';
    }

    echo '<div class="notice notice-warning is-dismissible">
        <p>⚠️ <strong>حالت اضطراری فعال است:</strong> درخواست‌های خارجی به ' . $count . ' دامنه مسدود می‌شود (' . $domains_list . '). <a href="' . esc_url( admin_url( 'options-general.php?page=bes-blocked-domains' ) ) . '">مدیریت دامنه‌ها و تنظیمات</a></p>
    </div>';
}
add_action( 'admin_notices', 'bes_show_admin_notice' );
