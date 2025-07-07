<?php
/**
 * Audit Page class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Audit Page class
 *
 * Handles audit page rendering and analysis
 */
class TTVGPTAuditPage {
	/**
	 * Initialize audit page
	 */
	public function __construct() {
		// Constructor logic if needed
	}

	/**
	 * Render audit analysis page
	 *
	 * @return void
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only audit page
		$year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only audit page
		$month = isset( $_GET['month'] ) ? absint( $_GET['month'] ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only audit page
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

		if ( ! $year || ! $month ) {
			$most_recent = TTVGPTAuditHelper::get_most_recent_month();
			if ( $most_recent ) {
				$year  = $most_recent['year'];
				$month = $most_recent['month'];
			} else {
				?>
				<div class="wrap">
					<h1><?php esc_html_e( 'Tekst TV GPT Audit', 'zw-ttvgpt' ); ?></h1>
					<div class="notice notice-info">
						<p><?php esc_html_e( 'Geen posts gevonden voor audit analyse.', 'zw-ttvgpt' ); ?></p>
					</div>
				</div>
				<?php
				return;
			}
		}

		// Run comprehensive benchmark by default (can be disabled with constant)
		$benchmark_results = null;
		$run_benchmark     = ! ( defined( 'ZW_TTVGPT_DISABLE_BENCHMARK' ) && ZW_TTVGPT_DISABLE_BENCHMARK );

		if ( $run_benchmark ) {
			$benchmark_results = TTVGPTAuditHelper::run_comprehensive_benchmark( $year, $month );
			// Use fastest strategy for actual data
			$fastest_strategy = $benchmark_results['analysis']['fastest_strategy'];
			if ( ! defined( 'ZW_TTVGPT_BENCHMARK_METHOD' ) ) {
				define( 'ZW_TTVGPT_BENCHMARK_METHOD', $fastest_strategy );
			}
		}

		$posts  = TTVGPTAuditHelper::get_posts( $year, $month );
		$counts = array(
			'fully_human_written'   => 0,
			'ai_written_not_edited' => 0,
			'ai_written_edited'     => 0,
		);

		// Bulk fetch all meta data in one query to avoid N+1 problem
		$post_ids   = array_map(
			static function ( $post ) {
				return $post->ID;
			},
			$posts
		);
		$meta_cache = TTVGPTAuditHelper::get_bulk_meta_data( $post_ids );

		$categorized_posts = array();
		foreach ( $posts as $post ) {
			$analysis = TTVGPTAuditHelper::categorize_post( $post, $meta_cache );
			++$counts[ $analysis['status'] ];

			// Apply status filter if set
			if ( empty( $status_filter ) || $analysis['status'] === $status_filter ) {
				$categorized_posts[] = array_merge( array( 'post' => $post ), $analysis );
			}
		}

		$available_months = TTVGPTAuditHelper::get_months();

		// Ensure CSS is loaded
		$version = ZW_TTVGPT_VERSION . ( TTVGPTSettingsManager::is_debug_mode() ? '.' . time() : '' );
		wp_enqueue_style( 'zw-ttvgpt-audit', ZW_TTVGPT_URL . 'assets/audit.css', array(), $version );
		wp_print_styles( array( 'zw-ttvgpt-audit' ) );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Tekst TV GPT Audit', 'zw-ttvgpt' ); ?></h1>
			<hr class="wp-header-end">
			
			<?php $this->render_navigation( $year, $month, $available_months, $status_filter ); ?>
			<?php
			if ( $benchmark_results ) {
				$this->render_benchmark_results( $benchmark_results );}
			?>
			<?php $this->render_summary( $counts ); ?>
			<?php $this->render_audit_list( $categorized_posts, $meta_cache ); ?>
		</div>
		<?php
	}


	/**
	 * Render benchmark results section
	 *
	 * @param array $benchmark_results Benchmark data
	 * @return void
	 */
	private function render_benchmark_results( array $benchmark_results ): void {
		?>
		<div class="zw-benchmark-results" style="background: #f0f6fc; border: 1px solid #0073aa; border-radius: 4px; padding: 16px; margin: 20px 0;">
			<h3 style="margin: 0 0 12px 0; color: #0073aa;">🚀 Database Performance Benchmark</h3>
			
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 16px;">
				<?php foreach ( $benchmark_results['strategies'] as $strategy => $data ) : ?>
					<div style="background: white; padding: 8px 12px; border-radius: 3px; <?php echo $strategy === $benchmark_results['analysis']['fastest_strategy'] ? 'border: 2px solid #00a32a;' : 'border: 1px solid #ddd;'; ?>">
						<div style="font-weight: 600; font-size: 12px; color: #646970; margin-bottom: 4px;">
							<?php echo esc_html( strtoupper( str_replace( '_', ' ', $strategy ) ) ); ?>
							<?php if ( $strategy === $benchmark_results['analysis']['fastest_strategy'] ) : ?>
								<span style="color: #00a32a;">🏆</span>
							<?php endif; ?>
						</div>
						<div style="font-size: 18px; font-weight: 600; color: <?php echo $strategy === $benchmark_results['analysis']['fastest_strategy'] ? '#00a32a' : '#1d2327'; ?>;">
							<?php echo esc_html( $data['avg_time_ms'] ); ?>ms
						</div>
						<div style="font-size: 11px; color: #646970;">
							<?php echo esc_html( $data['result_count'] ); ?> results
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			
			<div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
				<div>
					<strong>🏆 Fastest:</strong> <?php echo esc_html( strtoupper( str_replace( '_', ' ', $benchmark_results['analysis']['fastest_strategy'] ) ) ); ?>
					(<?php echo esc_html( $benchmark_results['analysis']['fastest_time_ms'] ); ?>ms)
				</div>
				<div>
					<strong>📊 Total Dashboard:</strong> <?php echo esc_html( $benchmark_results['analysis']['total_dashboard_time_ms'] ); ?>ms
					<?php if ( $benchmark_results['analysis']['total_dashboard_time_ms'] < 100 ) : ?>
						<span style="color: #00a32a;">🚀 Excellent!</span>
					<?php elseif ( $benchmark_results['analysis']['total_dashboard_time_ms'] < 200 ) : ?>
						<span style="color: #dba617;">✅ Good!</span>
					<?php elseif ( $benchmark_results['analysis']['total_dashboard_time_ms'] < 500 ) : ?>
						<span style="color: #d63638;">⚠️ Acceptable</span>
					<?php else : ?>
						<span style="color: #d63638;">❌ Slow</span>
					<?php endif; ?>
				</div>
				<div>
					<strong>Database:</strong> <?php echo esc_html( number_format( $benchmark_results['database_info']['posts_count'] ) ); ?> posts, 
					<?php echo esc_html( number_format( $benchmark_results['database_info']['postmeta_count'] ) ); ?> meta
				</div>
			</div>
			
			<div style="margin-top: 12px; font-size: 12px; color: #646970;">
				Results saved to: <code>/wp-content/uploads/zw-ttvgpt-benchmark.txt</code> and <code>.json</code>
			</div>
		</div>
		<?php
	}

