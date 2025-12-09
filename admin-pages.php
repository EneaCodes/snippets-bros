<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function snippets_bros_render_snippet_row( $snippet, $has_error = false ) {
    if ( ! empty( $snippet['modified_date'] ) ) {
        $modified_date = wp_date( 'd/M/Y - H:i', $snippet['modified_date'] );
    } else {
        $modified_date = 'N/A';
    }
    ?>
    <tr <?php echo $has_error ? 'class="snippet-has-error"' : ''; ?> data-snippet-name="<?php echo esc_attr( strtolower( $snippet['name'] ) ); ?>">
        <td><input type="checkbox" class="snippet-checkbox" value="<?php echo esc_attr( $snippet['id'] ); ?>"></td>
        <td>
            <strong class="snippet-name"><?php echo esc_html( $snippet['name'] ); ?>
                <?php if ( $has_error ) : ?>
                    <span class="error-indicator" title="This snippet has errors">🚨</span>
                <?php endif; ?>
            </strong>
            <?php if ( ! empty( $snippet['category'] ) ) : ?>
                <small class="snippet-meta">Category: <?php echo esc_html( $snippet['category'] ); ?></small>
            <?php endif; ?>
            <?php if ( ! empty( $snippet['tags'] ) && is_array( $snippet['tags'] ) ) : ?>
                <small class="snippet-meta">Tags: <?php echo esc_html( implode( ', ', $snippet['tags'] ) ); ?></small>
            <?php endif; ?>
        </td>
        <td>
            <span class="snippet-type snippet-type-<?php echo esc_attr( $snippet['type'] ); ?>">
                <?php echo esc_html( strtoupper( $snippet['type'] ) ); ?>
            </span>
        </td>
        <td class="snippet-scope"><?php echo esc_html( ucfirst( $snippet['scope'] ) ); ?></td>
        <td class="snippet-priority"><?php echo intval( $snippet['priority'] ); ?></td>
        <td>
            <?php if ( $snippet['enabled'] ) : ?>
                <span class="status-enabled">✅ Enabled</span>
            <?php else : ?>
                <span class="status-disabled">❌ Disabled</span>
            <?php endif; ?>
        </td>
        <td class="snippet-modified">
            <small><?php echo esc_html( $modified_date ); ?></small>
        </td>
        <td class="snippet-shortcode">
            <code>[snippets_bros id="<?php echo esc_attr( $snippet['id'] ); ?>"]</code>
        </td>
        <td>
            <div class="snippet-actions">
                <?php if ( $snippet['enabled'] ) : ?>
                    <a href="<?php 
                        echo esc_url( wp_nonce_url( 
                            admin_url( 'admin.php?page=snippets-bros&snippets_bros_action=toggle&id=' . $snippet['id'] ), 
                            'snippets_bros_toggle_' . $snippet['id'] 
                        ) ); 
                    ?>" class="button button-small">Disable</a>
                <?php else : ?>
                    <a href="<?php 
                        echo esc_url( wp_nonce_url( 
                            admin_url( 'admin.php?page=snippets-bros&snippets_bros_action=toggle&id=' . $snippet['id'] ), 
                            'snippets_bros_toggle_' . $snippet['id'] 
                        ) ); 
                    ?>" class="button button-small">Enable</a>
                <?php endif; ?>
                
                <a href="<?php 
                    echo esc_url( wp_nonce_url(
                        admin_url( 'admin.php?page=snippets-bros-add&edit=' . $snippet['id'] ),
                        'edit_snippet_action'
                    ) ); 
                ?>" class="button button-small">Edit</a>
                <a href="<?php 
                    echo esc_url( wp_nonce_url( 
                        admin_url( 'admin.php?page=snippets-bros&snippets_bros_action=clone&id=' . $snippet['id'] ), 
                        'snippets_bros_clone_' . $snippet['id'] 
                    ) ); 
                ?>" class="button button-small">Clone</a>
                <button class="button button-small export-single" data-snippet-id="<?php echo esc_attr( $snippet['id'] ); ?>">Export</button>
                <a href="<?php 
                    echo esc_url( wp_nonce_url( 
                        admin_url( 'admin.php?page=snippets-bros&snippets_bros_action=delete&id=' . $snippet['id'] ), 
                        'snippets_bros_delete_' . $snippet['id'] 
                    ) ); 
                ?>" class="button button-small button-link-delete" onclick="return confirm('Delete this snippet?')">Delete</a>
            </div>
        </td>
    </tr>
    <?php
}

