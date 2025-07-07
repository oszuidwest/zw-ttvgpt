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

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tekst TV GPT Audit', 'zw-ttvgpt' ); ?></h1>
			
			<?php $this->render_navigation( $year, $month, $available_months, $status_filter ); ?>
			
			<div class="postbox-container" style="display: flex; gap: 20px; margin: 20px 0;">
				<?php
				$labels      = array(
					'fully_human_written'   => __( 'Volledig handmatig', 'zw-ttvgpt' ),
					'ai_written_not_edited' => __( 'AI, niet bewerkt', 'zw-ttvgpt' ),
					'ai_written_edited'     => __( 'AI, bewerkt', 'zw-ttvgpt' ),
				);
				$css_classes = array(
					'fully_human_written'   => 'update-message',
					'ai_written_not_edited' => 'error',
					'ai_written_edited'     => 'notice-warning',
				);
				foreach ( $counts as $status => $count ) :
					?>
					<div class="postbox" style="flex: 1; min-width: 200px;">
						<div class="inside" style="text-align: center; padding: 20px;">
							<div class="notice <?php echo esc_attr( $css_classes[ $status ] ); ?> inline" style="margin: 0; padding: 10px;">
								<div style="font-size: 2em; font-weight: bold;">
									<?php echo esc_html( (string) $count ); ?>
								</div>
								<div style="margin-top: 8px;">
									<?php echo esc_html( $labels[ $status ] ); ?>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php
			foreach ( $categorized_posts as $item ) :
				$post          = $item['post'];
				$status        = $item['status'];
				$ai_content    = $item['ai_content'];
				$human_content = $item['human_content'];

				$author      = get_userdata( $post->post_author );
				$edit_last   = get_post_meta( $post->ID, '_edit_last', true );
				$last_editor = is_numeric( $edit_last ) ? get_userdata( (int) $edit_last ) : false;
				?>
				<div class="postbox" style="margin: 20px 0;">
					<div class="inside">
						<h3 class="hndle" style="margin: 0 0 15px 0; padding: 0; font-size: 1.2em;">
							<?php echo esc_html( (string) get_the_title( $post->ID ) ); ?>
						</h3>
						<p class="description" style="margin-bottom: 15px;">
							<?php
							printf(
								/* translators: 1: Date, 2: Author, 3: Last editor */
								esc_html__( 'Gepubliceerd: %1$s | Auteur: %2$s | Laatste bewerking: %3$s', 'zw-ttvgpt' ),
								esc_html( (string) get_the_date( 'Y-m-d', $post->ID ) ),
								esc_html( $author ? $author->display_name : 'Onbekend' ),
								esc_html( $last_editor ? $last_editor->display_name : 'Onbekend' )
							);
							?>
						</p>
						<span class="<?php echo esc_attr( $css_classes[ $status ] ); ?> notice inline" style="padding: 5px 10px; font-size: 0.9em;">
							<?php echo esc_html( $labels[ $status ] ); ?>
						</span>

					<?php if ( 'ai_written_edited' === $status ) : ?>
						<div style="margin-top: 20px;">
							<?php $diff = TTVGPTAuditHelper::generate_word_diff( $ai_content, $human_content ); ?>
							<h4><?php esc_html_e( 'Voor bewerking:', 'zw-ttvgpt' ); ?></h4>
							<div class="code-editor" style="padding: 12px; background: #f6f7f7; border: 1px solid #dcdcde; margin-bottom: 15px;">
								<?php echo wp_kses_post( $diff['before'] ); ?>
							</div>
							<h4><?php esc_html_e( 'Na bewerking:', 'zw-ttvgpt' ); ?></h4>
							<div class="code-editor" style="padding: 12px; background: #f6f7f7; border: 1px solid #dcdcde;">
								<?php echo wp_kses_post( $diff['after'] ); ?>
							</div>
						</div>
					<?php else : ?>
						<div style="margin-top: 20px;">
							<h4><?php esc_html_e( 'Content:', 'zw-ttvgpt' ); ?></h4>
							<div class="code-editor" style="padding: 12px; background: #f6f7f7; border: 1px solid #dcdcde;">
								<?php echo esc_html( $human_content ); ?>
							</div>
						</div>
					<?php endif; ?>
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