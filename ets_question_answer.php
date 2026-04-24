<?php
/**
 * Plugin Name: Product Questions & Answers for WooCommerce
 * Plugin URI:  https://www.expresstechsoftwares.com
 * Description: <code><strong>ETS WooCommerce Questions And Answers</strong></code> offers a rapid way to manage dynamic discussions about your Woo products. <a href="https://www.expresstechsoftwares.com">Get more plugins and custom development for WordPress on <strong>ETS</strong></a>.
 * Version: 1.3.0
 * Author: ExpressTech Software Solutions Pvt. Ltd.
 * Author URI: https://www.expresstechsoftwares.com
 * Requires at least: 5.6
 * WC tested up to: 9.7.1
 * Requires PHP: 7.0
 * Text Domain: product-questions-answers-for-woocommerce
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ETS_WOO_QA_PATH', plugin_dir_url( __FILE__ ) );
define( 'ETS_WOO_QA_VERSION', '1.3.0' );
define( 'ETS_WOO_QA_BASE_PLUGIN_ACTIVE', true ); // Required by premium plugin

class ETS_WOO_PRODUCT_QUESTION_ANSWER {
	public function __construct() {
		add_action( 'init', array( $this, 'qa_load_local' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'check_for_upgrade' ) );
		register_activation_hook( __FILE__, array( $this, 'on_plugin_activation' ) );

		require 'includes/ets_qa_helpers.php';
		require 'includes/ets_admin_qa_function.php';
		require 'includes/ets_user_qa_function.php';
		require 'includes/ets_centralised_qa_list.php';
	}

	public function qa_load_local() {
		$localeDir = dirname( plugin_dir_path( __FILE__ ) ) . '/question-answer-plugin-by-ets/languages/product-questions-answers-for-woocommerce-' . get_locale() . '.mo';
		$res       = load_textdomain( 'product-questions-answers-for-woocommerce', $localeDir );
	}

	public function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	/**
	 * Runs on plugin activation.
	 * First-ever install: marks migration as not needed.
	 * Re-activation on existing install: checks for legacy post meta.
	 */
	/**
	 * Runs on plugin activation.
	 * Always checks actual data — never assumes "new install" based on missing version option,
	 * because the old plugin never set ets_woo_qa_db_version so existing users would hit that
	 * branch incorrectly and skip migration.
	 */
	public function on_plugin_activation() {
		update_option( 'ets_woo_qa_db_version', ETS_WOO_QA_VERSION );
		$this->schedule_migration_if_needed();
	}

	/**
	 * Runs on every plugins_loaded.
	 * Triggers migration check on version bump, and also acts as a self-healer:
	 * if migration was never completed (e.g. wrongly skipped), re-checks every load
	 * until ets_qa_sync_completed = 'yes'.
	 */
	public function check_for_upgrade() {
		$installed = get_option( 'ets_woo_qa_db_version', '0' );
		if ( version_compare( $installed, ETS_WOO_QA_VERSION, '<' ) ) {
			update_option( 'ets_woo_qa_db_version', ETS_WOO_QA_VERSION );
			$this->schedule_migration_if_needed();
		} elseif ( get_option( 'ets_qa_sync_completed' ) !== 'yes' ) {
			// Migration still pending — re-verify on every load until done
			$this->schedule_migration_if_needed();
		}
	}

	/**
	 * Queries for products that have ets_question_answer post meta
	 * but have NOT yet been migrated (no ets_qa_migrated flag).
	 *
	 * Uses the ets_qa_migrated flag (set per-product by process_migration_batch)
	 * rather than just checking if ets_question_answer exists — because migration
	 * keeps the post meta intact (with comment_ids added), so a plain existence
	 * check would always return true even after migration is complete.
	 */
	private function schedule_migration_if_needed() {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT pm.post_id)
			 FROM {$wpdb->postmeta} pm
			 LEFT JOIN {$wpdb->postmeta} pm_done
			     ON pm_done.post_id = pm.post_id
			     AND pm_done.meta_key = 'ets_qa_migrated'
			 WHERE pm.meta_key = 'ets_question_answer'
			 AND pm.meta_value != ''
			 AND pm.meta_value NOT IN ('a:0:{}', 'b:0;')
			 AND pm_done.meta_id IS NULL"
		);
		if ( $count > 0 ) {
			update_option( 'ets_qa_sync_completed', 'no' );
		} else {
			update_option( 'ets_qa_sync_completed', 'yes' );
		}
	}
}
$etsWooProductQuestionAnswer = new ETS_WOO_PRODUCT_QUESTION_ANSWER();