function snippets_bros_render_header( $plugin, $page_title = '' ) {
    $snippets = $plugin->get_snippets();
    $total_snippets = count( $snippets );
    $active_snippets = count( array_filter( $snippets, function( $s ) { return ! empty( $s['enabled'] ); } ) );
    $disabled_snippets = $total_snippets - $active_snippets;
    $safe_mode = $plugin->is_safe_mode_enabled();
    
    add_filter( 'admin_body_class', function( $classes ) {
        return $classes . ' snippets-bros-page';
    } );
    ?>
    <div class="snippets-bros-wrap">
        <div class="sb-logo-header">
            <div class="sb-header-left">
                <img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'snippets-bros-logo.png' ); ?>" alt="Snippets Bros" class="sb-logo">
                <div class="sb-title-section">
                    <h1 class="sb-title"><span class="code-icon">&lt;/&gt;</span> Snippets Bros</h1>
                    <p class="sb-subtitle">Professional snippet manager for PHP, HTML, CSS, JS</p>
                </div>
            </div>
            
            <div class="sb-header-stats">
                <div class="stat-card">
                    <div class="stat-badge" data-icon="📊">
                        <span class="stat-number"><?php echo intval( $total_snippets ); ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-badge" data-icon="✅">
                        <span class="stat-number"><?php echo intval( $active_snippets ); ?></span>
                        <span class="stat-label">Active</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-badge" data-icon="❌">
                        <span class="stat-number"><?php echo intval( $disabled_snippets ); ?></span>
                        <span class="stat-label">Disabled</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="sb-main-content">
            <?php if ( ! empty( $page_title ) ) : ?>
                <h1 class="sb-page-title"><?php echo esc_html( $page_title ); ?></h1>
            <?php endif; ?>
            
            <?php if ( $safe_mode ) : ?>
                <div class="snippets-bros-emergency-notice">
                    <h3>Safe Mode Active (all snippets disabled)</h3>
                    <p>All snippets are temporarily disabled. This is a safety feature to prevent code from running until you're ready.</p>
                    <p><a href="<?php echo esc_url( wp_nonce_url( 
                        admin_url( 'admin.php?page=snippets-bros&snippets_bros_action=toggle_safe_mode' ), 
                        'snippets_bros_toggle_safe_mode' 
                    ) ); ?>" class="button">Click to Turn Off Safe Mode</a></p>
                </div>
            <?php endif; ?>
    <?php
}

function snippets_bros_render_footer() {
    ?>
<div id="snippets-bros-ko-fi-container">
    <span class="ko-fi-icon">🚀</span>
    <h3>Love Snippets Bros?</h3>
    <p>Support further development and help make this plugin even better!</p>
    <a href='https://ko-fi.com/W7W51P4XY6' target='_blank'>
        Support on Ko-fi
    </a>
    <p>Every coffee helps!</p>
    <div class="coffee-steam"></div>
</div>
        </div>
    </div>
<div>
	<p class="snippets-bros-made-with">Made with ❤️ by Enea.</p>
</div>
    <?php
}

function snippets_bros_admin_page_main( $plugin ) {
    $snippets = $plugin->get_snippets();
    $safe_mode = $plugin->is_safe_mode_enabled();
    $error_log = $plugin->get_error_log();
    
    $snippets_with_errors = array();
    foreach ( $error_log as $error ) {
        $snippets_with_errors[$error['snippet_id']] = true;
    }
    
    $show_saved = false;
    $show_deleted = false;
    $show_toggled = false;
    $show_cloned = false;
    $show_safe_mode_toggled = false;
    $show_emergency_recovery = false;
    
    if ( isset( $_GET['saved'] ) && isset( $_GET['_wpnonce_msg'] ) ) {
        if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce_msg'] ), 'snippets_bros_success_msg' ) ) {
            $show_saved = true;
        }
    }
    
    if ( isset( $_GET['deleted'] ) && isset( $_GET['_wpnonce_msg'] ) ) {
        if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce_msg'] ), 'snippets_bros_success_msg' ) ) {
            $show_deleted = true;
        }
    }
    
    if ( isset( $_GET['toggled'] ) && isset( $_GET['_wpnonce_msg'] ) ) {
        if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce_msg'] ), 'snippets_bros_success_msg' ) ) {
            $show_toggled = true;
        }
    }
    
    if ( isset( $_GET['cloned'] ) && isset( $_GET['_wpnonce_msg'] ) ) {
        if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce_msg'] ), 'snippets_bros_success_msg' ) ) {
            $show_cloned = true;
        }
    }
    
    if ( isset( $_GET['safe_mode_toggled'] ) && isset( $_GET['_wpnonce_msg'] ) ) {
        if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce_msg'] ), 'snippets_bros_success_msg' ) ) {
            $show_safe_mode_toggled = true;
        }
    }
    
    if ( isset( $_GET['emergency_recovery'] ) ) {
        $show_emergency_recovery = true;
    }
    
    $sort_by = isset( $_GET['sort_by'] ) ? sanitize_text_field( wp_unslash( $_GET['sort_by'] ) ) : 'name';
    $sort_order = isset( $_GET['sort_order'] ) ? sanitize_text_field( wp_unslash( $_GET['sort_order'] ) ) : 'asc';
    
    usort( $snippets, function( $a, $b ) use ( $sort_by, $sort_order ) {
        $a_val = '';
        $b_val = '';
        
        switch ( $sort_by ) {
            case 'name':
                $a_val = strtolower( $a['name'] ?? '' );
                $b_val = strtolower( $b['name'] ?? '' );
                break;
            case 'type':
                $a_val = strtolower( $a['type'] ?? '' );
                $b_val = strtolower( $b['type'] ?? '' );
                break;
            case 'scope':
                $a_val = strtolower( $a['scope'] ?? '' );
                $b_val = strtolower( $b['scope'] ?? '' );
                break;
            case 'priority':
                $a_val = intval( $a['priority'] ?? 10 );
                $b_val = intval( $b['priority'] ?? 10 );
                break;
            case 'status':
                $a_val = ! empty( $a['enabled'] ) ? 0 : 1;
                $b_val = ! empty( $b['enabled'] ) ? 0 : 1;
                break;
            case 'modified':
                $a_val = $a['modified_date'] ?? 0;
                $b_val = $b['modified_date'] ?? 0;
                break;
            default:
                $a_val = strtolower( $a['name'] ?? '' );
                $b_val = strtolower( $b['name'] ?? '' );
        }
        
        if ( $sort_order === 'asc' ) {
            return $a_val <=> $b_val;
        } else {
            return $b_val <=> $a_val;
        }
    } );
    
    snippets_bros_render_header( $plugin );
    ?>
        
        <?php if ( $show_saved ) : ?>
            <div class="notice notice-success"><p>Snippet saved successfully.</p></div>
        <?php endif; ?>
        
        <?php if ( $show_deleted ) : ?>
            <div class="notice notice-success"><p>Snippet deleted successfully.</p></div>
        <?php endif; ?>
        
        <?php if ( $show_toggled ) : ?>
            <div class="notice notice-success"><p>Snippet status updated.</p></div>
        <?php endif; ?>
        
        <?php if ( $show_cloned ) : ?>
            <div class="notice notice-success"><p>Snippet cloned successfully.</p></div>
        <?php endif; ?>
        
        <?php if ( $show_safe_mode_toggled ) : ?>
            <div class="notice notice-success"><p>Safe mode updated successfully.</p></div>
        <?php endif; ?>
        
        <?php if ( $show_emergency_recovery ) : ?>
            <div class="notice notice-success"><p>Emergency recovery completed. Safe mode enabled and all snippets disabled.</p></div>
        <?php endif; ?>
        
