<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/class-questions-list-table.php';

/**
 * Centralised Q&A List and Migration
 *
 * Provides the "All Questions" admin page (centralised view across all products)
 * and the AJAX-based migration wizard that syncs ets_question_answer post meta
 * into the wp_comments table (comment_type = 'ets_wc_qa').
 *
 * @since 1.3.0
 */
class ETS_WOO_CENTRALISED_QA_LIST
{

    public function __construct()
    {
        add_action('init', array($this, 'register_hooks'));
    }

    public function register_hooks()
    {
        if (current_user_can('shop_manager') || current_user_can('administrator')) {
            add_action('admin_menu', array($this, 'register_submenu'), 11);
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        }

        // Background migration hooks
        add_action('admin_init', array($this, 'maybe_schedule_background_migration'));
        add_action('ets_qa_background_migration_batch', array($this, 'process_migration_batch'));
        add_action('admin_notices', array($this, 'migration_admin_notice'));
    }

    /**
     * Admin notice to inform users migration is running
     */
    public function migration_admin_notice()
    {
        // Show notice whenever migration is not yet complete — regardless of whether
        // an AS action is pending/running at this exact moment.
        if ( get_option( 'ets_qa_sync_completed', 'no' ) !== 'yes' ) {
            echo '<div class="notice notice-info"><p><strong>'
                . esc_html__( 'Product Q&A for WooCommerce', 'product-questions-answers-for-woocommerce' )
                . '</strong>: '
                . esc_html__( 'Database optimization is currently running seamlessly in the background. No action is required.', 'product-questions-answers-for-woocommerce' )
                . '</p></div>';
        }
    }

    /**
     * Schedule the background migration if not completed and not already scheduled
     */
    public function maybe_schedule_background_migration()
    {
        if (!function_exists('as_enqueue_async_action')) {
            return;
        }

        if (get_option('ets_qa_sync_completed', 'no') === 'yes') {
            return;
        }

        if (false === as_next_scheduled_action('ets_qa_background_migration_batch')) {
            as_enqueue_async_action('ets_qa_background_migration_batch');
        }
    }
    /**
     * Register "All Questions" submenu under "Products Q & A".
     */
    public function register_submenu()
    {
        add_submenu_page(
            'theme-options',
            esc_html__('All Questions', 'product-questions-answers-for-woocommerce'),
            esc_html__('All Questions', 'product-questions-answers-for-woocommerce'),
            'manage_options',
            'all_qa_questions',
            array($this, 'render_all_qa_page')
        );
    }

    /**
     * Enqueue migration script on the All Questions admin page.
     */
    public function enqueue_scripts($hook_suffix)
    {
        if ($hook_suffix !== 'products-q-a_page_all_qa_questions') {
            return;
        }
        // Background processor handles the migration, no JS required anymore.
    }

    /**
     * Render the "All Questions" admin page.
     */
    public function render_all_qa_page()
    {
        $sync_completed = get_option('ets_qa_sync_completed', 'no');

        if ($sync_completed === 'no') {
            echo '<div id="sync-message" style="padding:10px;background-color:#ffffe0;border:1px solid #e6db55;margin-bottom:20px;">'
                . '<p><strong>' . esc_html__('Database optimization is currently running in the background. Your questions will appear here shortly...', 'product-questions-answers-for-woocommerce') . '</strong></p>'
                . '</div>';
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

        // prepare_items() must run first — it handles bulk delete redirects before any output
        $table = new Questions_List_Table();
        $table->prepare_items();

        echo '<h1>' . esc_html__('All Questions', 'product-questions-answers-for-woocommerce') . '</h1>';
        $table->admin_notices();

        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . esc_attr($page))) . '">';
        echo '<input type="hidden" name="page" value="' . esc_attr($page) . '" />';

        $table->search_box(esc_html__('Search', 'product-questions-answers-for-woocommerce'), 'search_id');
        $table->filter_dropdown();
        $table->display();

        echo '</form>';
    }

    /**
     * Action Scheduler Callback: Process a batch of products.
     */
    public function process_migration_batch()
    {
        global $wpdb;

        // Find products that have ets_question_answer but NOT ets_qa_migrated
        $products = $wpdb->get_col("
            SELECT pm.post_id
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm_migrated ON pm_migrated.post_id = p.ID AND pm_migrated.meta_key = 'ets_qa_migrated'
            WHERE pm.meta_key = 'ets_question_answer'
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm_migrated.meta_id IS NULL
            LIMIT 20
        ");

        if (empty($products)) {
            // No more products to migrate. We are done!
            update_option('ets_qa_sync_completed', 'yes');
            return;
        }

        foreach ($products as $productId) {
            $this->migrate_single_product($productId);
        }

        // Enqueue the next batch immediately
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('ets_qa_background_migration_batch');
        }
    }

    /**
     * Helper logic to migrate a single product's QA to comments
     */
    private function migrate_single_product($productId)
    {
        global $wpdb;

        $questionsMeta = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'ets_question_answer'",
            $productId
        ));

        $productQas = maybe_unserialize($questionsMeta);

        if (!is_array($productQas)) {
            // Nothing to migrate, mark it done.
            update_post_meta($productId, 'ets_qa_migrated', 'yes');
            return;
        }

        $all_missing_comment_ids = true;
        foreach ($productQas as $qa) {
            if (isset($qa['comment_id']) && !empty($qa['comment_id'])) {
                $all_missing_comment_ids = false;
                break;
            }
        }

        if ($all_missing_comment_ids) {
            $existing_comments = $wpdb->get_col($wpdb->prepare(
                "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_type = %s",
                $productId,
                'ets_wc_qa'
            ));
            foreach ($existing_comments as $comment_id) {
                wp_delete_comment($comment_id, true);
            }
        }

        $migration_needed = false;

        foreach ($productQas as $qkey => $qa) {
            if (!isset($qa['comment_id']) || empty($qa['comment_id'])) {
                $migration_needed = true;

                $comment_date = current_time('mysql');
                if (!empty($qa['date'])) {
                    $parsed = date_create($qa['date']);
                    if ($parsed) {
                        $parsed->setTimezone(new DateTimeZone('UTC'));
                        $comment_date = $parsed->format('Y-m-d H:i:s');
                    }
                }

                $comment_data = array(
                    'comment_post_ID'      => $productId,
                    'comment_content'      => $qa['question'],
                    'comment_type'         => 'ets_wc_qa',
                    'user_id'              => $qa['user_id'],
                    'comment_author'       => (!empty($qa['user_name']) ? $qa['user_name'] : ''),
                    'comment_author_email' => (!empty($qa['user_email']) ? $qa['user_email'] : ''),
                    'comment_date'         => $comment_date,
                    'comment_date_gmt'     => $comment_date,
                    'comment_approved'     => isset($qa['approve']) && $qa['approve'] === 'yes' ? 'yes' : 'no',
                );

                $comment_id = wp_insert_comment($comment_data);

                if ($comment_id) {
                    update_comment_meta($comment_id, 'qa_answer', (!empty($qa['answer']) ? $qa['answer'] : ''));
                    $productQas[$qkey]['comment_id'] = $comment_id;
                }
            }
        }

        if ($migration_needed) {
            update_post_meta($productId, 'ets_question_answer', $productQas);
        }

        // Mark as migrated
        update_post_meta($productId, 'ets_qa_migrated', 'yes');
    }
}

$etsWooCentralisedQaList = new ETS_WOO_CENTRALISED_QA_LIST();
