<?php
/**
 * QA Helper Functions - Fetch QA data from WordPress comments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetch QA data from WordPress comments table
 *
 * @param int   $product_id Product ID
 * @param bool  $approved_only Return only approved questions (default: true)
 * @param int   $offset Pagination offset (default: 0)
 * @param int   $number Number of items to fetch (default: 0 = all)
 *
 * @return array Array of QA data normalized to match post meta structure
 */
function ets_get_qa_from_comments( $product_id, $approved_only = true, $offset = 0, $number = 0 ) {
	global $wpdb;

	$where = $wpdb->prepare(
		"WHERE comment_post_ID = %d AND comment_type = 'ets_wc_qa'",
		$product_id
	);

	if ( $approved_only ) {
		$where .= " AND comment_approved = 'yes'";
	}

	// Get total count
	$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} {$where}" );

	if ( $total == 0 ) {
		return array();
	}

	// Build query
	$limit = '';
	if ( $number > 0 ) {
		$limit = $wpdb->prepare( ' LIMIT %d OFFSET %d', $number, $offset );
	}

	$query = "SELECT comment_ID, comment_post_ID, comment_author, comment_author_email, user_id, comment_content, comment_date, comment_approved
	          FROM {$wpdb->comments}
	          {$where}
	          ORDER BY comment_date ASC
	          {$limit}";

	$comments = $wpdb->get_results( $query );

	if ( empty( $comments ) ) {
		return array();
	}

	$qa_data = array();

	foreach ( $comments as $comment ) {
		$answer = get_comment_meta( $comment->comment_ID, 'qa_answer', true );
		$product_title = get_comment_meta( $comment->comment_ID, 'product_title', true );

		// Format date to match post meta format (d-M-Y)
		$date = date_i18n( 'd-M-Y', strtotime( $comment->comment_date ) );

		// Get product title if not stored in comment meta
		if ( empty( $product_title ) ) {
			$product_title = get_the_title( $comment->comment_post_ID );
		}

		$qa_data[] = array(
			'comment_id'     => intval( $comment->comment_ID ),
			'question'       => $comment->comment_content,
			'answer'         => $answer ? $answer : '',
			'user_name'      => $comment->comment_author,
			'user_email'     => $comment->comment_author_email,
			'user_id'        => intval( $comment->user_id ),
			'date'           => $date,
			'approve'        => $comment->comment_approved, // Already 'yes' or 'no'
			'product_title'  => $product_title,
		);
	}

	return $qa_data;
}

/**
 * Count total approved QAs for a product
 *
 * @param int $product_id Product ID
 *
 * @return int Total count of approved questions
 */
function ets_count_qa_from_comments( $product_id ) {
	global $wpdb;

	// Count comments where comment_approved = 'yes' (custom value from migration)
	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->comments}
			 WHERE comment_post_ID = %d
			 AND comment_type = 'ets_wc_qa'
			 AND comment_approved = 'yes'",
			$product_id
		)
	);

	return intval( $count );
}

/**
 * Create a new QA comment
 *
 * @param int    $product_id Product ID
 * @param string $question Question text
 * @param string $answer Answer text (can be empty for new questions)
 * @param string $user_name Username
 * @param string $user_email User email
 * @param int    $user_id User ID
 * @param string $approved 'yes' or 'no'
 * @param string $product_title Optional product title
 *
 * @return int|false Comment ID on success, false on failure
 */
function ets_create_qa_comment( $product_id, $question, $answer, $user_name, $user_email, $user_id, $approved = 'no', $product_title = '' ) {
	$comment_data = array(
		'comment_post_ID'      => intval( $product_id ),
		'comment_author'       => sanitize_text_field( $user_name ),
		'comment_author_email' => sanitize_email( $user_email ),
		'comment_content'      => sanitize_textarea_field( $question ),
		'user_id'              => intval( $user_id ),
		'comment_type'         => 'ets_wc_qa',
		'comment_approved'     => in_array( $approved, array( 'yes', 'no' ) ) ? $approved : 'no',
		'comment_date'         => current_time( 'mysql' ),
	);

	$comment_id = wp_insert_comment( $comment_data );

	if ( $comment_id ) {
		update_comment_meta( $comment_id, 'qa_answer', sanitize_textarea_field( $answer ) );
		if ( ! empty( $product_title ) ) {
			update_comment_meta( $comment_id, 'product_title', sanitize_text_field( $product_title ) );
		}
	}

	return $comment_id;
}

/**
 * Update an existing QA comment
 *
 * @param int    $comment_id Comment ID
 * @param string $question Question text
 * @param string $answer Answer text
 * @param string $approved 'yes' or 'no'
 *
 * @return int|false Comment ID on success, false on failure
 */
function ets_update_qa_comment( $comment_id, $question, $answer, $approved = 'no' ) {
	$comment_data = array(
		'comment_ID'       => intval( $comment_id ),
		'comment_content'  => sanitize_textarea_field( $question ),
		'comment_approved' => in_array( $approved, array( 'yes', 'no' ) ) ? $approved : 'no',
	);

	// Update the comment (may return 0 if nothing changed, but that's OK)
	wp_update_comment( $comment_data );

	// Always update the answer meta (this is the important part)
	update_comment_meta( intval( $comment_id ), 'qa_answer', sanitize_textarea_field( $answer ) );

	return intval( $comment_id );
}

/**
 * Delete a QA comment
 *
 * @param int $comment_id Comment ID
 *
 * @return bool True on success
 */
function ets_delete_qa_comment( $comment_id ) {
	return wp_delete_comment( intval( $comment_id ), true );
}