<div class="snippets-bros-controls">
    <div class="snippets-bros-controls-left">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=snippets-bros-add' ) ); ?>" class="button button-primary">Add New Snippet</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=snippets-bros-import-export' ) ); ?>" class="button">Import/Export</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=snippets-bros-error-log' ) ); ?>" class="button">
            Error Log
            <?php if ( count( $error_log ) > 0 ) : ?>
                <span style="color: #dc2626; margin-left: 8px; font-size: 1.0em; font-weight: 900; display: inline-block; transform: scale(1.8); text-shadow: 0 0 8px rgba(220, 38, 38, 0.6);">🚨</span>
            <?php endif; ?>
        </a>
    </div>
            
            <div class="snippets-bros-controls-right">
                <?php 
                $safe_mode_toggle_url = wp_nonce_url( 
                    admin_url( 'admin.php?page=snippets-bros&snippets_bros_action=toggle_safe_mode' ), 
                    'snippets_bros_toggle_safe_mode' 
                );
                ?>
                <a href="<?php echo esc_url( $safe_mode_toggle_url ); ?>"
                   class="button safe-mode-toggle <?php echo $safe_mode ? 'safe-mode-on' : 'safe-mode-off'; ?>">
                    
                    <span class="dashicons dashicons-<?php echo $safe_mode ? 'shield' : 'shield'; ?>"></span>
                    
                    <span class="safe-mode-text">
                        <?php echo $safe_mode ? esc_html__( 'Safe Mode: ON', 'snippets-bros' ) : esc_html__( 'Safe Mode: OFF', 'snippets-bros' ); ?>
                    </span>
                </a>
            </div>
        </div>
        
        <?php if ( empty( $snippets ) ) : ?>
            <div class="sb-empty-container">
                <div class="sb-empty-state">
                    <div class="sb-empty-icon">📝</div>
                    <h3 class="sb-empty-title">No snippets found</h3>
                    <p class="sb-empty-description">
                        Get started by creating your first code snippet
                    </p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=snippets-bros-add' ) ); ?>" class="button button-primary sb-empty-button">
                        Create Your First Snippet
                    </a>
                </div>
            </div>
        <?php else : ?>
            <div class="snippets-bros-bulk-actions">
                <div class="bulk-actions-left">
                    <select id="bulk-action-select-top">
                        <option value="">Bulk Actions</option>
                        <option value="enable">Enable</option>
                        <option value="disable">Disable</option>
                        <option value="delete">Delete</option>
                        <option value="export">Export Selected</option>
                    </select>
                    <button id="bulk-action-apply-top" class="button">Apply</button>
                </div>
                <div class="bulk-actions-right">
                    <input type="text" id="snippets-search" placeholder="Search snippets..." />
                </div>
            </div>
            
            <div id="snippets-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 2%;"><input type="checkbox" id="snippets-bros-select-all"></th>
                            <th style="width: 22%;">
                                <a href="<?php 
                                    echo esc_url( add_query_arg( array( 
                                        'sort_by' => 'name', 
                                        'sort_order' => ( $sort_by === 'name' && $sort_order === 'asc' ? 'desc' : 'asc' ) 
                                    ) ) ); 
                                ?>" class="sortable-header">
                                    Name
                                    <?php if ( $sort_by === 'name' ) : ?>
                                        <span class="sorting-indicator <?php echo $sort_order === 'asc' ? 'asc' : 'desc'; ?>"></span>
                                    <?php else : ?>
                                        <span class="sorting-indicator both"></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th style="width: 8%;">
                                <a href="<?php 
                                    echo esc_url( add_query_arg( array( 
                                        'sort_by' => 'type', 
                                        'sort_order' => ( $sort_by === 'type' && $sort_order === 'asc' ? 'desc' : 'asc' ) 
                                    ) ) ); 
                                ?>" class="sortable-header">
                                    Type
                                    <?php if ( $sort_by === 'type' ) : ?>
                                        <span class="sorting-indicator <?php echo $sort_order === 'asc' ? 'asc' : 'desc'; ?>"></span>
                                    <?php else : ?>
                                        <span class="sorting-indicator both"></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th style="width: 8%;">
                                <a href="<?php 
                                    echo esc_url( add_query_arg( array( 
                                        'sort_by' => 'scope', 
                                        'sort_order' => ( $sort_by === 'scope' && $sort_order === 'asc' ? 'desc' : 'asc' ) 
                                    ) ) ); 
                                ?>" class="sortable-header">
                                    Scope
                                    <?php if ( $sort_by === 'scope' ) : ?>
                                        <span class="sorting-indicator <?php echo $sort_order === 'asc' ? 'asc' : 'desc'; ?>"></span>
                                    <?php else : ?>
                                        <span class="sorting-indicator both"></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th style="width: 5%;">
                                <a href="<?php 
                                    echo esc_url( add_query_arg( array( 
                                        'sort_by' => 'priority', 
                                        'sort_order' => ( $sort_by === 'priority' && $sort_order === 'asc' ? 'desc' : 'asc' ) 
                                    ) ) ); 
                                ?>" class="sortable-header">
                                    Priority
                                    <?php if ( $sort_by === 'priority' ) : ?>
                                        <span class="sorting-indicator <?php echo $sort_order === 'asc' ? 'asc' : 'desc'; ?>"></span>
                                    <?php else : ?>
                                        <span class="sorting-indicator both"></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th style="width: 8%;">
                                <a href="<?php 
                                    echo esc_url( add_query_arg( array( 
                                        'sort_by' => 'status', 
                                        'sort_order' => ( $sort_by === 'status' && $sort_order === 'asc' ? 'desc' : 'asc' ) 
                                    ) ) ); 
                                ?>" class="sortable-header">
                                    Status
                                    <?php if ( $sort_by === 'status' ) : ?>
                                        <span class="sorting-indicator <?php echo $sort_order === 'asc' ? 'asc' : 'desc'; ?>"></span>
                                    <?php else : ?>
                                        <span class="sorting-indicator both"></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th style="width: 12%;">
                                <a href="<?php 
                                    echo esc_url( add_query_arg( array( 
                                        'sort_by' => 'modified', 
                                        'sort_order' => ( $sort_by === 'modified' && $sort_order === 'asc' ? 'desc' : 'asc' ) 
                                    ) ) ); 
                                ?>" class="sortable-header">
                                    Modified
                                    <?php if ( $sort_by === 'modified' ) : ?>
                                        <span class="sorting-indicator <?php echo $sort_order === 'asc' ? 'asc' : 'desc'; ?>"></span>
                                    <?php else : ?>
                                        <span class="sorting-indicator both"></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th style="width: 12%;">Shortcode</th>
                            <th style="width: 20%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="snippets-tbody">
                        <?php foreach ( $snippets as $snippet ) : 
                            $has_error = isset( $snippets_with_errors[$snippet['id']] );
                            snippets_bros_render_snippet_row( $snippet, $has_error );
                        endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="snippets-bros-bulk-actions">
                <select id="bulk-action-select">
                    <option value="">Bulk Actions</option>
                    <option value="enable">Enable</option>
                    <option value="disable">Disable</option>
                    <option value="delete">Delete</option>
                    <option value="export">Export Selected</option>
                </select>
                <button id="bulk-action-apply" class="button">Apply</button>
            </div>
        <?php endif; ?>

    <?php
    snippets_bros_render_footer();
}

