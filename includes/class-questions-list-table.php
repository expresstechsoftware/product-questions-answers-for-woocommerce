<?php
/**
 * Questions List Table
 *
 * Displays and manages product questions in WooCommerce admin.
 *
 * @package Product_Questions_Answers_For_WooCommerce
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Questions_List_Table extends WP_List_Table
{

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'question',
            'plural' => 'questions',
            'ajax' => false,
        ));
    }

    public function get_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'product' => esc_html__('Product', 'product-questions-answers-for-woocommerce'),
            'question' => esc_html__('Question', 'product-questions-answers-for-woocommerce'),
            'username' => esc_html__('Username', 'product-questions-answers-for-woocommerce'),
            'email' => esc_html__('Email', 'product-questions-answers-for-woocommerce'),
            'answer' => esc_html__('Answer', 'product-questions-answers-for-woocommerce'),
            'date' => esc_html__('Date', 'product-questions-answers-for-woocommerce'),
        );

        if (class_exists('PRODUCT_QUESTIONS_ANSWERS_FOR_WOOCOMMERCE_PREMIUM')) {
            $columns['votes'] = esc_html__('Votes (Likes/Dislikes)', 'product-questions-answers-for-woocommerce');
        }

        return $columns;
    }

    public function get_sortable_columns()
    {
        $sortable = array(
            'date' => array('date', true),
            'product' => array('product', false),
        );

        if (class_exists('PRODUCT_QUESTIONS_ANSWERS_FOR_WOOCOMMERCE_PREMIUM')) {
            $sortable['votes'] = array('votes', false);
        }

        return $sortable;
    }

    public function get_bulk_actions()
    {
        return array(
            'delete' => esc_html__('Delete', 'product-questions-answers-for-woocommerce'),
        );
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="question[]" value="%s" />', $item['id']);
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'product':
            case 'question':
            case 'username':
            case 'email':
            case 'answer':
            case 'date':
            case 'votes':
                return $item[$column_name];
            default:
                return '';
        }
    }

    public function column_question($item)
    {
        $actions = array(
            'view' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url(get_permalink($item['product_id'])),
                esc_html__('View on Product', 'product-questions-answers-for-woocommerce')
            ),
        );
        return sprintf('%s %s', $item['question'], $this->row_actions($actions));
    }

    public function filter_dropdown()
    {
        $allowed_filters = array('all', 'pending_answers', 'pending_approval');
        $selected_filter = isset($_REQUEST['filter']) && in_array($_REQUEST['filter'], $allowed_filters, true)
            ? sanitize_text_field($_REQUEST['filter'])
            : 'all';
        ?>
        <select name="filter" id="filter" class="ets_qa_cnt_algn">
            <option value="all" <?php selected($selected_filter, 'all'); ?>>
                <?php esc_html_e('All Questions', 'product-questions-answers-for-woocommerce'); ?>
            </option>
            <option value="pending_answers" <?php selected($selected_filter, 'pending_answers'); ?>>
                <?php esc_html_e('Pending Answers', 'product-questions-answers-for-woocommerce'); ?>
            </option>
            <option value="pending_approval" <?php selected($selected_filter, 'pending_approval'); ?>>
                <?php esc_html_e('Pending Approval', 'product-questions-answers-for-woocommerce'); ?>
            </option>
        </select>
        <?php wp_nonce_field('filter_questions', 'questions_filter_nonce'); ?>
        <input type="submit" class="button ets_qa_cnt_algn"
            value="<?php esc_attr_e('Filter', 'product-questions-answers-for-woocommerce'); ?>" />
        <?php
    }

    public function process_bulk_action()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if ('delete' === $this->current_action()) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
                wp_die(esc_html__('Security check failed.', 'product-questions-answers-for-woocommerce'));
            }

            $question_ids = isset($_POST['question']) ? array_map('intval', $_POST['question']) : array();
            if (empty($question_ids)) {
                return;
            }

            global $wpdb;
            $deleted_count = 0;

            foreach ($question_ids as $comment_id) {
                $comment = get_comment($comment_id);
                if ($comment && $comment->comment_type === 'ets_wc_qa') {
                    $product_id = $comment->comment_post_ID;
                    $product_qas = get_post_meta($product_id, 'ets_question_answer', true);

                    if ($product_qas && is_array($product_qas)) {
                        foreach ($product_qas as $key => $qa) {
                            if (isset($qa['comment_id']) && (int) $qa['comment_id'] === (int) $comment_id) {
                                unset($product_qas[$key]);
                                break;
                            }
                        }
                        $product_qas = array_values($product_qas);
                        if (!empty($product_qas)) {
                            update_post_meta($product_id, 'ets_question_answer', $product_qas);
                        } else {
                            delete_post_meta($product_id, 'ets_question_answer');
                        }
                    }

                    $wpdb->delete($wpdb->commentmeta, array('comment_id' => $comment_id), array('%d'));

                    $votes_table = $wpdb->prefix . 'qa_product_votes';
                    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $votes_table)) === $votes_table) {
                        $wpdb->delete($votes_table, array('comment_id' => $comment_id), array('%d'));
                    }

                    if (wp_delete_comment($comment_id, true)) {
                        $deleted_count++;
                    }
                }
            }

            if ($deleted_count > 0) {
                wp_redirect(add_query_arg(array('deleted' => $deleted_count), wp_get_referer()));
                exit;
            }
        }
    }

    public function admin_notices()
    {
        if (isset($_GET['deleted']) && (int) $_GET['deleted'] > 0) {
            $deleted_count = (int) $_GET['deleted'];
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(
                esc_html(_n(
                    '%d question deleted successfully.',
                    '%d questions deleted successfully.',
                    $deleted_count,
                    'product-questions-answers-for-woocommerce'
                )),
                $deleted_count
            );
            echo '</p></div>';
        }
    }

    public function prepare_items()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'product-questions-answers-for-woocommerce'));
        }

        $this->process_bulk_action();

        if (isset($_POST['filter']) && $_POST['filter'] !== 'all') {
            if (!isset($_POST['questions_filter_nonce']) || !wp_verify_nonce($_POST['questions_filter_nonce'], 'filter_questions')) {
                wp_die(esc_html__('Security check failed.', 'product-questions-answers-for-woocommerce'));
            }
        }
        if (isset($_GET['filter']) && $_GET['filter'] !== 'all') {
            if (!isset($_GET['questions_filter_nonce']) || !wp_verify_nonce($_GET['questions_filter_nonce'], 'filter_questions')) {
                wp_die(esc_html__('Security check failed.', 'product-questions-answers-for-woocommerce'));
            }
        }

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable, 'question');

        $per_page = $this->get_items_per_page('questions_per_page', 10);
        $current_page = $this->get_pagenum();
        $data = $this->get_table_data($per_page, $current_page);
        $this->items = $data['items'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page),
        ));
    }

    public function get_table_data($per_page = 10, $current_page = 1)
    {
        global $wpdb;

        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $allowed_filters = array('all', 'pending_answers', 'pending_approval');
        $filter = isset($_REQUEST['filter']) && in_array($_REQUEST['filter'], $allowed_filters, true)
            ? sanitize_text_field($_REQUEST['filter'])
            : 'all';

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), array('ASC', 'DESC'), true)
            ? strtoupper($_GET['order'])
            : 'DESC';

        $votes_table = $wpdb->prefix . 'qa_product_votes';
        $votes_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $votes_table)) === $votes_table;

        $select_clause = "
			SELECT c.comment_ID, c.comment_post_ID, c.comment_content,
			       c.comment_author, c.comment_author_email, c.user_id,
			       c.comment_date, c.comment_approved,
			       pm.meta_value as answer, p.post_title, p.ID as product_id";

        if ($votes_table_exists) {
            $select_clause .= ",
				COALESCE(likes.like_count, 0) as like_count,
				COALESCE(dislikes.dislike_count, 0) as dislike_count";
        }

        $from_clause = "
			FROM {$wpdb->comments} c
			LEFT JOIN {$wpdb->commentmeta} pm ON c.comment_ID = pm.comment_id AND pm.meta_key = 'qa_answer'
			LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID";

        if ($votes_table_exists) {
            $from_clause .= "
			LEFT JOIN (SELECT comment_id, COUNT(*) as like_count FROM {$votes_table} WHERE type='like' GROUP BY comment_id) likes ON c.comment_ID = likes.comment_id
			LEFT JOIN (SELECT comment_id, COUNT(*) as dislike_count FROM {$votes_table} WHERE type='dislike' GROUP BY comment_id) dislikes ON c.comment_ID = dislikes.comment_id";
        }

        $where_clause = $wpdb->prepare(' WHERE c.comment_type = %s AND p.post_status = %s', 'ets_wc_qa', 'publish');

        if ($filter === 'pending_answers') {
            $where_clause .= " AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = '0')";
        } elseif ($filter === 'pending_approval') {
            $where_clause .= " AND c.comment_approved = 'no'";
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_clause .= $wpdb->prepare(
                ' AND (c.comment_content LIKE %s OR pm.meta_value LIKE %s OR p.post_title LIKE %s OR c.comment_author LIKE %s)',
                $like,
                $like,
                $like,
                $like
            );
        }

        $count_query = "SELECT COUNT(DISTINCT c.comment_ID) FROM {$wpdb->comments} c
			LEFT JOIN {$wpdb->commentmeta} pm ON c.comment_ID = pm.comment_id AND pm.meta_key = 'qa_answer'
			LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID" . $where_clause;

        $total_items = (int) $wpdb->get_var($count_query);

        $order_clause = ' ORDER BY ';
        switch ($orderby) {
            case 'votes':
                $order_clause .= $votes_table_exists ? "like_count {$order}, dislike_count {$order}" : 'c.comment_date DESC';
                break;
            case 'product':
                $order_clause .= "p.post_title {$order}";
                break;
            default:
                $order_clause .= "c.comment_date {$order}";
        }

        $offset = ($current_page - 1) * $per_page;
        $limit_clause = $wpdb->prepare(' LIMIT %d OFFSET %d', $per_page, $offset);

        $comments = $wpdb->get_results($select_clause . $from_clause . $where_clause . $order_clause . $limit_clause);

        if ($wpdb->last_error) {
            error_log('Questions List Table Error: ' . $wpdb->last_error);
            return array('items' => array(), 'total' => 0);
        }

        $all_questions = array();
        if ($comments) {
            foreach ($comments as $comment) {
                $username_display = $comment->user_id > 0
                    ? '<a href="' . esc_url(get_edit_user_link($comment->user_id)) . '">' . esc_html($comment->comment_author) . '</a>'
                    : esc_html($comment->comment_author) . ' <em>(' . esc_html__('Guest', 'product-questions-answers-for-woocommerce') . ')</em>';

                $answer_display = !empty($comment->answer) && $comment->answer !== '0'
                    ? esc_html($comment->answer)
                    : '<em>' . esc_html__('No answer yet', 'product-questions-answers-for-woocommerce') . '</em>';

                $votes_display = $votes_table_exists
                    ? sprintf('%d / %d', (int) $comment->like_count, (int) $comment->dislike_count)
                    : esc_html__('N/A', 'product-questions-answers-for-woocommerce');

                $all_questions[] = array(
                    'id' => $comment->comment_ID,
                    'product_id' => $comment->product_id,
                    'product' => '<a href="' . esc_url(get_edit_post_link($comment->product_id)) . '">' . esc_html($comment->post_title) . '</a>',
                    'question' => esc_html($comment->comment_content),
                    'email' => '<a href="mailto:' . esc_attr($comment->comment_author_email) . '">' . esc_html($comment->comment_author_email) . '</a>',
                    'username' => $username_display,
                    'answer' => $answer_display,
                    'date' => esc_html(get_comment_date('d F Y', $comment->comment_ID)),
                    'votes' => esc_html($votes_display),
                );
            }
        }

        return array('items' => $all_questions, 'total' => $total_items);
    }
}