	/**
	 * Render simple summary boxes
	 *
	 * @param array $counts Statistics counts
	 * @return void
	 */
	private function render_summary( array $counts ): void {
		$labels = array(
			'fully_human_written'   => __( 'Handmatig', 'zw-ttvgpt' ),
			'ai_written_not_edited' => __( 'AI Gegenereerd', 'zw-ttvgpt' ),
			'ai_written_edited'     => __( 'AI + Redigering', 'zw-ttvgpt' ),
		);

		$css_classes = array(
			'fully_human_written'   => 'human',
			'ai_written_not_edited' => 'ai-unedited',
			'ai_written_edited'     => 'ai-edited',
		);
		?>
		
		<div class="zw-audit-summary">
			<?php foreach ( $counts as $status => $count ) : ?>
				<div class="zw-audit-summary-item <?php echo esc_attr( $css_classes[ $status ] ); ?>">
					<div class="zw-audit-summary-number">
						<?php echo esc_html( (string) $count ); ?>
					</div>
					<div class="zw-audit-summary-label">
						<?php echo esc_html( $labels[ $status ] ); ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render native WordPress-style list
	 *
	 * @param array $categorized_posts Array of categorized posts
	 * @param array $meta_cache Meta data cache to avoid N+1 queries
	 * @return void
	 */
	private function render_audit_list( array $categorized_posts, array $meta_cache ): void {
		if ( empty( $categorized_posts ) ) {
			?>
			<div class="zw-audit-empty">
				<p><?php esc_html_e( 'Geen artikelen gevonden voor de geselecteerde filters.', 'zw-ttvgpt' ); ?></p>
			</div>
			<?php
			return;
		}

		$type_labels = array(
			'fully_human_written'   => __( 'H', 'zw-ttvgpt' ),
			'ai_written_not_edited' => __( 'AI', 'zw-ttvgpt' ),
			'ai_written_edited'     => __( 'AI+', 'zw-ttvgpt' ),
		);

		$css_classes = array(
			'fully_human_written'   => 'human',
			'ai_written_not_edited' => 'ai-unedited',
			'ai_written_edited'     => 'ai-edited',
		);
		?>
		
		<div class="zw-audit-list">
			<?php foreach ( $categorized_posts as $item ) : ?>
				<?php
				$post          = $item['post'];
				$status        = $item['status'];
				$ai_content    = $item['ai_content'];
				$human_content = $item['human_content'];

				$author      = get_userdata( $post->post_author );
				$edit_last   = $meta_cache[ $post->ID ]['_edit_last'] ?? '';
				$last_editor = is_numeric( $edit_last ) ? get_userdata( (int) $edit_last ) : false;
				$post_url    = get_edit_post_link( $post->ID );
				?>
				
				<div class="zw-audit-item <?php echo esc_attr( $css_classes[ $status ] ); ?>">
					<div class="zw-audit-row">
						<div class="zw-audit-type">
							<?php echo esc_html( $type_labels[ $status ] ); ?>
						</div>
						
						<div class="zw-audit-content">
							<div class="zw-audit-title">
								<?php if ( $post_url ) : ?>
									<a href="<?php echo esc_url( $post_url ); ?>">
										<?php echo esc_html( get_the_title( $post->ID ) ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( get_the_title( $post->ID ) ); ?>
								<?php endif; ?>
							</div>
							
							<div class="zw-audit-meta">
								<span>
									<?php
									$post_date = get_the_date( 'j M Y', $post->ID );
									echo esc_html( is_string( $post_date ) ? $post_date : '' );
									?>
								</span>
								<span><?php echo esc_html( $author ? $author->display_name : 'Onbekend' ); ?></span>
								<?php if ( $last_editor && $last_editor->ID !== $post->post_author ) : ?>
									<span><?php esc_html_e( 'Bewerkt door', 'zw-ttvgpt' ); ?> <?php echo esc_html( $last_editor->display_name ); ?></span>
								<?php endif; ?>
								<a href="<?php echo esc_url( get_permalink( $post->ID ) ? get_permalink( $post->ID ) : '' ); ?>" target="_blank">
									<?php esc_html_e( 'Bekijk', 'zw-ttvgpt' ); ?>
								</a>
							</div>
							
							<?php if ( 'ai_written_edited' === $status ) : ?>
								<?php $diff = TTVGPTAuditHelper::generate_word_diff( $ai_content, $human_content ); ?>
								<div class="zw-audit-diff">
									<div class="zw-audit-diff-block before">
										<div class="zw-audit-diff-header"><?php esc_html_e( 'AI Versie', 'zw-ttvgpt' ); ?></div>
										<?php echo wp_kses_post( $diff['before'] ); ?>
									</div>
									<div class="zw-audit-diff-block after">
										<div class="zw-audit-diff-header"><?php esc_html_e( 'Geredigeerde Versie', 'zw-ttvgpt' ); ?></div>
										<?php echo wp_kses_post( $diff['after'] ); ?>
									</div>
								</div>
							<?php else : ?>
								<div class="zw-audit-preview">
									<?php echo esc_html( $human_content ); ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render audit navigation dropdown like WordPress Posts screen
	 *
	 * @param int    $year Current year
	 * @param int    $month Current month
	 * @param array  $available_months All available months
	 * @param string $status_filter Current status filter
	 * @return void
	 */
	private function render_navigation( int $year, int $month, array $available_months, string $status_filter ): void {
		?>
		<div class="tablenav top">
			<div class="alignleft actions">
				<label for="filter-by-date" class="screen-reader-text"><?php esc_html_e( 'Filter op datum', 'zw-ttvgpt' ); ?></label>
				<select name="m" id="filter-by-date">
					<option value=""><?php esc_html_e( 'Alle datums', 'zw-ttvgpt' ); ?></option>
					<?php foreach ( $available_months as $month_data ) : ?>
						<?php
						$option_value  = $month_data['year'] . sprintf( '%02d', $month_data['month'] );
						$current_value = $year . sprintf( '%02d', $month );
						$is_selected   = $option_value === $current_value;
						?>
						<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $is_selected ); ?>>
							<?php echo esc_html( date_i18n( 'F Y', mktime( 0, 0, 0, $month_data['month'], 1, $month_data['year'] ) ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				
				<label for="filter-by-status" class="screen-reader-text"><?php esc_html_e( 'Filter op type', 'zw-ttvgpt' ); ?></label>
				<select name="status" id="filter-by-status">
					<option value=""><?php esc_html_e( 'Alle types', 'zw-ttvgpt' ); ?></option>
					<option value="fully_human_written" <?php selected( $status_filter, 'fully_human_written' ); ?>>
						<?php esc_html_e( 'Volledig handmatig', 'zw-ttvgpt' ); ?>
					</option>
					<option value="ai_written_not_edited" <?php selected( $status_filter, 'ai_written_not_edited' ); ?>>
						<?php esc_html_e( 'AI, niet bewerkt', 'zw-ttvgpt' ); ?>
					</option>
					<option value="ai_written_edited" <?php selected( $status_filter, 'ai_written_edited' ); ?>>
						<?php esc_html_e( 'AI, bewerkt', 'zw-ttvgpt' ); ?>
					</option>
				</select>
				
				<input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php esc_attr_e( 'Filter', 'zw-ttvgpt' ); ?>">
			</div>
		</div>
		
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			function applyFilters() {
				var dateValue = $('#filter-by-date').val();
				var statusValue = $('#filter-by-status').val();
				var url = '<?php echo esc_js( admin_url( 'tools.php?page=zw-ttvgpt-audit' ) ); ?>';
				var params = [];
				
				if (dateValue) {
					var year = dateValue.substring(0, 4);
					var month = parseInt(dateValue.substring(4, 6), 10);
					params.push('year=' + year);
					params.push('month=' + month);
				}
				
				if (statusValue) {
					params.push('status=' + statusValue);
				}
				
				if (params.length > 0) {
					url += '&' + params.join('&');
				}
				
				window.location.href = url;
			}
			
			$('#filter-by-date, #filter-by-status').on('change', applyFilters);
			
			$('#post-query-submit').on('click', function(e) {
				e.preventDefault();
				applyFilters();
			});
		});
		</script>
		<?php
	}
}