function snippets_bros_admin_page_add( $plugin ) {
    $show_imported = false;
    $show_import_error = false;
    $show_revision_restored = false;
    $show_restore_error = false;
    $imported_count = 0;
    $import_error_type = '';
    
    if ( isset( $_GET['imported'] ) && isset( $_GET['_wpnonce_msg'] ) ) {
        if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce_msg'] ), 'snippets_bros_success_msg' ) ) {
            $show_imported = true;
            $imported_count = intval( $_GET['imported'] );
        }
    }
    
    if ( isset( $_GET['import_error'] ) && isset( $_GET['_wpnonce_msg'] ) ) {
        if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce_msg'] ), 'snippets_bros_error_msg' ) ) {
            $show_import_error = true;
            $import_error_type = isset( $_GET['import_error_type'] ) ? sanitize_text_field( wp_unslash( $_GET['import_error_type'] ) ) : '';
        }
    }
    
    if ( isset( $_GET['revision_restored'] ) && isset( $_GET['_wpnonce_msg'] ) ) {
        if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce_msg'] ), 'snippets_bros_success_msg' ) ) {
            $show_revision_restored = true;
        }
    }
    
    if ( isset( $_GET['restore_error'] ) && isset( $_GET['_wpnonce_msg'] ) ) {
        if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce_msg'] ), 'snippets_bros_error_msg' ) ) {
            $show_restore_error = true;
        }
    }
    
    $edit_id = '';
    $editing = null;
    
    if ( isset( $_GET['edit'] ) ) {
        $edit_id = sanitize_text_field( wp_unslash( $_GET['edit'] ) );
        $editing = $plugin->get_snippet( $edit_id );
        
        if ( ! $editing && ! empty( $edit_id ) ) {
            echo '<div class="notice notice-warning"><p>Debug: Snippet with ID "' . esc_html( $edit_id ) . '" not found in database.</p></div>';
        }
    }
    
    $id = $editing['id'] ?? '';
    $name = $editing['name'] ?? '';
    $type = $editing['type'] ?? 'php';
    $scope = $editing['scope'] ?? 'everywhere';
    $enabled = isset( $editing['enabled'] ) ? (bool) $editing['enabled'] : true;
    $priority = $editing['priority'] ?? 10;
    $category = $editing['category'] ?? '';
    $tags = isset( $editing['tags'] ) ? implode( ', ', $editing['tags'] ) : '';
    $content = $editing['content'] ?? '';
    $run_once = isset( $editing['run_once'] ) ? (bool) $editing['run_once'] : false;
    $conditions = $editing['conditions'] ?? array();
    
    $revisions = $editing ? $plugin->get_revisions( $id ) : array();
    
    snippets_bros_render_header( $plugin, $editing ? 'Edit Snippet' : 'Add New Snippet' );
    ?>
        
        <?php if ( $show_revision_restored ) : ?>
            <div class="notice notice-success"><p>Revision restored successfully.</p></div>
        <?php endif; ?>
        
        <?php if ( $show_restore_error ) : ?>
            <div class="notice notice-error"><p>Error restoring revision.</p></div>
        <?php endif; ?>
        
        <?php if ( $show_import_error ) : ?>
            <div class="notice notice-error">
                <p>
                    <?php if ( $import_error_type === 'size' ) : ?>
                        Error: File is too large. Maximum file size is 10MB.
                    <?php elseif ( $import_error_type === 'type' ) : ?>
                        Error: Invalid file type. Please upload a JSON file.
                    <?php else : ?>
                        Error importing snippets. Please check the file format.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="sb-page-add-snippet">
            <div class="sb-page-back">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=snippets-bros' ) ); ?>" class="button">← Back to All Snippets</a>
            </div>
            
            <div class="sb-form-container">
                <form method="post" id="snippet-form">
                    <?php wp_nonce_field( 'snippets_bros_save_snippet' ); ?>
                    <input type="hidden" name="snippets_bros_action" value="save_snippet">
                    <input type="hidden" name="snippets_bros_id" value="<?php echo esc_attr( $id ); ?>">
                    
                    <div class="sb-form-grid">
                        <div class="sb-form-main">
                            <div class="sb-form-section">
                                <h3>Basic Information</h3>
                                <div class="sb-form-row">
                                    <label for="snippets_bros_name">Snippet Name <span class="required">*</span></label>
                                    <input type="text" id="snippets_bros_name" name="snippets_bros_name" class="sb-form-input" value="<?php echo esc_attr( $name ); ?>" required>
                                    <p class="description">A descriptive name for your snippet</p>
                                </div>
                                
                                <div class="sb-form-row">
                                    <label for="snippets_bros_type">Type</label>
                                    <select id="snippets_bros_type" name="snippets_bros_type" class="sb-form-select">
                                        <option value="php" <?php selected( $type, 'php' ); ?>>PHP Code</option>
                                        <option value="html" <?php selected( $type, 'html' ); ?>>HTML</option>
                                        <option value="css" <?php selected( $type, 'css' ); ?>>CSS</option>
                                        <option value="js" <?php selected( $type, 'js' ); ?>>JavaScript</option>
                                        <option value="header" <?php selected( $type, 'header' ); ?>>Header Script</option>
                                        <option value="footer" <?php selected( $type, 'footer' ); ?>>Footer Script</option>
                                    </select>
                                </div>
                                
                                <div class="sb-form-row">
                                    <label for="snippets_bros_scope">Scope</label>
                                    <select id="snippets_bros_scope" name="snippets_bros_scope" class="sb-form-select">
                                        <option value="everywhere" <?php selected( $scope, 'everywhere' ); ?>>Everywhere</option>
                                        <option value="frontend" <?php selected( $scope, 'frontend' ); ?>>Frontend Only</option>
                                        <option value="admin" <?php selected( $scope, 'admin' ); ?>>Admin Only</option>
                                        <option value="shortcode" <?php selected( $scope, 'shortcode' ); ?>>Shortcode Only</option>
                                    </select>
                                </div>
                                
                                <div class="sb-form-row">
                                    <label for="snippets_bros_priority">Priority</label>
                                    <input type="number" id="snippets_bros_priority" name="snippets_bros_priority" value="<?php echo esc_attr( $priority ); ?>" min="1" max="100" class="sb-form-input-small">
                                    <p class="description">Lower numbers execute first (1-100)</p>
                                </div>
                                
                                <div class="sb-form-row">
                                    <label for="snippets_bros_category">Category</label>
                                    <input type="text" id="snippets_bros_category" name="snippets_bros_category" class="sb-form-input" value="<?php echo esc_attr( $category ); ?>">
                                    <p class="description">Optional category for organization</p>
                                </div>
                                
                                <div class="sb-form-row">
                                    <label for="snippets_bros_tags">Tags</label>
                                    <input type="text" id="snippets_bros_tags" name="snippets_bros_tags" class="sb-form-input" value="<?php echo esc_attr( $tags ); ?>">
                                    <p class="description">Comma separated tags</p>
                                </div>
                            </div>
                            
                            <div class="sb-form-section">
                                <h3>Conditions</h3>
                                <div class="sb-conditions-simple">
                                    <div class="sb-condition-item">
                                        <h4>User Status</h4>
                                        <div class="sb-radio-group">
                                            <label class="sb-radio-label">
                                                <input type="radio" name="snippets_bros_conditions[user_status]" value="" <?php checked( empty( $conditions['user_status'] ) ); ?>>
                                                <span>All Users</span>
                                            </label>
                                            <label class="sb-radio-label">
                                                <input type="radio" name="snippets_bros_conditions[user_status]" value="logged_in" <?php checked( $conditions['user_status'] ?? '', 'logged_in' ); ?>>
                                                <span>Only Logged-in Users</span>
                                            </label>
                                            <label class="sb-radio-label">
                                                <input type="radio" name="snippets_bros_conditions[user_status]" value="logged_out" <?php checked( $conditions['user_status'] ?? '', 'logged_out' ); ?>>
                                                <span>Only Logged-out Users</span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="sb-condition-item">
                                        <h4>Device Type</h4>
                                        <div class="sb-radio-group">
                                            <label class="sb-radio-label">
                                                <input type="radio" name="snippets_bros_conditions[device_type]" value="" <?php checked( empty( $conditions['device_type'] ) ); ?>>
                                                <span>All Devices</span>
                                            </label>
                                            <label class="sb-radio-label">
                                                <input type="radio" name="snippets_bros_conditions[device_type]" value="desktop" <?php checked( $conditions['device_type'] ?? '', 'desktop' ); ?>>
                                                <span>Desktop Only</span>
                                            </label>
                                            <label class="sb-radio-label">
                                                <input type="radio" name="snippets_bros_conditions[device_type]" value="mobile" <?php checked( $conditions['device_type'] ?? '', 'mobile' ); ?>>
                                                <span>Mobile Only</span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="sb-condition-item">
                                        <h4>URL Patterns (one per line)</h4>
                                        <textarea name="snippets_bros_conditions[url_patterns]" 
                                                  rows="4" 
                                                  class="sb-form-input" 
                                                  placeholder="e.g. /shop&#10;/blog&#10;/product*&#10;/category/*"><?php 
                                            if ( isset( $conditions['url_patterns'] ) && is_array( $conditions['url_patterns'] ) ) {
                                                echo esc_textarea( implode( "\n", $conditions['url_patterns'] ) );
                                            }
                                        ?></textarea>
                                        <p class="description">Snippet will run on ANY of these URLs (one pattern per line). Use * for wildcard.</p>
                                        <div class="pattern-examples">
                                            <strong>Examples:</strong>
                                            <ul>
                                                <li><code>/shop</code> - matches any URL containing "/shop"</li>
                                                <li><code>/blog*</code> - matches "/blog", "/blog/", "/blog/post-1"</li>
                                                <li><code>/category/*</code> - matches any URL starting with "/category/"</li>
                                                <li><code>/product/*/view</code> - matches "/product/123/view", "/product/abc/view"</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <p class="description">All conditions must be met for the snippet to execute</p>
                            </div>
                            
                            <div class="sb-form-section">
                                <h3>Code</h3>
                                <div class="sb-form-row">
                                    <label for="snippets_bros_content">Snippet Code</label>
                                    <textarea id="snippets_bros_content" name="snippets_bros_content" rows="15" class="sb-code-editor"><?php echo esc_textarea( $content ); ?></textarea>
                                    <?php if ( $type === 'php' ) : ?>
                                        <p class="description">For PHP snippets, omit the opening &lt;?php tag</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="sb-form-section">
                                <h3>Settings</h3>
                                <div class="sb-settings-simple">
                                    <div class="sb-setting-item">
                                        <label class="sb-checkbox-label">
                                            <input type="checkbox" name="snippets_bros_run_once" value="1" <?php checked( $run_once ); ?>>
                                            <span>Run this snippet only once then disable it</span>
                                        </label>
                                        <p class="description">Useful for one-time actions</p>
                                    </div>
                                    
                                    <div class="sb-setting-item">
                                        <label class="sb-checkbox-label">
                                            <input type="checkbox" name="snippets_bros_enabled" value="1" <?php checked( $enabled ); ?>>
                                            <span>Enable this snippet immediately</span>
                                        </label>
                                        <p class="description">Uncheck to save as draft</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="sb-form-actions">
                                <button type="submit" class="button button-primary"><?php echo $editing ? 'Update Snippet' : 'Create Snippet'; ?></button>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=snippets-bros' ) ); ?>" class="button">Cancel</a>
                            </div>
                        </div>
                        
                        <?php if ( $editing && ! empty( $revisions ) ) : ?>
                        <div class="sb-form-sidebar">
                            <div class="sb-sidebar-card">
                                <div class="sb-sidebar-card-header">
                                    <h4>Revision History</h4>
                                </div>
                                <div class="sb-sidebar-card-content">
                                    <p class="description" style="margin-bottom: 15px;">Last 15 revisions</p>
                                    
                                    <?php
                                    $current_revision_index = -1;
                                    foreach ( $revisions as $index => $revision ) {
                                        if ( $revision['content'] === $content ) {
                                            $current_revision_index = $index;
                                            break;
                                        }
                                    }
                                    ?>
                                    
                                    <div class="sb-revisions-list">
                                        <?php foreach ( $revisions as $index => $revision ) : 
                                            $user = get_userdata( $revision['modified_by'] );
                                            $username = $user ? $user->display_name : 'Unknown';
                                            $date = wp_date( 'd/M/Y - H:i', $revision['modified_date'] );
                                            $is_current = ( $index === $current_revision_index );
                                        ?>
                                            <div class="sb-revision-item">
                                                <?php if ( $is_current ) : ?>
                                                    <div class="sb-current-revision">Current</div>
                                                <?php endif; ?>
                                                <div class="sb-revision-date"><?php echo esc_html( $date ); ?></div>
                                                <div class="sb-revision-author">By <?php echo esc_html( $username ); ?></div>
                                                <div class="sb-revision-actions">
                                                    <a href="<?php 
                                                        echo esc_url( wp_nonce_url( 
                                                            admin_url( 'admin.php?page=snippets-bros-add&edit=' . $id . '&snippets_bros_action=restore_revision&revision=' . $index ), 
                                                            'snippets_bros_restore_' . $id 
                                                        ) ); 
                                                    ?>" 
                                                       class="button button-small" 
                                                       onclick="return confirm('Restore this revision? Current content will be saved as a new revision.')">
                                                        Restore
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

    <?php
    snippets_bros_render_footer();
}

