<?php
/**
 * Audit Page class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ZW_TTVGPT_Core\AuditHelper;
use ZW_TTVGPT_Core\AuditStatus;
use ZW_TTVGPT_Core\Constants;

/**
 * Renders the audit tools page and diff review UI.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class AuditPage {
	/**
	 * Retrieves validated filter parameters for audit page display.
	 *
	 * @since 1.0.0
	 *
	 * @return array Validated parameters including year, month, status_filter, and change_filter.
	 *
	 * @phpstan-return FilterParams
	 */
	private function get_filter_params(): array {
		// Read-only page - nonce verification not required for display filters.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$year  = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : null;
		$month = isset( $_GET['month'] ) ? absint( $_GET['month'] ) : null;

		// Fallback for native form submits when audit.js cannot split m=YYYYMM into year/month.
		if ( null === $year && null === $month && isset( $_GET['m'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_GET['m'] ) );
			if ( '' !== $raw && '0' !== $raw && preg_match( '/^\d{6}$/', $raw ) ) {
				$parsed_month = (int) substr( $raw, 4, 2 );
				if ( $parsed_month >= 1 && $parsed_month <= 12 ) {
					$year  = (int) substr( $raw, 0, 4 );
					$month = $parsed_month;
				}
			}
		}

		return array(
			'year'          => $year,
			'month'         => $month,
			'status_filter' => isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '',
			'change_filter' => isset( $_GET['change'] ) ? sanitize_text_field( $_GET['change'] ) : '',
			'paged'         => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/** The page emits a hidden modal per row, so large pages are expensive. */
	private const int POSTS_PER_PAGE = 50;

	/**
	 * Allowed tag/attribute map for diff panel wp_kses.
	 *
	 * @var array<string, array<string, bool>>
	 */
	private const array DIFF_ALLOWED_TAGS = array(
		'ins' => array(),
		'del' => array(),
	);

	/**
	 * Clamps out-of-range pages so a stale ?paged= still renders the table.
	 *
	 * @param array $items          Items to slice.
	 * @param int   $requested_page Requested page (1-indexed; clamped to a valid page).
	 * @param int   $per_page       Page size; values < 1 are treated as "no slicing".
	 *
	 * @phpstan-template T
	 * @phpstan-param array<int, T> $items
	 * @phpstan-return array{slice: array<int, T>, paged: int, total_pages: int, total: int}
	 */
	private static function paginate( array $items, int $requested_page, int $per_page ): array {
		$total       = count( $items );
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;
		$paged       = $total_pages > 0 ? min( max( 1, $requested_page ), $total_pages ) : 1;
		$offset      = ( $paged - 1 ) * max( 0, $per_page );
		$slice       = $per_page > 0 ? array_slice( $items, $offset, $per_page ) : array();

		return array(
			'slice'       => $slice,
			'paged'       => $paged,
			'total_pages' => $total_pages,
			'total'       => $total,
		);
	}

	/**
	 * Reports a sanitizer failure without leaking diff content.
	 *
	 * The hook is retained for the current non-string guard and any future
	 * sanitizer failure modes that need the same logging/subscriber path.
	 *
	 * @param string $reason        Stable reason code for this failure mode.
	 * @param string $error_message Optional lower-level error message.
	 */
	private static function report_diff_sanitizer_failure( string $reason, string $error_message = '' ): void {
		if ( function_exists( 'do_action' ) ) {
			do_action( 'zw_ttvgpt_diff_sanitizer_failed', $reason, $error_message );
		}
	}

	/**
	 * Runs wp_kses(), including pre_kses callbacks that can return arbitrary
	 * values. Callers must verify is_string() before echoing the result.
	 *
	 * @param string $diff_html Raw diff HTML.
	 */
	private static function kses_diff_html( string $diff_html ): mixed {
		return wp_kses( $diff_html, self::DIFF_ALLOWED_TAGS );
	}

	/**
	 * Sanitizes diff HTML for the audit modal.
	 *
	 * The diff renderer emits unadorned <ins>/<del> tags. Letting wp_kses()
	 * keep only those tags avoids a class allowlist and strips any attributes.
	 *
	 * @param string $diff_html Raw diff HTML emitted by AuditHelper::generate_word_diff.
	 */
	public static function sanitize_diff_panel( string $diff_html ): string {
		$kses_out = self::kses_diff_html( $diff_html );
		if ( ! is_string( $kses_out ) ) {
			self::report_diff_sanitizer_failure( 'wp_kses_non_string' );
			return wp_strip_all_tags( $diff_html );
		}

		return $kses_out;
	}

	/**
	 * Audit page callback for the Tools submenu.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		$params        = $this->get_filter_params();
		$year          = $params['year'];
		$month         = $params['month'];
		$status_filter = $params['status_filter'];
		$change_filter = $params['change_filter'];
		$paged         = $params['paged'];

		if ( ! $year || ! $month ) {
			$most_recent = AuditHelper::get_most_recent_month();
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

		$posts  = AuditHelper::get_posts( $year, $month );
		$counts = array(
			AuditStatus::FullyHumanWritten->value  => 0,
			AuditStatus::AiWrittenNotEdited->value => 0,
			AuditStatus::AiWrittenEdited->value    => 0,
		);

		// Prime WordPress' meta cache so categorize_post's get_post_meta calls hit cache.
		$post_ids = array_map( static fn( $post ) => $post->ID, $posts );
		AuditHelper::prime_meta_cache( $post_ids );

		$categorized_posts = array();
		foreach ( $posts as $post ) {
			$analysis = AuditHelper::categorize_post( $post );
			$status   = $analysis['status'];
			++$counts[ $status->value ];

			$status_match = empty( $status_filter ) || $status->value === $status_filter;

			$change_match = match ( true ) {
				empty( $change_filter )                       => true,
				AuditStatus::AiWrittenEdited !== $status      => false,
				default                                       => match ( $change_filter ) {
					'low'    => $analysis['change_percentage'] <= 20,
					'medium' => $analysis['change_percentage'] > 20 && $analysis['change_percentage'] <= 50,
					'high'   => $analysis['change_percentage'] > 50,
					default  => true,
				},
			};

			if ( $status_match && $change_match ) {
				$categorized_posts[] = array_merge( array( 'post' => $post ), $analysis );
			}
		}

		$available_months = AuditHelper::get_months();

		$pagination     = self::paginate( $categorized_posts, $paged, self::POSTS_PER_PAGE );
		$page_slice     = $pagination['slice'];
		$paged          = $pagination['paged'];
		$total_pages    = $pagination['total_pages'];
		$total_filtered = $pagination['total'];

		// Stylesheet is enqueued by AdminMenu on the audit page hook.
		add_thickbox();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Tekst TV GPT - Auditlog', 'zw-ttvgpt' ); ?></h1>
			<hr class="wp-header-end">

			<?php $this->render_status_links( $year, $month, $status_filter, $counts ); ?>

			<form id="posts-filter" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( Constants::AUDIT_PAGE_SLUG ); ?>">
				<?php
				$this->render_tablenav(
					$year,
					$month,
					$available_months,
					$status_filter,
					$change_filter,
					$total_filtered,
					$paged,
					$total_pages,
					'top'
				);
				$this->render_audit_table( $page_slice );
				$this->render_tablenav(
					$year,
					$month,
					$available_months,
					$status_filter,
					$change_filter,
					$total_filtered,
					$paged,
					$total_pages,
					'bottom'
				);
				?>
			</form>
		</div>
		<?php
	}




	/**
	 * Renders WordPress-style status filter links.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $year          Current year for filtering.
	 * @param int    $month         Current month for filtering.
	 * @param string $status_filter Current status filter value.
	 * @param array  $counts        Statistics counts for each category.
	 *
	 * @phpstan-param array<value-of<AuditStatus>, int> $counts
	 */
	private function render_status_links( int $year, int $month, string $status_filter, array $counts ): void {
		$total          = array_sum( $counts );
		$base_url       = admin_url( 'tools.php?page=' . Constants::AUDIT_PAGE_SLUG );
		$current_params = array(
			'year'  => $year,
			'month' => $month,
		);

		?>
		<h2 class="screen-reader-text"><?php esc_html_e( 'Auditlog filteren', 'zw-ttvgpt' ); ?></h2>
		<ul class="subsubsub">
			<li class="all">
				<?php $all_url = add_query_arg( $current_params, $base_url ); ?>
				<a href="<?php echo esc_url( $all_url ); ?>"
					<?php echo empty( $status_filter ) ? 'class="current" aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'Alle', 'zw-ttvgpt' ); ?> <span class="count">(<?php echo esc_html( (string) $total ); ?>)</span>
				</a>
			</li>
			<?php foreach ( AuditStatus::cases() as $status ) : ?>
				<?php
				$params   = array_merge( $current_params, array( 'status' => $status->value ) );
				$url      = add_query_arg( $params, $base_url );
				$selected = $status->value === $status_filter;
				?>
				<li class="<?php echo esc_attr( $status->get_css_class() ); ?>">
					<a href="<?php echo esc_url( $url ); ?>"
						<?php if ( $selected ) : ?>
						class="current" aria-current="page"
						<?php endif; ?>>
						<?php echo esc_html( $status->get_label() ); ?>
						<span class="count">(<?php echo esc_html( (string) ( $counts[ $status->value ] ?? 0 ) ); ?>)</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Renders WordPress-style table navigation.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $year             Current year for filtering.
	 * @param int    $month            Current month for filtering.
	 * @param array  $available_months All available months with data.
	 * @param string $status_filter    Current status filter value.
	 * @param string $change_filter    Current change percentage filter value.
	 * @param int    $total_filtered   Total items matching the current filters.
	 * @param int    $paged            Current page number (1-indexed).
	 * @param int    $total_pages      Total page count for the filtered set.
	 * @param string $which            Position of navigation ('top' or 'bottom').
	 *
	 * @phpstan-param array<int, MonthData> $available_months
	 */
	private function render_tablenav(
		int $year,
		int $month,
		array $available_months,
		string $status_filter,
		string $change_filter,
		int $total_filtered,
		int $paged,
		int $total_pages,
		string $which
	): void {
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
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %s: total item count */
						esc_html( _n( '%s item', '%s items', $total_filtered, 'zw-ttvgpt' ) ),
						esc_html( number_format_i18n( $total_filtered ) )
					);
					?>
				</span>
				<?php
				$this->render_pagination_links(
					array(
						'year'          => $year,
						'month'         => $month,
						'status_filter' => $status_filter,
						'change_filter' => $change_filter,
						'paged'         => $paged,
					),
					$total_pages
				);
				?>
			</div>
			<br class="clear">
		</div>
		<?php
	}

	/**
	 * Renders pagination links from a known arg allowlist so unrelated REQUEST_URI
	 * params (filter_action, _wp_http_referer, etc.) cannot leak into hrefs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $filters     Active filters; pass `paged` set to the clamped current page.
	 * @param int   $total_pages Total number of pages.
	 *
	 * @phpstan-param FilterParams $filters
	 */
	private function render_pagination_links( array $filters, int $total_pages ): void {
		if ( $total_pages < 2 ) {
			return;
		}

		$base_args = array(
			'page'   => Constants::AUDIT_PAGE_SLUG,
			'year'   => $filters['year'],
			'month'  => $filters['month'],
			'status' => '' !== $filters['status_filter'] ? $filters['status_filter'] : false,
			'change' => '' !== $filters['change_filter'] ? $filters['change_filter'] : false,
			'paged'  => '%#%',
		);

		$links = paginate_links(
			array(
				'base'      => add_query_arg( $base_args, admin_url( 'tools.php' ) ),
				'format'    => '',
				'prev_text' => __( '&laquo;', 'zw-ttvgpt' ),
				'next_text' => __( '&raquo;', 'zw-ttvgpt' ),
				'total'     => $total_pages,
				'current'   => $filters['paged'],
				'type'      => 'plain',
			)
		);

		if ( empty( $links ) ) {
			return;
		}

		echo '<span class="pagination-links">' . wp_kses_post( $links ) . '</span>';
	}

	/**
	 * Renders WordPress-style table with audit data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $categorized_posts Array of categorized posts with analysis data.
	 *
	 * @phpstan-param array<int, array<string, mixed>> $categorized_posts
	 */
	private function render_audit_table( array $categorized_posts ): void {
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
						$author_data   = get_userdata( $post->post_author );
						$edit_last     = get_post_meta( $post->ID, '_edit_last', true );
						$editor_data   = is_numeric( $edit_last ) ? get_userdata( (int) $edit_last ) : null;
						$post_url      = get_edit_post_link( $post->ID );
						?>
						<?php
						$row_classes = sprintf(
							'iedit author-self level-0 post-%d type-post status-%s %s',
							$post->ID,
							$post->post_status,
							$status->get_css_class()
						);
						?>
						<tr id="post-<?php echo esc_attr( (string) $post->ID ); ?>"
							class="<?php echo esc_attr( $row_classes ); ?>">
							<td class="type column-type" data-colname="<?php esc_attr_e( 'Type', 'zw-ttvgpt' ); ?>">
								<span class="zw-audit-type-label <?php echo esc_attr( $status->get_css_class() ); ?>">
									<?php echo esc_html( $status->get_label() ); ?>
								</span>
							</td>
							<td class="title column-title has-row-actions column-primary page-title"
							data-colname="<?php esc_attr_e( 'Titel', 'zw-ttvgpt' ); ?>">
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
										<a href="<?php echo esc_url( (string) $post_url ); ?>"
											aria-label="<?php echo esc_attr( $edit_aria_label ); ?>">
											<?php esc_html_e( 'Bewerken', 'zw-ttvgpt' ); ?>
										</a> |
									</span>
									<span class="view">
										<?php
										/* translators: %s is the post title. */
										$view_aria_label = sprintf( __( '"%s" bekijken', 'zw-ttvgpt' ), get_the_title( $post->ID ) );
										?>
										<a href="<?php echo esc_url( (string) get_permalink( $post->ID ) ); ?>"
											rel="bookmark"
											aria-label="<?php echo esc_attr( $view_aria_label ); ?>"
											target="_blank">
											<?php esc_html_e( 'Bekijken', 'zw-ttvgpt' ); ?>
										</a>
									</span>
									<?php if ( AuditStatus::AiWrittenEdited === $status ) : ?>
										<?php
										$diff_url = '#TB_inline?width=800&height=600&inlineId=zw-diff-modal-' . $post->ID;
										?>
										| <span class="view-diff">
											<a href="<?php echo esc_attr( $diff_url ); ?>"
												class="thickbox"
												aria-label="<?php esc_attr_e( 'Toon verschillen tussen AI en bewerkte versie', 'zw-ttvgpt' ); ?>">
												<?php esc_html_e( 'Verschillen', 'zw-ttvgpt' ); ?>
											</a>
										</span>
									<?php endif; ?>
								</div>
								<button type="button" class="toggle-row">
									<span class="screen-reader-text">
										<?php esc_html_e( 'Meer details weergeven', 'zw-ttvgpt' ); ?>
									</span>
								</button>
							</td>
							<td class="author column-author" data-colname="<?php esc_attr_e( 'Auteur', 'zw-ttvgpt' ); ?>">
								<?php echo esc_html( $author_data ? $author_data->display_name : __( 'Onbekend', 'zw-ttvgpt' ) ); ?>
							</td>
							<td class="editor column-editor" data-colname="<?php esc_attr_e( 'Eindredacteur', 'zw-ttvgpt' ); ?>">
								<?php if ( $editor_data && $editor_data->ID !== $post->post_author ) : ?>
									<?php echo esc_html( $editor_data->display_name ); ?>
								<?php else : ?>
									<span aria-hidden="true">—</span>
									<span class="screen-reader-text"><?php esc_html_e( 'Geen eindredacteur', 'zw-ttvgpt' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="change column-change"
								data-colname="<?php esc_attr_e( 'Wijzigingen %', 'zw-ttvgpt' ); ?>">
								<?php if ( AuditStatus::AiWrittenEdited === $status && isset( $item['change_percentage'] ) ) : ?>
									<?php
									$pct       = $item['change_percentage'];
									$pct_class = $pct > 50 ? 'high-change' : ( $pct > 20 ? 'medium-change' : 'low-change' );
									?>
									<span class="change-percentage <?php echo esc_attr( $pct_class ); ?>">
										<?php echo esc_html( $pct . '%' ); ?>
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
		
		<?php foreach ( $categorized_posts as $item ) : ?>
			<?php if ( AuditStatus::AiWrittenEdited === $item['status'] ) : ?>
				<?php
				$post = $item['post'];
				$diff = AuditHelper::generate_word_diff( $item['ai_content'], $item['human_content'] );
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
									<div class="zw-diff-panel" style="max-height: 400px; overflow-y: auto; padding: 10px; font-size: 13px; line-height: 1.6;">
										<?php
											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is sanitized by sanitize_diff_panel().
											echo self::sanitize_diff_panel( $diff['before'] );
										?>
									</div>
								</div>
							</div>
							
							<div class="postbox">
								<div class="postbox-header">
									<h3 class="hndle"><?php esc_html_e( 'Bewerkte versie', 'zw-ttvgpt' ); ?></h3>
								</div>
								<div class="inside">
									<div class="zw-diff-panel" style="max-height: 400px; overflow-y: auto; padding: 10px; font-size: 13px; line-height: 1.6;">
										<?php
											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is sanitized by sanitize_diff_panel().
											echo self::sanitize_diff_panel( $diff['after'] );
										?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
		<?php
	}
}
