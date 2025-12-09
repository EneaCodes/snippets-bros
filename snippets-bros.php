<?php
/**
 * Plugin Name:       Snippets Bros
 * Description:       Professional snippet manager for PHP, HTML, CSS, JS with global header/footer code, safe mode, import/export, clone, categories, tags, and run-once execution.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Enea
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       snippets-bros
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Snippets_Bros_Safe_Executor {
    
    private $plugin;
    private $execution_timeout = 30;
    private $max_memory = '256M';
    
    public function __construct( $plugin ) {
        $this->plugin = $plugin;
    }
    
    public function execute_php( $snippet ) {
        $code       = $this->prepare_code( $snippet['content'] ?? '' );
        $snippet_id = $snippet['id'] ?? '';
        
        if ( empty( trim( $code ) ) ) {
            return false;
        }

        if ( ! $this->preflight_conflict_check( $code, $snippet_id ) ) {
            return false;
        }
        
        if ( $this->is_hook_based_code( $code ) ) {
            return $this->execute_hook_based_code( $code, $snippet_id );
        }

        return $this->execute_regular_code( $code, $snippet_id );
    }
    
    private function prepare_code( $code ) {
        $code = preg_replace( '/^\s*<\?(?:php)?\s*/', '', $code );
        $code = preg_replace( '/\?>\s*$/', '', $code );
        $code = trim( $code );
        return $code;
    }

    private function preflight_conflict_check( $code, $snippet_id ) {
        $conflicts = array();

        if ( preg_match_all( '/function\s+([a-zA-Z0-9_]+)\s*\(/', $code, $matches_functions ) ) {
            foreach ( $matches_functions[1] as $fn_name ) {
                if ( function_exists( $fn_name ) ) {
                    $conflicts[] = "function {$fn_name}()";
                }
            }
        }

        if ( preg_match_all( '/\bclass\s+([a-zA-Z0-9_]+)/', $code, $matches_classes ) ) {
            foreach ( $matches_classes[1] as $class_name ) {
                if ( class_exists( $class_name, false ) ) {
                    $conflicts[] = "class {$class_name}";
                }
            }
        }

        if ( preg_match_all( '/\binterface\s+([a-zA-Z0-9_]+)/', $code, $matches_interfaces ) ) {
            foreach ( $matches_interfaces[1] as $iface_name ) {
                if ( interface_exists( $iface_name, false ) ) {
                    $conflicts[] = "interface {$iface_name}";
                }
            }
        }

        if ( preg_match_all( '/\btrait\s+([a-zA-Z0-9_]+)/', $code, $matches_traits ) ) {
            foreach ( $matches_traits[1] as $trait_name ) {
                if ( trait_exists( $trait_name, false ) ) {
                    $conflicts[] = "trait {$trait_name}";
                }
            }
        }

        if ( preg_match_all( '/\bdefine\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/i', $code, $matches_consts ) ) {
            foreach ( $matches_consts[1] as $const_name ) {
                if ( defined( $const_name ) ) {
                    $conflicts[] = "constant {$const_name}";
                }
            }
        }

        if ( empty( $conflicts ) ) {
            return true;
        }

        $message = sprintf(
            'Execution blocked: snippet declares already existing %s. Snippet was disabled to prevent a fatal error.',
            implode( ', ', $conflicts )
        );

        if ( method_exists( $this->plugin, 'log_error' ) ) {
            $this->plugin->log_error( $snippet_id ?: 'unknown', $message );
        }

        if ( ! empty( $snippet_id ) && method_exists( $this->plugin, 'disable_snippet' ) ) {
            $this->plugin->disable_snippet( $snippet_id );
        }

        return false;
    }
    
    private function is_hook_based_code( $code ) {
        $hook_patterns = array(
            '/add_action\s*\(/i',
            '/add_filter\s*\(/i',
            '/do_action\s*\(/i',
            '/apply_filters\s*\(/i',
            '/remove_action\s*\(/i',
            '/remove_filter\s*\(/i',
        );
        
        foreach ( $hook_patterns as $pattern ) {
            if ( preg_match( $pattern, $code ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    private function execute_hook_based_code( $code, $snippet_id ) {
        try {
            $temp_file = tempnam( sys_get_temp_dir(), 'snippet_hook_' );
            
            $wrapped_code = '<?php
            try {
                ' . $code . '
            } catch (Exception $e) {
                error_log("Snippets Bros Hook Error: " . $e->getMessage());
            } catch (Error $e) {
                error_log("Snippets Bros Hook Error: " . $e->getMessage());
            }
            ';
            
            file_put_contents( $temp_file, $wrapped_code );
            include_once $temp_file;
            wp_delete_file( $temp_file );
            return true;
        } catch ( Throwable $e ) {
            $this->plugin->log_error(
                $snippet_id,
                'Hook-based execution failed: ' . $e->getMessage()
            );
            return false;
        }
    }
    
    private function execute_regular_code( $code, $snippet_id ) {
        try {
            $temp_file = tempnam( sys_get_temp_dir(), 'snippet_exec_' );

            $wrapped_code = '<?php
            return (function() {
                ob_start();
                $result = null;
                ' . $code . '
                $output = ob_get_clean();
                return $result !== null ? $result : $output;
            })();
            ';

            file_put_contents( $temp_file, $wrapped_code );
            $result = include $temp_file;
            wp_delete_file( $temp_file );
            return $result;
        } catch ( Throwable $e ) {
            $this->plugin->log_error(
                $snippet_id,
                'Regular execution failed: ' . $e->getMessage()
            );
            return false;
        }
    }
}

class Snippets_Bros_Simple {
    
    private static $instance = null;
    private $option_key               = 'snippets_bros_snippets';
    private $revisions_key            = 'snippets_bros_revisions';
    private $error_log_key            = 'snippets_bros_error_log';
    private $safe_mode_key            = 'snippets_bros_safe_mode';
    private $last_safe_mode_log_key   = 'snippets_bros_last_safe_mode_log';
    private $last_snippet_option_key  = 'snippets_bros_last_snippet_id';
    private $executor                 = null;
    
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'shutdown', array( $this, 'check_for_fatal_errors' ) );
        $this->executor = new Snippets_Bros_Safe_Executor( $this );
    }
    
    public function init() {
        $old_revisions = get_option( $this->revisions_key, false );
        if ( $old_revisions !== false && is_array( $old_revisions ) ) {
            foreach ( $old_revisions as $old_id => $revision_list ) {
                if ( is_array( $revision_list ) && ! empty( $revision_list ) ) {
                    $new_key = 'snippets_bros_rev_' . $old_id;
                    update_option( $new_key, $revision_list, false );
                }
            }
            delete_option( $this->revisions_key );
        }
        
        if ( isset( $_GET['snippets_bros_emergency'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'snippets_bros_emergency' ) ) {
                $this->emergency_recovery();
            }
        }
        
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'admin_menu' ) );
            add_action( 'admin_init', array( $this, 'admin_init' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
            add_action( 'wp_ajax_snippets_bros_bulk_action', array( $this, 'handle_bulk_actions' ) );
            add_action( 'wp_ajax_snippets_bros_export_snippet', array( $this, 'export_snippet' ) );
            add_action( 'wp_ajax_snippets_bros_clear_error_log', array( $this, 'clear_error_log' ) );
        }
        
        add_shortcode( 'snippets_bros', array( $this, 'shortcode_output' ) );
        
        if ( ! $this->is_safe_mode_enabled() ) {
            add_action( 'wp', array( $this, 'execute_frontend_snippets' ) );
            add_action( 'admin_init', array( $this, 'execute_admin_snippets' ) );
        }
    }
    
    public function is_safe_mode_enabled() {
        return (bool) get_option( $this->safe_mode_key, 0 );
    }
    
    public function enable_safe_mode( $log_message = true ) {
        if ( $this->is_safe_mode_enabled() ) {
            return;
        }
        
        update_option( $this->safe_mode_key, 1 );
        
        $snippets = $this->get_snippets();
        foreach ( $snippets as &$snippet ) {
            $snippet['enabled'] = 0;
        }
        $this->save_snippets( $snippets );
        
        if ( $log_message ) {
            $last_log = get_option( $this->last_safe_mode_log_key, 0 );
            if ( time() - $last_log > 3600 ) {
                $this->log_error( 'system', 'Safe mode enabled. All snippets disabled.' );
                update_option( $this->last_safe_mode_log_key, time() );
            }
        }
    }
    
    public function disable_safe_mode() {
        update_option( $this->safe_mode_key, 0 );
    }
    
    public function toggle_safe_mode() {
        if ( $this->is_safe_mode_enabled() ) {
            $this->disable_safe_mode();
            $this->log_error( 'system', 'Safe mode disabled by user.' );
        } else {
            $this->enable_safe_mode( true );
        }
    }
    
    public function check_for_fatal_errors() {
        $error = error_get_last();

        if ( ! $error ) {
            return;
        }

        $fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR );
        if ( ! in_array( $error['type'], $fatal_types, true ) ) {
            return;
        }

        $message = strtolower( $error['message'] ?? '' );
        $file    = strtolower( $error['file'] ?? '' );

        $indicators = array(
            'snippet_hook_',
            'snippets_bros',
            'snippets-bros',
            'create_function',
            'snippet_exec_',
        );

        $from_snippet = false;
        foreach ( $indicators as $needle ) {
            if ( strpos( $message, $needle ) !== false || strpos( $file, $needle ) !== false ) {
                $from_snippet = true;
                break;
            }
        }

        if ( ! $from_snippet ) {
            return;
        }

        $last_id = get_option( $this->last_snippet_option_key, '' );

        if ( ! empty( $last_id ) ) {
            $this->log_error(
                $last_id,
                'Fatal PHP error: ' . ( $error['message'] ?? 'unknown error' ) .
                ' in ' . ( $error['file'] ?? 'unknown file' ) .
                ' on line ' . ( $error['line'] ?? '0' )
            );

            $this->disable_snippet( $last_id );
        }

        $this->enable_safe_mode( true );
    }
    
    private function emergency_recovery() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }
        
        $this->enable_safe_mode( false );
        update_option( $this->error_log_key, array() );
        
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'               => 'snippets-bros',
                    'emergency_recovery' => '1',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
    
    private function validate_php_syntax( $code ) {
        if ( empty( trim( $code ) ) ) {
            return true;
        }
        
        $dangerous_patterns = array(
            '/\beval\s*\(\s*[\'"`\$]/i',
            '/\b(chmod|chown|rmdir)\s*\(\s*[\'"`\$]/i',
            '/@\s*ini_set\s*\(\s*[\'"]display_errors[\'"]/i',
        );
        
        foreach ( $dangerous_patterns as $pattern ) {
            if ( preg_match( $pattern, $code ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    public function get_snippets() {
        $snippets = get_option( $this->option_key, array() );
        return is_array( $snippets ) ? $snippets : array();
    }
    
    public function save_snippets( $snippets ) {
        update_option( $this->option_key, $snippets );
    }
    
    public function get_snippet( $id ) {
        $snippets = $this->get_snippets();
        foreach ( $snippets as $s ) {
            if ( (string) $s['id'] === (string) $id ) {
                return $s;
            }
        }
        return null;
    }
    
    private function generate_id() {
        $left  = substr( str_shuffle( 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' ), 0, 7 );
        $right = substr( str_shuffle( 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' ), 0, 7 );
        return $left . '.' . $right;
    }
    
    private function save_revision( $snippet ) {
        if ( empty( $snippet['id'] ) || ! isset( $snippet['content'] ) ) {
            return;
        }
        
        if ( empty( trim( $snippet['content'] ) ) ) {
            return;
        }
        
        $rev_key   = 'snippets_bros_rev_' . $snippet['id'];
        $revisions = get_option( $rev_key, array() );
        
        if ( ! empty( $revisions ) ) {
            $last_revision = $revisions[0];
            if ( $last_revision['content'] === $snippet['content'] ) {
                return;
            }
        }
        
        $revision_data = array(
            'snippet_id'    => $snippet['id'],
            'snippet_name'  => $snippet['name'] ?? __( 'Untitled', 'snippets-bros' ),
            'content'       => $snippet['content'],
            'modified_by'   => get_current_user_id(),
            'modified_date' => time(),
            'version'       => '1.0',
        );
        
        array_unshift( $revisions, $revision_data );
        $revisions = array_slice( $revisions, 0, 15 );
        
        update_option( $rev_key, $revisions, false );
    }
    
    public function get_revisions( $snippet_id ) {
        if ( empty( $snippet_id ) ) {
            return array();
        }
        $rev_key = 'snippets_bros_rev_' . $snippet_id;
        return get_option( $rev_key, array() );
    }
    
    private function sanitize_css( $css ) {
        if ( ! is_string( $css ) ) {
            return '';
        }

        $css = preg_replace( '/<style[^>]*>(.*?)<\/style>/is', '$1', $css );
        $css = str_replace( '</style>', '<\/style>', $css );
        
        $dangerous_patterns = array(
            '/expression\s*\(/i',
            '/javascript\s*:/i',
            '/data\s*:/i',
            '/vbscript\s*:/i',
            '/@import[^;]*;/i',
            '/url\s*\(\s*["\']?\s*javascript:/i',
        );
        
        $css = preg_replace( $dangerous_patterns, '', $css );
        $css = wp_strip_all_tags( $css );
        $css = html_entity_decode( $css, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $css = str_replace( "\0", '', $css );
        $css = mb_convert_encoding( $css, 'UTF-8', 'UTF-8' );
        
        return trim( $css );
    }

    private function sanitize_js( $js ) {
        if ( ! is_string( $js ) ) {
            return '';
        }

        $js = preg_replace( '/<script[^>]*>(.*?)<\/script>/is', '$1', $js );
        $js = str_replace( '</script>', '<\/script>', $js );
        
        $dangerous_patterns = array(
            '/javascript\s*:/i',
            '/data\s*:/i',
            '/vbscript\s*:/i',
            '/on\w+\s*=/i',
        );
        
        $js = preg_replace( $dangerous_patterns, '', $js );
        $js = html_entity_decode( $js, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $js = str_replace( "\0", '', $js );
        $js = mb_convert_encoding( $js, 'UTF-8', 'UTF-8' );
        
        return trim( $js );
    }
    
    private function sanitize_html( $html ) {
        if ( ! is_string( $html ) ) {
            return '';
        }
        
        if ( current_user_can( 'unfiltered_html' ) ) {
            return $html;
        }
        
        $dangerous_patterns = array(
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/<object[^>]*>.*?<\/object>/is',
            '/<embed[^>]*>/is',
            '/on\w+\s*=\s*["\'][^"\']*["\']/i',
        );
        
        $html = preg_replace( $dangerous_patterns, '', $html );
        $allowed_tags = wp_kses_allowed_html( 'post' );
        
        $allowed_tags['style'] = array(
            'type'  => true,
            'media' => true,
        );
        
        $allowed_tags['link'] = array(
            'rel'   => true,
            'href'  => true,
            'type'  => true,
            'media' => true,
        );
        
        $allowed_tags['meta'] = array(
            'name'     => true,
            'content'  => true,
            'property' => true,
        );
        
        $html = wp_kses( $html, $allowed_tags );
        $html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $html = str_replace( "\0", '', $html );
        $html = mb_convert_encoding( $html, 'UTF-8', 'UTF-8' );
        
        return trim( $html );
    }

    public function log_error( $snippet_id, $error_message ) {
        $error_log    = get_option( $this->error_log_key, array() );
        $current_time = time();
        $url          = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

        $found_index = null;

        foreach ( $error_log as $index => $error ) {
            if ( isset( $error['snippet_id'] ) && $error['snippet_id'] === $snippet_id ) {
                $prev_count                         = isset( $error['count'] ) ? intval( $error['count'] ) : 1;
                $error_log[ $index ]['count']       = $prev_count + 1;
                $error_log[ $index ]['message']     = sanitize_text_field( $error_message );
                $error_log[ $index ]['timestamp']   = $current_time;
                $error_log[ $index ]['url']         = $url;
                $found_index                        = $index;
                break;
            }
        }

        if ( null !== $found_index ) {
            $entry = $error_log[ $found_index ];
            unset( $error_log[ $found_index ] );
            array_unshift( $error_log, $entry );
        } else {
            $error_entry = array(
                'snippet_id' => $snippet_id,
                'message'    => sanitize_text_field( $error_message ),
                'timestamp'  => $current_time,
                'url'        => $url,
                'count'      => 1,
            );
            array_unshift( $error_log, $error_entry );
        }

        $error_log = array_slice( $error_log, 0, 20 );
        update_option( $this->error_log_key, $error_log );
    }
    
    public function get_error_log() {
        return get_option( $this->error_log_key, array() );
    }
    
    public function admin_menu() {
        add_menu_page(
            __( 'Snippets Bros', 'snippets-bros' ),
            __( 'Snippets Bros', 'snippets-bros' ),
            'manage_options',
            'snippets-bros',
            array( $this, 'admin_page_main' ),
            'dashicons-editor-code',
            65
        );
        
        add_submenu_page(
            'snippets-bros',
            __( 'All Snippets', 'snippets-bros' ),
            __( 'All Snippets', 'snippets-bros' ),
            'manage_options',
            'snippets-bros',
            array( $this, 'admin_page_main' )
        );
        
        add_submenu_page(
            'snippets-bros',
            __( 'Add New Snippet', 'snippets-bros' ),
            __( 'Add New', 'snippets-bros' ),
            'manage_options',
            'snippets-bros-add',
            array( $this, 'admin_page_add' )
        );
        
        add_submenu_page(
            'snippets-bros',
            __( 'Import/Export', 'snippets-bros' ),
            __( 'Import/Export', 'snippets-bros' ),
            'manage_options',
            'snippets-bros-import-export',
            array( $this, 'admin_page_import_export' )
        );
        
        $error_log        = $this->get_error_log();
        $error_menu_title = __( 'Error Log', 'snippets-bros' );
        if ( count( $error_log ) > 0 ) {
            $error_menu_title .= ' <span style="color: #dc2626; font-weight: bold;" title="' . count( $error_log ) . ' error(s)">🚨</span>';
        }
        
        add_submenu_page(
            'snippets-bros',
            __( 'Error Log', 'snippets-bros' ),
            $error_menu_title,
            'manage_options',
            'snippets-bros-error-log',
            array( $this, 'admin_page_error_log' )
        );
    }
    
    public function admin_init() {
        $this->handle_form_submissions();
    }
    
    public function admin_scripts( $hook ) {
        if ( strpos( $hook, 'snippets-bros' ) === false ) {
            return;
        }
        
        $css_version = filemtime( plugin_dir_path( __FILE__ ) . 'admin-style.css' );
        wp_enqueue_style(
            'snippets-bros-admin-css',
            plugin_dir_url( __FILE__ ) . 'admin-style.css',
            array(),
            $css_version
        );
        
        $is_edit_page = false;
        if ( isset( $_GET['edit'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'edit_snippet_action' ) ) {
                $is_edit_page = true;
            }
        }
        
        if ( 'snippets-bros_page_snippets-bros-add' === $hook || $is_edit_page ) {
            wp_enqueue_code_editor(
                array(
                    'type' => 'text/x-php',
                )
            );
            wp_enqueue_script( 'code-editor' );
            wp_enqueue_style( 'code-editor' );
        }
        
        $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'admin-script.js' );
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script(
            'snippets-bros-admin-js',
            plugin_dir_url( __FILE__ ) . 'admin-script.js',
            array( 'jquery' ),
            $js_version,
            true
        );
        
        wp_localize_script(
            'snippets-bros-admin-js',
            'snippetsBros',
            array(
                'nonce'             => wp_create_nonce( 'snippets_bros_bulk_actions' ),
                'ajaxurl'           => admin_url( 'admin-ajax.php' ),
                'export_nonce'      => wp_create_nonce( 'snippets_bros_export_snippet' ),
                'clear_log_nonce'   => wp_create_nonce( 'snippets_bros_clear_error_log' ),
                'saving'            => __( 'Saving...', 'snippets-bros' ),
                'processing'        => __( 'Processing...', 'snippets-bros' ),
                'safe_mode_enabled' => $this->is_safe_mode_enabled() ? 1 : 0,
            )
        );
    }
    
    private function handle_form_submissions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'snippets-bros' ) );
        }
        
        if ( isset( $_POST['snippets_bros_action'] ) && 'save_snippet' === $_POST['snippets_bros_action'] ) {
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'snippets_bros_save_snippet' ) ) {
                wp_die( esc_html__( 'Security check failed.', 'snippets-bros' ) );
            }
            
            $id       = isset( $_POST['snippets_bros_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snippets_bros_id'] ) ) : '';
            $name     = isset( $_POST['snippets_bros_name'] ) ? sanitize_text_field( wp_unslash( $_POST['snippets_bros_name'] ) ) : '';
            
            if ( empty( $name ) ) {
                wp_die( esc_html__( 'Snippet name is required.', 'snippets-bros' ) );
            }
            
            $type      = isset( $_POST['snippets_bros_type'] ) ? sanitize_text_field( wp_unslash( $_POST['snippets_bros_type'] ) ) : 'php';
            $scope     = isset( $_POST['snippets_bros_scope'] ) ? sanitize_text_field( wp_unslash( $_POST['snippets_bros_scope'] ) ) : 'everywhere';
            $enabled   = isset( $_POST['snippets_bros_enabled'] ) ? 1 : 0;
            $priority  = isset( $_POST['snippets_bros_priority'] ) ? intval( $_POST['snippets_bros_priority'] ) : 10;
            $category  = isset( $_POST['snippets_bros_category'] ) ? sanitize_text_field( wp_unslash( $_POST['snippets_bros_category'] ) ) : '';
            $run_once  = isset( $_POST['snippets_bros_run_once'] ) ? 1 : 0;
            
            $conditions = array();
            if ( isset( $_POST['snippets_bros_conditions'] ) && is_array( $_POST['snippets_bros_conditions'] ) ) {
                $conditions_input = wp_unslash( $_POST['snippets_bros_conditions'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $conditions       = $this->sanitize_conditions( $conditions_input );
            }
            
            $content = '';
            if ( isset( $_POST['snippets_bros_content'] ) ) {
                $content_raw = wp_unslash( $_POST['snippets_bros_content'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                
                if ( current_user_can( 'unfiltered_html' ) ) {
                    $content = $content_raw;
                } else {
                    if ( 'css' === $type ) {
                        $content = $this->sanitize_css( $content_raw );
                    } elseif ( 'js' === $type ) {
                        $content = $this->sanitize_js( $content_raw );
                    } else {
                        $content = wp_kses_post( $content_raw );
                    }
                }
                
                if ( 'php' === $type && ! $this->validate_php_syntax( $content ) ) {
                    $enabled = 0;
                    $this->log_error( $id ?: 'new', 'PHP syntax validation failed. Snippet disabled.' );
                }
            }
            
            $tags     = array();
            $tags_raw = isset( $_POST['snippets_bros_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['snippets_bros_tags'] ) ) : '';
            if ( ! empty( $tags_raw ) ) {
                $tags = array_map( 'trim', explode( ',', $tags_raw ) );
            }
            
            $snippets = $this->get_snippets();
            $is_new   = empty( $id );
            
            if ( $is_new ) {
                $snippet_data = array(
                    'id'            => $this->generate_id(),
                    'name'          => $name,
                    'type'          => $type,
                    'scope'         => $scope,
                    'enabled'       => $enabled,
                    'priority'      => $priority,
                    'category'      => $category,
                    'tags'          => $tags,
                    'content'       => $content,
                    'run_once'      => $run_once,
                    'conditions'    => $conditions,
                    'created_date'  => time(),
                    'modified_date' => time(),
                );
                $snippets[] = $snippet_data;
                $this->save_revision( $snippet_data );
            } else {
                foreach ( $snippets as &$s ) {
                    if ( (string) $s['id'] === $id ) {
                        $this->save_revision( $s );
                        
                        $s['name']          = $name;
                        $s['type']          = $type;
                        $s['scope']         = $scope;
                        $s['enabled']       = $enabled;
                        $s['priority']      = $priority;
                        $s['category']      = $category;
                        $s['tags']          = $tags;
                        $s['content']       = $content;
                        $s['run_once']      = $run_once;
                        $s['conditions']    = $conditions;
                        $s['modified_date'] = time();
                        break;
                    }
                }
            }
            
            $this->save_snippets( $snippets );
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'         => 'snippets-bros',
                        'saved'        => '1',
                        '_wpnonce_msg' => wp_create_nonce( 'snippets_bros_success_msg' ),
                    ),
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }
        
        if ( isset( $_POST['snippets_bros_action'] ) && 'import_snippets' === $_POST['snippets_bros_action'] ) {
            check_admin_referer( 'snippets_bros_import' );
            
            if ( isset( $_FILES['snippets_file'] ) && ! empty( $_FILES['snippets_file']['tmp_name'] ) ) {
                if ( isset( $_FILES['snippets_file']['size'] ) && $_FILES['snippets_file']['size'] > 10 * 1024 * 1024 ) {
                    wp_safe_redirect(
                        add_query_arg(
                            array(
                                'page'               => 'snippets-bros-import-export',
                                'import_error'       => '1',
                                'import_error_type'  => 'size',
                                '_wpnonce_msg'       => wp_create_nonce( 'snippets_bros_error_msg' ),
                            ),
                            admin_url( 'admin.php' )
                        )
                    );
                    exit;
                }
                
                $file_name = isset( $_FILES['snippets_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['snippets_file']['name'] ) ) : '';
                $file_type = wp_check_filetype( $file_name, array( 'json' => 'application/json' ) );
                if ( $file_type['ext'] !== 'json' ) {
                    wp_safe_redirect(
                        add_query_arg(
                            array(
                                'page'               => 'snippets-bros-import-export',
                                'import_error'       => '1',
                                'import_error_type'  => 'type',
                                '_wpnonce_msg'       => wp_create_nonce( 'snippets_bros_error_msg' ),
                            ),
                            admin_url( 'admin.php' )
                        )
                    );
                    exit;
                }
                
                $json_data   = file_get_contents( sanitize_text_field( wp_unslash( $_FILES['snippets_file']['tmp_name'] ) ) );
                $import_data = json_decode( $json_data, true );
                
                if ( $import_data && isset( $import_data['snippets'] ) ) {
                    $existing_snippets = $this->get_snippets();
                    $imported_count    = 0;
                    
                    foreach ( $import_data['snippets'] as $snippet ) {
                        $snippet['enabled']       = 0;
                        $snippet['id']            = $this->generate_id();
                        $snippet['created_date']  = time();
                        $snippet['modified_date'] = time();
                        $existing_snippets[]      = $snippet;
                        $imported_count++;
                    }
                    
                    $this->save_snippets( $existing_snippets );
                    wp_safe_redirect(
                        add_query_arg(
                            array(
                                'page'         => 'snippets-bros-import-export',
                                'imported'     => $imported_count,
                                '_wpnonce_msg' => wp_create_nonce( 'snippets_bros_success_msg' ),
                            ),
                            admin_url( 'admin.php' )
                        )
                    );
                    exit;
                }
            }
            
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'         => 'snippets-bros-import-export',
                        'import_error' => '1',
                        '_wpnonce_msg' => wp_create_nonce( 'snippets_bros_error_msg' ),
                    ),
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }
        
        if ( isset( $_POST['snippets_bros_action'] ) && 'export_snippets' === $_POST['snippets_bros_action'] ) {
            check_admin_referer( 'snippets_bros_export' );
            $this->export_all_snippets();
            exit;
        }
        
        if ( isset( $_GET['snippets_bros_action'] ) && isset( $_GET['_wpnonce'] ) ) {
            $action = sanitize_text_field( wp_unslash( $_GET['snippets_bros_action'] ) );
            $nonce  = sanitize_key( wp_unslash( $_GET['_wpnonce'] ) );
            
            if ( 'toggle_safe_mode' === $action ) {
                if ( wp_verify_nonce( $nonce, 'snippets_bros_toggle_safe_mode' ) ) {
                    $this->toggle_safe_mode();
                    
                    wp_safe_redirect(
                        add_query_arg(
                            array(
                                'page'               => 'snippets-bros',
                                'safe_mode_toggled'  => '1',
                                '_wpnonce_msg'       => wp_create_nonce( 'snippets_bros_success_msg' ),
                            ),
                            admin_url( 'admin.php' )
                        )
                    );
                    exit;
                }
            }
            
            $id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
            
            if ( empty( $id ) && isset( $_GET['edit'] ) && 'restore_revision' === $action ) {
                $id = sanitize_text_field( wp_unslash( $_GET['edit'] ) );
            }
            
            if ( ! empty( $id ) ) {
                switch ( $action ) {
                    case 'delete':
                        if ( wp_verify_nonce( $nonce, 'snippets_bros_delete_' . $id ) ) {
                            $this->delete_snippet( $id );
                        }
                        break;
                        
                    case 'toggle':
                        if ( wp_verify_nonce( $nonce, 'snippets_bros_toggle_' . $id ) ) {
                            $this->toggle_snippet( $id );
                        }
                        break;
                        
                    case 'clone':
                        if ( wp_verify_nonce( $nonce, 'snippets_bros_clone_' . $id ) ) {
                            $this->clone_snippet( $id );
                        }
                        break;
                        
                    case 'restore_revision':
                        if ( wp_verify_nonce( $nonce, 'snippets_bros_restore_' . $id ) ) {
                            $revision_index = isset( $_GET['revision'] ) ? intval( $_GET['revision'] ) : 0;
                            $this->restore_revision( $id, $revision_index );
                        }
                        break;
                }
            }
        }
    }
    
    private function sanitize_conditions( $conditions ) {
        if ( ! is_array( $conditions ) ) {
            return array();
        }
        
        $sanitized = array();
        
        if ( isset( $conditions['user_status'] ) ) {
            $sanitized['user_status'] = sanitize_text_field( $conditions['user_status'] );
        }
        
        if ( isset( $conditions['device_type'] ) ) {
            $sanitized['device_type'] = sanitize_text_field( $conditions['device_type'] );
        }
        
        if ( isset( $conditions['url_patterns'] ) ) {
            $patterns = trim( $conditions['url_patterns'] );
            if ( ! empty( $patterns ) ) {
                $patterns_array = explode( "\n", $patterns );
                $patterns_array = array_map( 'trim', $patterns_array );
                $patterns_array = array_filter( $patterns_array );
                $patterns_array = array_map( 'sanitize_text_field', $patterns_array );
                $sanitized['url_patterns'] = $patterns_array;
            }
        }
        
        return $sanitized;
    }
    
    private function delete_snippet( $id ) {
        $snippets = $this->get_snippets();
        $snippets = array_filter(
            $snippets,
            function( $s ) use ( $id ) {
                return (string) $s['id'] !== $id;
            }
        );
        $this->save_snippets( array_values( $snippets ) );
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'         => 'snippets-bros',
                    'deleted'      => '1',
                    '_wpnonce_msg' => wp_create_nonce( 'snippets_bros_success_msg' ),
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
    
    private function toggle_snippet( $id ) {
        $snippets = $this->get_snippets();
        foreach ( $snippets as &$s ) {
            if ( (string) $s['id'] === $id ) {
                $s['enabled']       = $s['enabled'] ? 0 : 1;
                $s['modified_date'] = time();
                break;
            }
        }
        $this->save_snippets( $snippets );
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'         => 'snippets-bros',
                    'toggled'      => '1',
                    '_wpnonce_msg' => wp_create_nonce( 'snippets_bros_success_msg' ),
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
    
    private function clone_snippet( $id ) {
        $snippets = $this->get_snippets();
        foreach ( $snippets as $s ) {
            if ( (string) $s['id'] === $id ) {
                $clone                 = $s;
                $clone['id']           = $this->generate_id();
                $clone['name']        .= ' ' . __( '(Clone)', 'snippets-bros' );
                $clone['created_date'] = time();
                $clone['modified_date']= time();
                $snippets[]            = $clone;
                break;
            }
        }
        $this->save_snippets( $snippets );
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'         => 'snippets-bros',
                    'cloned'       => '1',
                    '_wpnonce_msg' => wp_create_nonce( 'snippets_bros_success_msg' ),
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
    
    private function restore_revision( $snippet_id, $revision_index ) {
        $revisions = $this->get_revisions( $snippet_id );
        
        if ( isset( $revisions[ $revision_index ] ) ) {
            $revision = $revisions[ $revision_index ];
            $snippets = $this->get_snippets();
            
            foreach ( $snippets as &$s ) {
                if ( (string) $s['id'] === $snippet_id ) {
                    $this->save_revision( $s );
                    
                    $s['content']       = $revision['content'];
                    $s['modified_date'] = time();
                    break;
                }
            }
            
            $this->save_snippets( $snippets );
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'              => 'snippets-bros-add',
                        'edit'              => $snippet_id,
                        'revision_restored' => '1',
                        '_wpnonce'          => wp_create_nonce( 'edit_snippet_action' ),
                        '_wpnonce_msg'      => wp_create_nonce( 'snippets_bros_success_msg' ),
                    ),
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }
        
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'          => 'snippets-bros-add',
                    'edit'          => $snippet_id,
                    'restore_error' => '1',
                    '_wpnonce'      => wp_create_nonce( 'edit_snippet_action' ),
                    '_wpnonce_msg'  => wp_create_nonce( 'snippets_bros_error_msg' ),
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
    
    public function handle_bulk_actions() {
        check_ajax_referer( 'snippets_bros_bulk_actions', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'snippets-bros' ) );
        }
        
        $action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
        $ids    = isset( $_POST['snippet_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['snippet_ids'] ) ) : array();
        
        if ( empty( $action ) || empty( $ids ) ) {
            wp_send_json_error( __( 'Invalid parameters.', 'snippets-bros' ) );
        }
        
        $snippets = $this->get_snippets();
        $updated  = false;
        
        foreach ( $snippets as &$s ) {
            if ( in_array( (string) $s['id'], $ids, true ) ) {
                switch ( $action ) {
                    case 'enable':
                        $s['enabled']       = 1;
                        $s['modified_date'] = time();
                        $updated            = true;
                        break;
                    case 'disable':
                        $s['enabled']       = 0;
                        $s['modified_date'] = time();
                        $updated            = true;
                        break;
                    case 'delete':
                        $s['_delete']       = true;
                        $updated            = true;
                        break;
                    case 'export':
                        break;
                }
            }
        }
        
        if ( 'delete' === $action ) {
            $snippets = array_filter(
                $snippets,
                function( $s ) {
                    return empty( $s['_delete'] );
                }
            );
        }
        
        if ( $updated && 'export' !== $action ) {
            $this->save_snippets( array_values( $snippets ) );
            wp_send_json_success();
        } else {
            wp_send_json_error( __( 'No changes made.', 'snippets-bros' ) );
        }
    }
    
    private function export_all_snippets() {
        $snippets = $this->get_snippets();
        
        $export_data = array(
            'version'     => '1.6.0',
            'export_date' => gmdate( 'Y-m-d H:i:s' ),
            'snippets'    => $snippets,
        );
        
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="snippets-bros-export-' . gmdate( 'Y-m-d' ) . '.json"' );
        echo wp_json_encode( $export_data, JSON_PRETTY_PRINT );
        exit;
    }
    
    public function export_snippet() {
        check_ajax_referer( 'snippets_bros_export_snippet', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'snippets-bros' ) );
        }
        
        $ids = array();
        
        if ( isset( $_POST['snippet_ids'] ) && is_array( $_POST['snippet_ids'] ) ) {
            $ids = array_map( 'sanitize_text_field', wp_unslash( $_POST['snippet_ids'] ) );
        } elseif ( isset( $_POST['snippet_id'] ) ) {
            $ids = array( sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) ) );
        } else {
            wp_send_json_error( __( 'No snippet ID(s) provided.', 'snippets-bros' ) );
        }
        
        $snippets_to_export = array();
        foreach ( $ids as $id ) {
            $snippet = $this->get_snippet( $id );
            if ( $snippet ) {
                $snippets_to_export[] = $snippet;
            }
        }
        
        if ( empty( $snippets_to_export ) ) {
            wp_send_json_error( __( 'No valid snippets found.', 'snippets-bros' ) );
        }
        
        $export_data = array(
            'version'     => '1.6.0',
            'export_date' => gmdate( 'Y-m-d H:i:s' ),
            'snippets'    => $snippets_to_export,
        );
        
        if ( count( $snippets_to_export ) === 1 ) {
            $snippet  = $snippets_to_export[0];
            $filename = 'snippet-' . sanitize_title( $snippet['name'] ) . '-' . gmdate( 'Y-m-d' ) . '.json';
        } else {
            $filename = 'snippets-bulk-export-' . gmdate( 'Y-m-d' ) . '.json';
        }
        
        wp_send_json_success(
            array(
                'filename' => $filename,
                'content'  => wp_json_encode( $export_data, JSON_PRETTY_PRINT ),
            )
        );
    }
    
    public function clear_error_log() {
        check_ajax_referer( 'snippets_bros_clear_error_log', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'snippets-bros' ) );
        }
        
        update_option( $this->error_log_key, array() );
        wp_send_json_success();
    }
    
    public function admin_page_main() {
        include_once plugin_dir_path( __FILE__ ) . 'admin-pages.php';
        snippets_bros_admin_page_main( $this );
    }
    
    public function admin_page_add() {
        include_once plugin_dir_path( __FILE__ ) . 'admin-pages.php';
        snippets_bros_admin_page_add( $this );
    }
    
    public function admin_page_import_export() {
        include_once plugin_dir_path( __FILE__ ) . 'admin-pages.php';
        snippets_bros_admin_page_import_export( $this );
    }
    
    public function admin_page_error_log() {
        include_once plugin_dir_path( __FILE__ ) . 'admin-pages.php';
        snippets_bros_admin_page_error_log( $this );
    }
    
    public function execute_frontend_snippets() {
        if ( is_admin() || $this->is_safe_mode_enabled() ) {
            return;
        }
        
        $snippets = $this->get_snippets();
        
        usort(
            $snippets,
            function( $a, $b ) {
                return ( $a['priority'] ?? 10 ) - ( $b['priority'] ?? 10 );
            }
        );
        
        foreach ( $snippets as $snippet ) {
            if ( empty( $snippet['enabled'] ) ) {
                continue;
            }
            
            if ( ! $this->check_conditions( $snippet ) ) {
                continue;
            }
            
            $scope = $snippet['scope'] ?? 'everywhere';
            
            if ( 'admin' === $scope || 'shortcode' === $scope ) {
                continue;
            }
            
            $this->execute_snippet( $snippet );
        }
    }
    
    public function execute_admin_snippets() {
        if ( ! is_admin() || $this->is_safe_mode_enabled() ) {
            return;
        }
        
        $snippets = $this->get_snippets();
        
        usort(
            $snippets,
            function( $a, $b ) {
                return ( $a['priority'] ?? 10 ) - ( $b['priority'] ?? 10 );
            }
        );
        
        foreach ( $snippets as $snippet ) {
            if ( empty( $snippet['enabled'] ) ) {
                continue;
            }
            
            if ( ! $this->check_conditions( $snippet ) ) {
                continue;
            }
            
            $scope = $snippet['scope'] ?? 'everywhere';
            
            if ( 'frontend' === $scope || 'shortcode' === $scope ) {
                continue;
            }
            
            $this->execute_snippet( $snippet );
        }
    }
    
    private function check_conditions( $snippet ) {
        $conditions = $snippet['conditions'] ?? array();
        
        if ( empty( $conditions ) ) {
            return true;
        }
        
        if ( ! empty( $conditions['user_status'] ) ) {
            $is_logged_in = is_user_logged_in();
            
            if ( 'logged_in' === $conditions['user_status'] && ! $is_logged_in ) {
                return false;
            }
            
            if ( 'logged_out' === $conditions['user_status'] && $is_logged_in ) {
                return false;
            }
        }
        
        if ( ! empty( $conditions['url_patterns'] ) && is_array( $conditions['url_patterns'] ) ) {
            $current_url = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
            $matched     = false;
            
            foreach ( $conditions['url_patterns'] as $pattern ) {
                $pattern = trim( $pattern );
                if ( empty( $pattern ) ) {
                    continue;
                }
                
                if ( strpos( $pattern, '*' ) !== false ) {
                    $pattern_regex = str_replace( '\*', '.*', preg_quote( $pattern, '/' ) );
                    $pattern_regex = '/^' . $pattern_regex . '$/i';
                    
                    if ( preg_match( $pattern_regex, $current_url ) ) {
                        $matched = true;
                        break;
                    }
                } else {
                    if ( strpos( $current_url, $pattern ) !== false ) {
                        $matched = true;
                        break;
                    }
                }
            }
            
            if ( ! $matched ) {
                return false;
            }
        }
        
        if ( ! empty( $conditions['device_type'] ) ) {
            $is_mobile = wp_is_mobile();
            
            if ( 'mobile' === $conditions['device_type'] && ! $is_mobile ) {
                return false;
            }
            
            if ( 'desktop' === $conditions['device_type'] && $is_mobile ) {
                return false;
            }
        }
        
        return true;
    }
    
    private function execute_snippet( $snippet ) {
        $type       = $snippet['type'] ?? 'php';
        $content    = $snippet['content'] ?? '';
        $priority   = intval( $snippet['priority'] ?? 10 );
        $run_once   = ! empty( $snippet['run_once'] );
        $snippet_id = $snippet['id'] ?? '';
        
        if ( empty( $content ) ) {
            return;
        }

        if ( ! empty( $snippet_id ) ) {
            update_option( $this->last_snippet_option_key, $snippet_id, false );
        }
        
        switch ( $type ) {
            case 'php':
                try {
                    $result = $this->executor->execute_php( $snippet );
                    
                    if ( $result !== false && $run_once ) {
                        $this->disable_snippet( $snippet['id'] );
                    }
                } catch ( Exception $e ) {
                    $this->log_error( $snippet['id'], 'PHP Exception: ' . $e->getMessage() );
                    $this->disable_snippet( $snippet['id'] );
                } catch ( Error $e ) {
                    $this->log_error( $snippet['id'], 'PHP Error: ' . $e->getMessage() );
                    $this->disable_snippet( $snippet['id'] );
                }
                break;
                
            case 'css':
                $hook = is_admin() ? 'admin_head' : 'wp_head';
                add_action(
                    $hook,
                    function() use ( $content, $snippet, $run_once ) {
                        $sanitized_css = $this->sanitize_css( $content );
                        echo "\n<!-- Snippets Bros CSS: " . esc_html( $snippet['name'] ) . " -->\n";
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is sanitized in sanitize_css()
                        echo '<style>' . $sanitized_css . '</style>';
                        echo "\n<!-- End Snippets Bros CSS -->\n";
                        
                        if ( $run_once ) {
                            $this->disable_snippet( $snippet['id'] );
                        }
                    },
                    $priority
                );
                break;
                
            case 'js':
                $hook = is_admin() ? 'admin_footer' : 'wp_footer';
                add_action(
                    $hook,
                    function() use ( $content, $snippet, $run_once ) {
                        $sanitized_js = $this->sanitize_js( $content );
                        echo "\n<!-- Snippets Bros JS: " . esc_html( $snippet['name'] ) . " -->\n";
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JS is sanitized in sanitize_js()
                        echo '<script>' . $sanitized_js . '</script>';
                        echo "\n<!-- End Snippets Bros JS -->\n";
                        
                        if ( $run_once ) {
                            $this->disable_snippet( $snippet['id'] );
                        }
                    },
                    $priority
                );
                break;
                
            case 'html':
                $hook = is_admin() ? 'admin_footer' : 'wp_footer';
                add_action(
                    $hook,
                    function() use ( $content, $snippet, $run_once ) {
                        $sanitized_html = $this->sanitize_html( $content );
                        echo "\n<!-- Snippets Bros HTML: " . esc_html( $snippet['name'] ) . " -->\n";
                        echo wp_kses_post( $sanitized_html );
                        echo "\n<!-- End Snippets Bros HTML -->\n";
                        
                        if ( $run_once ) {
                            $this->disable_snippet( $snippet['id'] );
                        }
                    },
                    $priority
                );
                break;
                
            case 'header':
                $hook = is_admin() ? 'admin_head' : 'wp_head';
                add_action(
                    $hook,
                    function() use ( $content, $snippet, $run_once ) {
                        $sanitized_html = $this->sanitize_html( $content );
                        echo "\n<!-- Snippets Bros Header: " . esc_html( $snippet['name'] ) . " -->\n";
                        echo wp_kses_post( $sanitized_html );
                        echo "\n<!-- End Snippets Bros Header -->\n";
                        
                        if ( $run_once ) {
                            $this->disable_snippet( $snippet['id'] );
                        }
                    },
                    $priority
                );
                break;
                
            case 'footer':
                $hook = is_admin() ? 'admin_footer' : 'wp_footer';
                add_action(
                    $hook,
                    function() use ( $content, $snippet, $run_once ) {
                        $sanitized_html = $this->sanitize_html( $content );
                        echo "\n<!-- Snippets Bros Footer: " . esc_html( $snippet['name'] ) . " -->\n";
                        echo wp_kses_post( $sanitized_html );
                        echo "\n<!-- End Snippets Bros Footer -->\n";
                        
                        if ( $run_once ) {
                            $this->disable_snippet( $snippet['id'] );
                        }
                    },
                    $priority
                );
                break;
        }

        update_option( $this->last_snippet_option_key, '', false );
    }
    
    public function disable_snippet( $id ) {
        $snippets = $this->get_snippets();
        foreach ( $snippets as &$s ) {
            if ( (string) $s['id'] === $id ) {
                $s['enabled']       = 0;
                $s['modified_date'] = time();
                break;
            }
        }
        $this->save_snippets( $snippets );
    }
    
    public function shortcode_output( $atts ) {
        if ( $this->is_safe_mode_enabled() ) {
            return '';
        }
        
        $atts = shortcode_atts(
            array(
                'id' => '',
            ),
            $atts
        );
        
        if ( empty( $atts['id'] ) ) {
            return '';
        }
        
        $snippet = $this->get_snippet( $atts['id'] );
        if ( ! $snippet || empty( $snippet['enabled'] ) ) {
            return '';
        }
        
        if ( ! $this->check_conditions( $snippet ) ) {
            return '';
        }
        
        $type     = $snippet['type'] ?? 'html';
        $content  = $snippet['content'] ?? '';
        $run_once = ! empty( $snippet['run_once'] );
        
        ob_start();
        
        switch ( $type ) {
            case 'php':
                if ( ! is_admin() ) {
                    try {
                        $result = $this->executor->execute_php( $snippet );
                        
                        if ( $result !== false && $run_once ) {
                            $this->disable_snippet( $snippet['id'] );
                        }
                    } catch ( Exception $e ) {
                        $this->log_error( $snippet['id'], 'Shortcode PHP Exception: ' . $e->getMessage() );
                    } catch ( Error $e ) {
                        $this->log_error( $snippet['id'], 'Shortcode PHP Error: ' . $e->getMessage() );
                    }
                }
                break;
                
            case 'css':
                $css = $this->sanitize_css( $content );
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is sanitized in sanitize_css()
                echo '<style>' . $css . '</style>';
                
                if ( $run_once ) {
                    $this->disable_snippet( $snippet['id'] );
                }
                break;
                
            case 'js':
                $js = $this->sanitize_js( $content );
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JS is sanitized in sanitize_js()
                echo '<script>' . $js . '</script>';
                
                if ( $run_once ) {
                    $this->disable_snippet( $snippet['id'] );
                }
                break;
                
            default:
                $html = $this->sanitize_html( $content );
                echo wp_kses_post( $html );
                
                if ( $run_once ) {
                    $this->disable_snippet( $snippet['id'] );
                }
                break;
        }
        
        return ob_get_clean();
    }
}

function snippets_bros_init() {
    return Snippets_Bros_Simple::instance();
}
add_action( 'plugins_loaded', 'snippets_bros_init' );

register_activation_hook(
    __FILE__,
    function() {
        update_option( 'snippets_bros_safe_mode', 1 );
        
        if ( ! get_option( 'snippets_bros_snippets' ) ) {
            update_option( 'snippets_bros_snippets', array() );
        }
        
        if ( ! get_option( 'snippets_bros_error_log' ) ) {
            update_option( 'snippets_bros_error_log', array() );
        }
        
        add_option( 'snippets_bros_activation_notice', 1 );
    }
);

register_deactivation_hook(
    __FILE__,
    function() {
        $plugin = Snippets_Bros_Simple::instance();
        
        update_option( 'snippets_bros_safe_mode', 1 );
        
        $snippets = $plugin->get_snippets();
        foreach ( $snippets as &$snippet ) {
            $snippet['enabled'] = 0;
        }
        $plugin->save_snippets( $snippets );
    }
);

register_uninstall_hook( __FILE__, 'snippets_bros_uninstall' );

function snippets_bros_uninstall() {
    $options = array(
        'snippets_bros_snippets',
        'snippets_bros_safe_mode',
        'snippets_bros_error_log',
        'snippets_bros_activation_notice',
        'snippets_bros_last_safe_mode_log',
        'snippets_bros_deactivated_at',
        'snippets_bros_reenabled_notice',
    );
    
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $revision_options = $wpdb->get_col(
        "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'snippets_bros_rev_%'"
    );
    
    foreach ( $revision_options as $option ) {
        delete_option( $option );
    }
    
    foreach ( $options as $option ) {
        delete_option( $option );
    }
}

add_action(
    'admin_notices',
    function() {
        if ( get_option( 'snippets_bros_activation_notice' ) ) {
            ?>
        <div class="notice notice-info">
            <p>
                <strong>Snippets Bros activated!</strong> Safe mode is enabled by default for safety. 
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=snippets-bros' ) ); ?>">Go to Snippets Bros</a> to manage your snippets.
            </p>
            <?php delete_option( 'snippets_bros_activation_notice' ); ?>
        </div>
        <?php
        }
    }
);

add_action(
    'admin_footer',
    function() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $nonce = wp_create_nonce( 'snippets_bros_emergency' );
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('body').append(
                '<div id="snippets-bros-emergency" style="display:none;">' +
                '<a href="<?php echo esc_url( add_query_arg( array( 'snippets_bros_emergency' => '1', '_wpnonce' => $nonce ), admin_url( 'index.php' ) ) ); ?>" ' +
                'onclick="return confirm(\'This will enable safe mode and disable all snippets. Continue?\')">' +
                'Snippets Bros Emergency Recovery</a>' +
                '</div>'
            );
        });
        </script>
        <?php
    }
);