function snippets_bros_admin_page_import_export( $plugin ) {
    snippets_bros_render_header( $plugin, 'Import & Export Snippets' );
    
    $show_imported = false;
    $show_import_error = false;
    $imported_count = 0;
    $import_error_type = '';
    
    if ( isset( $_GET['imported'] ) && isset( $_GET['_wpnonce_msg'] ) ) {
        if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce_msg'] ), 'snippets_bros_success_msg' ) ) {
            $show_imported = true;
            $imported_count = intval( $_GET['imported'] );
        }
    }
    
    if ( isset( $_GET['import_error'] ) && isset( $_GET['_wpnonce_msg'] ) ) {
        if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce_msg'] ), 'snippets_bros_error_msg' ) ) {
            $show_import_error = true;
            $import_error_type = isset( $_GET['import_error_type'] ) ? sanitize_text_field( wp_unslash( $_GET['import_error_type'] ) ) : '';
        }
    }
    ?>
        
        <?php if ( $show_imported ) : ?>
            <div class="notice notice-success"><p><?php echo intval( $imported_count ); ?> snippets imported successfully.</p></div>
        <?php endif; ?>
        
        <?php if ( $show_import_error ) : ?>
            <div class="notice notice-error">
                <p>
                    <?php if ( $import_error_type === 'size' ) : ?>
                        Error: File is too large. Maximum file size is 10MB.
                    <?php elseif ( $import_error_type === 'type' ) : ?>
                        Error: Invalid file type. Please upload a JSON file.
                    <?php else : ?>
                        Error importing snippets. Please check the file format.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="sb-page-back">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=snippets-bros' ) ); ?>" class="button">← Back to All Snippets</a>
        </div>
        
        <div class="sb-page-import-export">
            <div class="sb-import-export-section export-section">
                <h2>Export Snippets</h2>
                <p>Export all your snippets as a JSON file for backup or migration.</p>
                
                <form method="post">
                    <?php wp_nonce_field( 'snippets_bros_export' ); ?>
                    <input type="hidden" name="snippets_bros_action" value="export_snippets">
                    
                    <p>
                        <button type="submit" class="button button-primary">Export All Snippets</button>
                    </p>
                </form>
                
                <hr>
                
                <h3>Export Selected Snippets</h3>
                <p>Go to the <a href="<?php echo esc_url( admin_url( 'admin.php?page=snippets-bros' ) ); ?>">All Snippets</a> page to select specific snippets for export using bulk actions.</p>
            </div>
            
            <div class="sb-import-export-section import-section">
                <h2>Import Snippets</h2>
                <p>Import snippets from a previously exported JSON file.</p>
                <div class="import-security-notice">
                    <p><strong>⚠️ Security Notice:</strong></p>
                    <ul>
                        <li>Maximum file size: <strong>10MB</strong></li>
                        <li>Allowed file type: <strong>.json only</strong></li>
                        <li>Imported snippets will be <strong>disabled by default</strong> for safety</li>
                        <li>All imported snippets will be <strong>sanitized automatically</strong></li>
                    </ul>
                </div>
                
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'snippets_bros_import' ); ?>
                    <input type="hidden" name="snippets_bros_action" value="import_snippets">
                    
                    <p>
                        <input type="file" name="snippets_file" accept=".json" required>
                    </p>
                    <p class="description">Select a JSON file exported from Snippets Bros (max 10MB)</p>
                    
                    <p>
                        <button type="submit" class="button button-primary">Import Snippets</button>
                    </p>
                </form>
                
                <hr>
                
                <h3>Import Notes</h3>
                <ul>
                    <li>Imported snippets will get new unique IDs</li>
                    <li>Existing snippets will not be overwritten</li>
                    <li>All imported snippets will be disabled by default</li>
                    <li>All settings will be preserved</li>
                    <li>PHP snippets will be validated for syntax</li>
                    <li>CSS/JS/HTML snippets will be sanitized</li>
                </ul>
            </div>
        </div>

    <?php
    snippets_bros_render_footer();
}

