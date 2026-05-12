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

/**
 * Audit Page class.
 *
 * Handles audit page rendering and analysis.
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

		// Fallback for native form submit: the date <select> is named "m" and
		// emits YYYYMM (e.g. "202604"). The audit.js module splits this into
		// year+month before navigating, but if JS is unavailable (CSP, encode
		// failure, etc.) the form posts ?m=YYYYMM directly. Parse it server-side.
		if ( null === $year && null === $month && isset( $_GET['m'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_GET['m'] ) );
			if ( '' !== $raw && '0' !== $raw && preg_match( '/^\d{6}$/', $raw ) ) {
				$year  = (int) substr( $raw, 0, 4 );
				$month = (int) substr( $raw, 4, 2 );
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

	/**
	 * Posts per page on the audit overview. Sized to show a meaningful slice
	 * of a typical month without blowing up render time on large months
	 * (the page renders a hidden modal per row, so the cost is non-trivial).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const int POSTS_PER_PAGE = 50;

	/**
	 * Allowed HTML for rendered diff markup. Currently emits via
	 * AuditHelper::generate_word_diff, which only produces span.zw-diff-added
	 * and span.zw-diff-removed — widen this allowlist only if that helper
	 * starts emitting new tags, and update DIFF_ALLOWED_CLASSES in lockstep.
	 *
	 * @since 1.0.0
	 * @var array<string, array<string, bool>>
	 */
	private const array DIFF_ALLOWED_TAGS = array(
		'span' => array( 'class' => true ),
	);

	/**
	 * Slices a list into a single page and returns clamped pagination state.
	 *
	 * Requested pages outside [1, total_pages] are clamped to the closest
	 * valid page so an out-of-range ?paged= still renders the table instead
	 * of a blank slice. Empty input returns paged=1, total_pages=0.
	 *
	 * @since 1.0.0
	 *
	 * @param array $items          Items to paginate (any indexed list).
	 * @param int   $requested_page Page number the caller asked for.
	 * @param int   $per_page       Page size; values < 1 are treated as "no slicing wanted".
	 * @return array{slice: array, paged: int, total_pages: int, total: int} Pagination outcome:
	 *                  the slice for the requested page, the clamped page number, total page
	 *                  count, and total item count before slicing.
	 *
	 * @phpstan-template T
	 * @phpstan-param array<int, T> $items
	 * @phpstan-return array{slice: array<int, T>, paged: int, total_pages: int, total: int}
	 */
	public static function paginate( array $items, int $requested_page, int $per_page ): array {
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
	 * Class values that survive the diff sanitizer. Anything else is stripped
	 * to inert text. Mirrors the two class names AuditHelper::generate_word_diff
	 * emits via str_replace, so widening either end requires touching both.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	private const array DIFF_ALLOWED_CLASSES = array( 'zw-diff-added', 'zw-diff-removed' );

	/**
	 * Sanitizes diff markup for display in the audit modal.
	 *
	 * Note: `wp_kses` with `['span' => ['class' => true]]` strips disallowed
	 * tags but does NOT validate the class attribute value — any class string
	 * passes. That breaks the "safe to echo" contract on raw input like
	 * `<span class="evil">x</span>`. We therefore run a second pass that
	 * drops every <span> whose class is not exactly zw-diff-added or
	 * zw-diff-removed, keeping its inner content as text. The pattern
	 * tolerates the variations wp_kses leaves intact: single/double quotes,
	 * uppercase tag/attribute names, whitespace padding inside the value.
	 *
	 * The regex is iterated because a single pass uses non-greedy `(.*?)`
	 * and binds the outer disallowed span to the FIRST `</span>` it sees —
	 * for nested disallowed spans like `<span class="a"><span class="b">x
	 * </span></span>` that leaves the inner span as a top-level survivor.
	 * Re-running peels one level per pass.
	 *
	 * Iterations are bounded by the count of `<span` markers in the
	 * kses-normalized input: each pass removes at least one disallowed
	 * span (otherwise the stability check exits), and the substitution is
	 * `$2` — content between the matched open/close pair — so new `<span`
	 * markers can never be introduced. If the bound is somehow exceeded we
	 * MUST NOT return the partially-stripped string (it can still contain
	 * a live disallowed span); fail closed by stripping all remaining tags.
	 *
	 * Centralizing here keeps the modal render path and the regression
	 * tests in AuditPageDiffAllowlistTest exercising the same sanitizer —
	 * removing either pass fails those tests, not just the constant lock-in.
	 *
	 * @since 1.0.0
	 *
	 * @param string $diff_html Raw diff HTML emitted by AuditHelper::generate_word_diff.
	 * @return string HTML safe to echo into the audit modal panes.
	 */
	public static function sanitize_diff_panel( string $diff_html ): string {
		$kses_out = wp_kses( $diff_html, self::DIFF_ALLOWED_TAGS );

		$class_pattern = implode( '|', array_map( 'preg_quote', self::DIFF_ALLOWED_CLASSES ) );
		$pattern       = '#<(?i:span)(?![^>]*\b(?i:class)=(["\'])\s*(?:' . $class_pattern . ')\s*\1)[^>]*>(.*?)</(?i:span)>#s';

		// Tight upper bound: one iteration per span in the input, plus one
		// final stability check to confirm no further changes. Counted with
		// the same case-insensitive match the regex above uses so the bound
		// never undershoots on non-normalized input.
		$max_iterations = (int) preg_match_all( '/<span\b/i', $kses_out ) + 1;

		$current = $kses_out;
		for ( $i = 0; $i < $max_iterations; $i++ ) {
			$next = preg_replace( $pattern, '$2', $current );
			if ( ! is_string( $next ) || $next === $current ) {
				return $current;
			}
			$current = $next;
		}

		return wp_strip_all_tags( $current );
	}

	/**
	 * Renders the audit analysis page.
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

		// Clean implementation - no benchmarking needed.

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

			// Apply status filter if set.
			$status_match = empty( $status_filter ) || $status->value === $status_filter;

			// Apply change filter if set (using match expression).
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

		// Slice filtered results for the requested page.
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
				<input type="hidden" name="page" value="zw-ttvgpt-audit">
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
		$base_url       = admin_url( 'tools.php?page=zw-ttvgpt-audit' );
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
				<?php $this->render_pagination_links( $paged, $total_pages, $year, $month, $status_filter, $change_filter ); ?>
			</div>
			<br class="clear">
		</div>
		<?php
	}

	/**
	 * Renders WordPress-style pagination links preserving current filters.
	 *
	 * Builds the base URL from a known allowlist of args (the audit page slug
	 * plus active filters) so unrelated query parameters present in
	 * REQUEST_URI — e.g. _wp_http_referer, settings-updated, filter_action —
	 * do not leak into pagination hrefs.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $paged         Current page (1-indexed).
	 * @param int    $total_pages   Total number of pages.
	 * @param int    $year          Active year filter.
	 * @param int    $month         Active month filter.
	 * @param string $status_filter Active status filter (empty when none).
	 * @param string $change_filter Active change-percentage filter (empty when none).
	 */
	private function render_pagination_links(
		int $paged,
		int $total_pages,
		int $year,
		int $month,
		string $status_filter,
		string $change_filter
	): void {
		if ( $total_pages < 2 ) {
			return;
		}

		$base_args = array(
			'page'   => 'zw-ttvgpt-audit',
			'year'   => $year,
			'month'  => $month,
			'status' => '' !== $status_filter ? $status_filter : false,
			'change' => '' !== $change_filter ? $change_filter : false,
			'paged'  => '%#%',
		);

		$links = paginate_links(
			array(
				'base'      => add_query_arg( $base_args, admin_url( 'tools.php' ) ),
				'format'    => '',
				'prev_text' => __( '&laquo;', 'zw-ttvgpt' ),
				'next_text' => __( '&raquo;', 'zw-ttvgpt' ),
				'total'     => $total_pages,
				'current'   => $paged,
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
		
		<!-- ThickBox Inline Modals for Diff Display -->
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
									<div style="max-height: 400px; overflow-y: auto; padding: 10px; font-size: 13px; line-height: 1.6;">
										<?php
										// sanitize_diff_panel applies wp_kses with DIFF_ALLOWED_TAGS.
										echo self::sanitize_diff_panel( $diff['before'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									</div>
								</div>
							</div>
							
							<div class="postbox">
								<div class="postbox-header">
									<h3 class="hndle"><?php esc_html_e( 'Bewerkte versie', 'zw-ttvgpt' ); ?></h3>
								</div>
								<div class="inside">
									<div style="max-height: 400px; overflow-y: auto; padding: 10px; font-size: 13px; line-height: 1.6;">
										<?php
										// sanitize_diff_panel applies wp_kses with DIFF_ALLOWED_TAGS.
										echo self::sanitize_diff_panel( $diff['after'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
