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
	 * Get validated filter parameters from GET request
	 *
	 * @return array Validated parameters
	 */
	private function get_filter_params(): array {
		// Read-only page - nonce verification not required for display filters
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		return array(
			'year'          => isset( $_GET['year'] ) ? absint( $_GET['year'] ) : null,
			'month'         => isset( $_GET['month'] ) ? absint( $_GET['month'] ) : null,
			'status_filter' => isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '',
			'change_filter' => isset( $_GET['change'] ) ? sanitize_text_field( $_GET['change'] ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Render audit analysis page
	 *
	 * @return void
	 */
	public function render(): void {
		$params        = $this->get_filter_params();
		$year          = $params['year'];
		$month         = $params['month'];
		$status_filter = $params['status_filter'];
		$change_filter = $params['change_filter'];

		if ( ! $year || ! $month ) {
			$most_recent = TTVGPTAuditHelper::get_most_recent_month();
			if ( $most_recent ) {
				$year  = $most_recent['year'];
				$month = $most_recent['month'];
			} else {
				?>
				<div class="wrap">
					<h1><?php esc_html_e( 'Tekst TV GPT - Auditlog', 'zw-ttvgpt' ); ?></h1>
					<div class="notice notice-info">
						<p><?php esc_html_e( 'Geen artikelen gevonden voor auditanalyse.', 'zw-ttvgpt' ); ?></p>
					</div>
				</div>
				<?php
				return;
			}
		}

		// Clean implementation - no benchmarking needed

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
			$status_match = empty( $status_filter ) || $analysis['status'] === $status_filter;

			// Apply change filter if set
			$change_match = true;
			if ( ! empty( $change_filter ) && 'ai_written_edited' === $analysis['status'] ) {
				$change_percentage = $analysis['change_percentage'];
				switch ( $change_filter ) {
					case 'low':
						$change_match = $change_percentage <= 20;
						break;
					case 'medium':
						$change_match = $change_percentage > 20 && $change_percentage <= 50;
						break;
					case 'high':
						$change_match = $change_percentage > 50;
						break;
				}
			} elseif ( ! empty( $change_filter ) ) {
				// If change filter is set but post is not AI+, exclude it
				$change_match = false;
			}

			if ( $status_match && $change_match ) {
				$categorized_posts[] = array_merge( array( 'post' => $post ), $analysis );
			}
		}

		$available_months = TTVGPTAuditHelper::get_months();

		// Load audit CSS for improved card spacing
		$version = ZW_TTVGPT_VERSION . ( TTVGPTSettingsManager::is_debug_mode() ? '.' . time() : '' );
		wp_enqueue_style( 'zw-ttvgpt-audit', ZW_TTVGPT_URL . 'assets/audit.css', array(), $version );
		wp_print_styles( array( 'zw-ttvgpt-audit' ) );

		// Enqueue WordPress ThickBox for modal functionality
		add_thickbox();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Tekst TV GPT - Auditlog', 'zw-ttvgpt' ); ?></h1>
			<hr class="wp-header-end">
			
			<?php $this->render_status_links( $year, $month, $status_filter, $counts ); ?>
			
			<form id="posts-filter" method="get">
				<input type="hidden" name="page" value="zw-ttvgpt-audit">
				<?php $this->render_tablenav( $year, $month, $available_months, $status_filter, $change_filter, $counts, 'top' ); ?>
				<?php $this->render_audit_table( $categorized_posts, $meta_cache ); ?>
				<?php $this->render_tablenav( $year, $month, $available_months, $status_filter, $change_filter, $counts, 'bottom' ); ?>
			</form>
		</div>
		<?php
	}




	/**
	 * Render WordPress-style status filter links
	 *
	 * @param int    $year Current year
	 * @param int    $month Current month
	 * @param string $status_filter Current status filter
	 * @param array  $counts Statistics counts
	 * @return void
	 */
	private function render_status_links( int $year, int $month, string $status_filter, array $counts ): void {
		$total          = array_sum( $counts );
		$base_url       = admin_url( 'tools.php?page=zw-ttvgpt-audit' );
		$current_params = array(
			'year'  => $year,
			'month' => $month,
		);
		?>
		<h2 class="screen-reader-text"><?php esc_html_e( 'Auditlog filteren', 'zw-ttvgpt' ); ?></h2>
		<ul class="subsubsub">
			<li class="all">
				<a href="<?php echo esc_url( add_query_arg( $current_params, $base_url ) ); ?>" <?php echo empty( $status_filter ) ? 'class="current" aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'Alle', 'zw-ttvgpt' ); ?> <span class="count">(<?php echo esc_html( (string) $total ); ?>)</span>
				</a>
			</li>
			<li class="human">
				<a href="<?php echo esc_url( add_query_arg( array_merge( $current_params, array( 'status' => 'fully_human_written' ) ), $base_url ) ); ?>" <?php echo 'fully_human_written' === $status_filter ? 'class="current" aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'Handgeschreven', 'zw-ttvgpt' ); ?> <span class="count">(<?php echo esc_html( $counts['fully_human_written'] ); ?>)</span>
				</a>
			</li>
			<li class="ai-unedited">
				<a href="<?php echo esc_url( add_query_arg( array_merge( $current_params, array( 'status' => 'ai_written_not_edited' ) ), $base_url ) ); ?>" <?php echo 'ai_written_not_edited' === $status_filter ? 'class="current" aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'AI-gegenereerd', 'zw-ttvgpt' ); ?> <span class="count">(<?php echo esc_html( $counts['ai_written_not_edited'] ); ?>)</span>
				</a>
			</li>
			<li class="ai-edited">
				<a href="<?php echo esc_url( add_query_arg( array_merge( $current_params, array( 'status' => 'ai_written_edited' ) ), $base_url ) ); ?>" <?php echo 'ai_written_edited' === $status_filter ? 'class="current" aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'AI-bewerkt', 'zw-ttvgpt' ); ?> <span class="count">(<?php echo esc_html( $counts['ai_written_edited'] ); ?>)</span>
				</a>
			</li>
		</ul>
		<?php
	}

	/**
	 * Render WordPress-style table navigation
	 *
	 * @param int    $year Current year
	 * @param int    $month Current month
	 * @param array  $available_months All available months
	 * @param string $status_filter Current status filter
	 * @param string $change_filter Current change filter
	 * @param array  $counts Statistics counts
	 * @param string $which Top or bottom
	 * @return void
	 */
	private function render_tablenav( int $year, int $month, array $available_months, string $status_filter, string $change_filter, array $counts, string $which ): void {
		$total = array_sum( $counts );
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">
			<?php if ( 'top' === $which ) : ?>
				<div class="alignleft actions">
					<label for="filter-by-date" class="screen-reader-text"><?php esc_html_e( 'Op datum filteren', 'zw-ttvgpt' ); ?></label>
					<select name="m" id="filter-by-date">
						<option value="0"><?php esc_html_e( 'Alle datums', 'zw-ttvgpt' ); ?></option>
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

					<label for="filter-by-status" class="screen-reader-text"><?php esc_html_e( 'Op type filteren', 'zw-ttvgpt' ); ?></label>
					<select name="status" id="filter-by-status">
						<option value=""><?php esc_html_e( 'Alle types', 'zw-ttvgpt' ); ?></option>
						<option value="fully_human_written" <?php selected( $status_filter, 'fully_human_written' ); ?>>
							<?php esc_html_e( 'Handgeschreven', 'zw-ttvgpt' ); ?>
						</option>
						<option value="ai_written_not_edited" <?php selected( $status_filter, 'ai_written_not_edited' ); ?>>
							<?php esc_html_e( 'AI-gegenereerd', 'zw-ttvgpt' ); ?>
						</option>
						<option value="ai_written_edited" <?php selected( $status_filter, 'ai_written_edited' ); ?>>
							<?php esc_html_e( 'AI-bewerkt', 'zw-ttvgpt' ); ?>
						</option>
					</select>

					<label for="filter-by-change" class="screen-reader-text"><?php esc_html_e( 'Op wijzigingspercentage filteren', 'zw-ttvgpt' ); ?></label>
					<select name="change" id="filter-by-change">
						<option value=""><?php esc_html_e( 'Alle wijzigingen', 'zw-ttvgpt' ); ?></option>
						<option value="low" <?php selected( $change_filter, 'low' ); ?>>
							<?php esc_html_e( 'Laag (≤20%)', 'zw-ttvgpt' ); ?>
						</option>
						<option value="medium" <?php selected( $change_filter, 'medium' ); ?>>
							<?php esc_html_e( 'Gemiddeld (21-50%)', 'zw-ttvgpt' ); ?>
						</option>
						<option value="high" <?php selected( $change_filter, 'high' ); ?>>
							<?php esc_html_e( 'Hoog (>50%)', 'zw-ttvgpt' ); ?>
						</option>
					</select>

					<input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php esc_attr_e( 'Filter', 'zw-ttvgpt' ); ?>">
				</div>
			<?php endif; ?>

			<h2 class="screen-reader-text"><?php esc_html_e( 'Auditlijst navigatie', 'zw-ttvgpt' ); ?></h2>
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo esc_html( (string) $total ); ?> items</span>
			</div>
			<br class="clear">
		</div>
		<?php
	}

	/**
	 * Render WordPress-style table
	 *
	 * @param array $categorized_posts Array of categorized posts
	 * @param array $meta_cache Meta data cache to avoid N+1 queries
	 * @return void
	 */
	private function render_audit_table( array $categorized_posts, array $meta_cache ): void {
		$type_labels = array(
			'fully_human_written'   => __( 'Handgeschreven', 'zw-ttvgpt' ),
			'ai_written_not_edited' => __( 'AI-gegenereerd', 'zw-ttvgpt' ),
			'ai_written_edited'     => __( 'AI-bewerkt', 'zw-ttvgpt' ),
		);

		$css_classes = array(
			'fully_human_written'   => 'human',
			'ai_written_not_edited' => 'ai-unedited',
			'ai_written_edited'     => 'ai-edited',
		);
		?>
		<h2 class="screen-reader-text"><?php esc_html_e( 'Auditlog', 'zw-ttvgpt' ); ?></h2>
		<table class="wp-list-table widefat fixed striped table-view-list posts zw-audit-table">
			<thead>
				<tr>
					<th scope="col" id="type" class="manage-column column-type" style="width: 80px;"><?php esc_html_e( 'Type', 'zw-ttvgpt' ); ?></th>
					<th scope="col" id="title" class="manage-column column-title column-primary"><?php esc_html_e( 'Titel', 'zw-ttvgpt' ); ?></th>
					<th scope="col" id="author" class="manage-column column-author"><?php esc_html_e( 'Auteur', 'zw-ttvgpt' ); ?></th>
					<th scope="col" id="editor" class="manage-column column-editor"><?php esc_html_e( 'Eindredacteur', 'zw-ttvgpt' ); ?></th>
					<th scope="col" id="change" class="manage-column column-change"><?php esc_html_e( 'Wijzigingen %', 'zw-ttvgpt' ); ?></th>
					<th scope="col" id="date" class="manage-column column-date"><?php esc_html_e( 'Datum', 'zw-ttvgpt' ); ?></th>
				</tr>
			</thead>
			<tbody id="the-list">
				<?php if ( empty( $categorized_posts ) ) : ?>
					<tr class="no-items">
						<td class="colspanchange" colspan="6">
							<?php esc_html_e( 'Geen artikelen gevonden voor de geselecteerde filters.', 'zw-ttvgpt' ); ?>
						</td>
					</tr>
				<?php else : ?>
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
						<tr id="post-<?php echo esc_attr( $post->ID ); ?>" class="iedit author-self level-0 post-<?php echo esc_attr( $post->ID ); ?> type-post status-<?php echo esc_attr( $post->post_status ); ?> <?php echo esc_attr( $css_classes[ $status ] ); ?>">
							<td class="type column-type" data-colname="<?php esc_attr_e( 'Type', 'zw-ttvgpt' ); ?>">
								<span class="zw-audit-type-label <?php echo esc_attr( $css_classes[ $status ] ); ?>">
									<?php echo esc_html( $type_labels[ $status ] ); ?>
								</span>
							</td>
							<td class="title column-title has-row-actions column-primary page-title" data-colname="<?php esc_attr_e( 'Titel', 'zw-ttvgpt' ); ?>">
								<strong>
									<?php if ( $post_url ) : ?>
										<?php
										/* translators: %s is the post title. */
										$edit_label = sprintf( __( '"%s" (bewerken)', 'zw-ttvgpt' ), get_the_title( $post->ID ) );
										?>
									<a class="row-title" href="<?php echo esc_url( $post_url ); ?>" aria-label="<?php echo esc_attr( $edit_label ); ?>">
											<?php echo esc_html( get_the_title( $post->ID ) ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( get_the_title( $post->ID ) ); ?>
									<?php endif; ?>
								</strong>
								<div class="row-actions">
									<span class="edit">
										<?php
										/* translators: %s is the post title. */
										$edit_aria_label = sprintf( __( 'Bewerk "%s"', 'zw-ttvgpt' ), get_the_title( $post->ID ) );
										?>
										<a href="<?php echo esc_url( (string) $post_url ); ?>" aria-label="<?php echo esc_attr( $edit_aria_label ); ?>">
											<?php esc_html_e( 'Bewerken', 'zw-ttvgpt' ); ?>
										</a> |
									</span>
									<span class="view">
										<?php
										/* translators: %s is the post title. */
										$view_aria_label = sprintf( __( '"%s" bekijken', 'zw-ttvgpt' ), get_the_title( $post->ID ) );
										?>
										<a href="<?php echo esc_url( (string) get_permalink( $post->ID ) ); ?>" rel="bookmark" aria-label="<?php echo esc_attr( $view_aria_label ); ?>" target="_blank">
											<?php esc_html_e( 'Bekijken', 'zw-ttvgpt' ); ?>
										</a>
									</span>
									<?php if ( 'ai_written_edited' === $status ) : ?>
										| <span class="view-diff">
											<a href="#TB_inline?width=800&height=600&inlineId=zw-diff-modal-<?php echo esc_attr( $post->ID ); ?>" class="thickbox"
												aria-label="<?php esc_attr_e( 'Toon verschillen tussen AI en bewerkte versie', 'zw-ttvgpt' ); ?>">
												<?php esc_html_e( 'Verschillen', 'zw-ttvgpt' ); ?>
											</a>
										</span>
									<?php endif; ?>
								</div>
								<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Meer details weergeven', 'zw-ttvgpt' ); ?></span></button>
							</td>
							<td class="author column-author" data-colname="<?php esc_attr_e( 'Auteur', 'zw-ttvgpt' ); ?>">
								<?php echo esc_html( $author ? $author->display_name : __( 'Onbekend', 'zw-ttvgpt' ) ); ?>
							</td>
							<td class="editor column-editor" data-colname="<?php esc_attr_e( 'Eindredacteur', 'zw-ttvgpt' ); ?>">
								<?php if ( $last_editor && $last_editor->ID !== $post->post_author ) : ?>
									<?php echo esc_html( $last_editor->display_name ); ?>
								<?php else : ?>
									<span aria-hidden="true">—</span>
									<span class="screen-reader-text"><?php esc_html_e( 'Geen eindredacteur', 'zw-ttvgpt' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="change column-change" data-colname="<?php esc_attr_e( 'Wijzigingen %', 'zw-ttvgpt' ); ?>">
								<?php if ( 'ai_written_edited' === $status && isset( $item['change_percentage'] ) ) : ?>
									<span class="change-percentage <?php echo esc_attr( $item['change_percentage'] > 50 ? 'high-change' : ( $item['change_percentage'] > 20 ? 'medium-change' : 'low-change' ) ); ?>">
										<?php echo esc_html( $item['change_percentage'] . '%' ); ?>
									</span>
								<?php else : ?>
									<span aria-hidden="true">—</span>
									<span class="screen-reader-text"><?php esc_html_e( 'Niet van toepassing', 'zw-ttvgpt' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="date column-date" data-colname="<?php esc_attr_e( 'Datum', 'zw-ttvgpt' ); ?>">
								<?php
								$post_status_label = 'publish' === $post->post_status ? __( 'Gepubliceerd', 'zw-ttvgpt' ) : ucfirst( $post->post_status );
								echo esc_html( $post_status_label );
								?>
								<br>
								<?php echo esc_html( (string) get_the_date( 'j F Y \o\m H:i', $post->ID ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="col" class="manage-column column-type"><?php esc_html_e( 'Type', 'zw-ttvgpt' ); ?></th>
					<th scope="col" class="manage-column column-title column-primary"><?php esc_html_e( 'Titel', 'zw-ttvgpt' ); ?></th>
					<th scope="col" class="manage-column column-author"><?php esc_html_e( 'Auteur', 'zw-ttvgpt' ); ?></th>
					<th scope="col" class="manage-column column-editor"><?php esc_html_e( 'Eindredacteur', 'zw-ttvgpt' ); ?></th>
					<th scope="col" class="manage-column column-change"><?php esc_html_e( 'Wijzigingen %', 'zw-ttvgpt' ); ?></th>
					<th scope="col" class="manage-column column-date"><?php esc_html_e( 'Datum', 'zw-ttvgpt' ); ?></th>
				</tr>
			</tfoot>
		</table>
		
		<!-- ThickBox Inline Modals for Diff Display -->
		<?php foreach ( $categorized_posts as $item ) : ?>
			<?php if ( 'ai_written_edited' === $item['status'] ) : ?>
				<?php
				$post = $item['post'];
				$diff = TTVGPTAuditHelper::generate_word_diff( $item['ai_content'], $item['human_content'] );
				?>
				<div id="zw-diff-modal-<?php echo esc_attr( $post->ID ); ?>" style="display: none;">
					<div class="wrap">
						<?php
						/* translators: %s is the post title. */
						$modal_title = sprintf( __( 'Verschillen voor: %s', 'zw-ttvgpt' ), get_the_title( $post->ID ) );
						?>
						<h2><?php echo esc_html( $modal_title ); ?></h2>
						
						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
							<div class="postbox">
								<div class="postbox-header">
									<h3 class="hndle"><?php esc_html_e( 'AI-versie', 'zw-ttvgpt' ); ?></h3>
								</div>
								<div class="inside">
									<div style="max-height: 400px; overflow-y: auto; padding: 10px; font-size: 13px; line-height: 1.6;">
										<?php echo wp_kses_post( $diff['before'] ); ?>
									</div>
								</div>
							</div>
							
							<div class="postbox">
								<div class="postbox-header">
									<h3 class="hndle"><?php esc_html_e( 'Bewerkte versie', 'zw-ttvgpt' ); ?></h3>
								</div>
								<div class="inside">
									<div style="max-height: 400px; overflow-y: auto; padding: 10px; font-size: 13px; line-height: 1.6;">
										<?php echo wp_kses_post( $diff['after'] ); ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
		
		<script>
		jQuery(function($) {
			function applyFilters() {
				var date = $('#filter-by-date').val();
				var status = $('#filter-by-status').val();
				var change = $('#filter-by-change').val();
				var url = '<?php echo esc_js( admin_url( 'tools.php?page=zw-ttvgpt-audit' ) ); ?>';
				var params = [];
				
				if (date && date !== '0') {
					params.push('year=' + date.substring(0, 4));
					params.push('month=' + parseInt(date.substring(4, 6), 10));
				}
				
				if (status) params.push('status=' + status);
				if (change) params.push('change=' + change);
				
				if (params.length) url += '&' + params.join('&');
				window.location.href = url;
			}
			
			$('#filter-by-date, #filter-by-status, #filter-by-change').on('change', applyFilters);
			$('#post-query-submit').on('click', function(e) {
				e.preventDefault();
				applyFilters();
			});
		});
		</script>
		<?php
	}
}