function snippets_bros_admin_page_error_log( $plugin ) {
    $error_log = $plugin->get_error_log();
    
    snippets_bros_render_header( $plugin, 'Error Log' );
    ?>
        
        <div class="sb-page-back">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=snippets-bros' ) ); ?>" class="button">← Back to All Snippets</a>
        </div>

        <div class="sb-page-error-log">
            <div class="snippets-bros-error-log">
                <div class="sb-error-log-header">
                    <h2>PHP Errors from Snippets</h2>
                    <button id="clear-error-log" class="button button-secondary">Clear Log</button>
                </div>

                <div class="sb-error-log-content">
                    <?php if ( empty( $error_log ) ) : ?>
                        <div class="sb-error-log-empty">
                            <div style="font-size: 30px; margin-bottom: 10px;">🎉</div>
                            <h3>All good</h3>
                            <p>No Errors Found</p>
                            <p style="color: #64748b; font-size: 15px;">Your snippets are running smoothly with no issues.</p>
                        </div>
                    <?php else : ?>
                        <div class="error-log-container">
                            <?php foreach ( $error_log as $error ) :
                                $snippet = $plugin->get_snippet( $error['snippet_id'] );
                                $snippet_name = $snippet ? $snippet['name'] : 'Unknown Snippet (' . $error['snippet_id'] . ')';
                                $timestamp = wp_date( 'd M Y - H:i:s', $error['timestamp'] );
                            ?>
                                <div class="error-log-entry">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                        <div>
                                            <span style="background: #dc2626; color: #fff; padding: 4px 9px; border-radius: 4px; font-size: 11px; font-weight: bold;">ERROR</span>
                                            <strong style="margin-left: 10px;"><?php echo esc_html( $snippet_name ); ?></strong>
                                            <span style="color: #dc2626; font-weight: bold; margin-left: 8px;" title="Error detected">🚨</span>
                                        </div>
                                        <span style="color: #666; font-size: 13px;"><?php echo esc_html( $timestamp ); ?></span>
                                    </div>

                                    <div class="error-log-message">
                                        <code><?php echo esc_html( $error['message'] ); ?></code>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; color: #555;">
                                        <div>
                                            <strong>URL:</strong> <span style="word-break: break-all;"><?php echo esc_html( $error['url'] ); ?></span>
                                        </div>
                                        <?php if ( $snippet ) : ?>
                                            <a href="<?php 
                                                echo esc_url( wp_nonce_url(
                                                    admin_url( 'admin.php?page=snippets-bros-add&edit=' . $snippet['id'] ),
                                                    'edit_snippet_action'
                                                ) ); 
                                            ?>" class="button button-small">Edit Snippet</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php
    snippets_bros_render_footer();
}