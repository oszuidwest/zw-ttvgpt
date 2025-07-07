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
	 * Logger instance
	 *
	 * @var TTVGPTLogger
	 */
	private TTVGPTLogger $logger;

	/**
	 * Initialize audit page
	 *
	 * @param TTVGPTLogger $logger Logger instance for debugging
	 */
	public function __construct( TTVGPTLogger $logger ) {
		$this->logger = $logger;
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

		$posts  = TTVGPTAuditHelper::get_posts( $year, $month );
		$counts = array(
			'fully_human_written'   => 0,
			'ai_written_not_edited' => 0,
			'ai_written_edited'     => 0,
		);

		$categorized_posts = array();
		foreach ( $posts as $post ) {
			$analysis = TTVGPTAuditHelper::categorize_post( $post );
			++$counts[ $analysis['status'] ];

			// Apply status filter if set
			if ( empty( $status_filter ) || $analysis['status'] === $status_filter ) {
				$categorized_posts[] = array_merge( array( 'post' => $post ), $analysis );
			}
		}

		$available_months = TTVGPTAuditHelper::get_months();

		// Enqueue audit-specific CSS
		add_action( 'admin_head', array( $this, 'enqueue_audit_styles' ) );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Tekst TV GPT Audit', 'zw-ttvgpt' ); ?></h1>
			<hr class="wp-header-end">
			
			<?php $this->render_navigation( $year, $month, $available_months, $status_filter ); ?>
			
			<?php $this->render_statistics_dashboard( $counts ); ?>
			
			<?php $this->render_articles_list( $categorized_posts ); ?>
		</div>
		<?php
	}

	/**
	 * Enqueue audit-specific styles
	 *
	 * @return void
	 */
	public function enqueue_audit_styles(): void {
		$version = ZW_TTVGPT_VERSION;
		if ( TTVGPTSettingsManager::is_debug_mode() ) {
			$version .= '.' . time();
		}

		wp_enqueue_style(
			'zw-ttvgpt-audit',
			ZW_TTVGPT_URL . 'assets/audit.css',
			array(),
			$version
		);
	}

	/**
	 * Render modern statistics dashboard
	 *
	 * @param array $counts Statistics counts
	 * @return void
	 */
	private function render_statistics_dashboard( array $counts ): void {
		$labels = array(
			'fully_human_written'   => __( 'Volledig handmatig', 'zw-ttvgpt' ),
			'ai_written_not_edited' => __( 'AI, niet bewerkt', 'zw-ttvgpt' ),
			'ai_written_edited'     => __( 'AI, bewerkt', 'zw-ttvgpt' ),
		);

		$icons = array(
			'fully_human_written'   => 'edit',
			'ai_written_not_edited' => 'superhero',
			'ai_written_edited'     => 'admin-tools',
		);

		$css_classes = array(
			'fully_human_written'   => 'human',
			'ai_written_not_edited' => 'ai-unedited',
			'ai_written_edited'     => 'ai-edited',
		);

		$total_posts = array_sum( $counts );
		?>
		
		<div class="zw-audit-stats">
			<?php foreach ( $counts as $status => $count ) : ?>
				<div class="zw-audit-stat-card <?php echo esc_attr( $css_classes[ $status ] ); ?>">
					<div class="zw-audit-stat-number">
						<?php echo esc_html( (string) $count ); ?>
					</div>
					<div class="zw-audit-stat-label">
						<span class="dashicons dashicons-<?php echo esc_attr( $icons[ $status ] ); ?>"></span>
						<?php echo esc_html( $labels[ $status ] ); ?>
						<?php if ( $total_posts > 0 ) : ?>
							<div style="margin-top: 4px; font-size: 12px; opacity: 0.8;">
								<?php echo esc_html( round( ( $count / $total_posts ) * 100 ) ); ?>%
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render modern articles list
	 *
	 * @param array $categorized_posts Array of categorized posts
	 * @return void
	 */
	private function render_articles_list( array $categorized_posts ): void {
		if ( empty( $categorized_posts ) ) {
			?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'Geen artikelen gevonden voor de geselecteerde filters.', 'zw-ttvgpt' ); ?></p>
			</div>
			<?php
			return;
		}

		$labels = array(
			'fully_human_written'   => __( 'Handmatig', 'zw-ttvgpt' ),
			'ai_written_not_edited' => __( 'AI Origineel', 'zw-ttvgpt' ),
			'ai_written_edited'     => __( 'AI Bewerkt', 'zw-ttvgpt' ),
		);

		$css_classes = array(
			'fully_human_written'   => 'human',
			'ai_written_not_edited' => 'ai-unedited',
			'ai_written_edited'     => 'ai-edited',
		);
		?>
		
		<div class="zw-audit-articles">
			<?php foreach ( $categorized_posts as $item ) : ?>
				<?php
				$post          = $item['post'];
				$status        = $item['status'];
				$ai_content    = $item['ai_content'];
				$human_content = $item['human_content'];

				$author      = get_userdata( $post->post_author );
				$edit_last   = get_post_meta( $post->ID, '_edit_last', true );
				$last_editor = is_numeric( $edit_last ) ? get_userdata( (int) $edit_last ) : false;
				$post_url    = get_edit_post_link( $post->ID );
				?>
				
				<article class="zw-audit-article">
					<div class="zw-audit-article-header">
						<div>
							<h3 class="zw-audit-article-title">
								<?php if ( $post_url ) : ?>
									<a href="<?php echo esc_url( $post_url ); ?>" target="_blank">
										<?php echo esc_html( get_the_title( $post->ID ) ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( get_the_title( $post->ID ) ); ?>
								<?php endif; ?>
							</h3>
							
							<div class="zw-audit-article-meta">
								<span>
									<span class="dashicons dashicons-calendar-alt"></span>
									<?php echo esc_html( get_the_date( 'j F Y', $post->ID ) ); ?>
								</span>
								
								<span>
									<span class="dashicons dashicons-admin-users"></span>
									<?php echo esc_html( $author ? $author->display_name : 'Onbekend' ); ?>
								</span>
								
								<?php if ( $last_editor && $last_editor->ID !== $post->post_author ) : ?>
									<span>
										<span class="dashicons dashicons-edit"></span>
										<?php echo esc_html( $last_editor->display_name ); ?>
									</span>
								<?php endif; ?>
								
								<span>
									<span class="dashicons dashicons-visibility"></span>
									<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank">
										<?php esc_html_e( 'Bekijk live', 'zw-ttvgpt' ); ?>
									</a>
								</span>
							</div>
						</div>
						
						<div class="zw-audit-status-badge <?php echo esc_attr( $css_classes[ $status ] ); ?>">
							<?php echo esc_html( $labels[ $status ] ); ?>
						</div>
					</div>
					
					<?php if ( 'ai_written_edited' === $status ) : ?>
						<div class="zw-audit-content-section">
							<?php $diff = TTVGPTAuditHelper::generate_word_diff( $ai_content, $human_content ); ?>
							
							<h4>
								<span class="dashicons dashicons-arrow-down-alt"></span>
								<?php esc_html_e( 'Voor bewerking (AI origineel):', 'zw-ttvgpt' ); ?>
							</h4>
							<div class="zw-audit-content-box zw-audit-diff-before">
								<?php echo wp_kses_post( $diff['before'] ); ?>
							</div>
							
							<h4>
								<span class="dashicons dashicons-arrow-up-alt"></span>
								<?php esc_html_e( 'Na bewerking (Definitief):', 'zw-ttvgpt' ); ?>
							</h4>
							<div class="zw-audit-content-box zw-audit-diff-after">
								<?php echo wp_kses_post( $diff['after'] ); ?>
							</div>
						</div>
					<?php else : ?>
						<div class="zw-audit-content-section">
							<h4>
								<span class="dashicons dashicons-media-text"></span>
								<?php esc_html_e( 'Tekst TV Content:', 'zw-ttvgpt' ); ?>
							</h4>
							<div class="zw-audit-content-box">
								<?php echo esc_html( $human_content ); ?>
							</div>
						</div>
					<?php endif; ?>
				</article>
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