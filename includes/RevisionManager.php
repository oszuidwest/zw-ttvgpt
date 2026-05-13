<?php
/**
 * Native WordPress revision integration for AI summaries.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and annotates WordPress revisions that contain generated summaries.
 *
 * WordPress 6.4+ can revision post meta when keys opt in through
 * register_post_meta() or the wp_post_revision_meta_keys filter. This manager
 * keeps the plugin on that native path instead of maintaining a parallel
 * version table in custom post meta.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class RevisionManager {
	/**
	 * Meta key storing the model used for a generated summary.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string META_MODEL = '_zw_ttvgpt_model';

	/**
	 * Meta key storing the selected regions as JSON.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string META_REGIONS = '_zw_ttvgpt_regions';

	/**
	 * Meta key marking a revision as created by a successful AI generation.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string META_IS_AI_GENERATED = '_zw_ttvgpt_is_ai_generated';

	/**
	 * Transient key prefix for pending AI revision markers.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const string PENDING_AI_REVISION_TRANSIENT_PREFIX = 'zw_ttvgpt_pending_revision_';

	/**
	 * Maximum time to wait for the post save that should create the AI revision.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const int PENDING_AI_REVISION_TTL = HOUR_IN_SECONDS;

	/**
	 * Maximum AI revisions shown in the editor metabox.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const int AI_REVISIONS_PER_PAGE = 5;

	/**
	 * Creates the revision manager and registers WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param Logger $logger Logger instance for debug output.
	 */
	public function __construct( private readonly Logger $logger ) {
		$this->register_revisioned_meta();

		add_filter( 'wp_post_revision_meta_keys', $this->add_revision_meta_keys( ... ), 10, 2 );
		add_action( '_wp_put_post_revision', $this->tag_revision( ... ), 11, 2 );
		add_action( 'wp_after_insert_post', $this->clear_unresolved_pending_revision( ... ), 20, 3 );
		add_filter( '_wp_post_revision_fields', $this->add_revision_fields( ... ), 10, 2 );

		foreach ( array_keys( $this->get_revision_ui_fields() ) as $field ) {
			add_filter( "_wp_post_revision_field_{$field}", $this->format_revision_field( ... ), 10, 4 );
		}

		add_action( 'add_meta_boxes_' . Constants::SUPPORTED_POST_TYPE, $this->add_ai_revisions_meta_box( ... ) );
	}

	/**
	 * Stores generation metadata for the next native WordPress revision.
	 *
	 * The AJAX generation flow receives live editor content, but the post table
	 * still contains the last saved content until the editor is saved. Creating a
	 * revision directly from this AJAX request would snapshot stale post_content.
	 * Instead, we store the generated meta now and let the next natural post save
	 * create the revision after WordPress has persisted the editor content.
	 *
	 * @since 1.0.0
	 *
	 * @param int           $post_id Post ID that received the generated summary.
	 * @param string        $model   OpenAI model used for generation.
	 * @param array<string> $regions Region labels selected in the editor.
	 */
	public function record_generation( int $post_id, string $model, array $regions ): void {
		update_post_meta( $post_id, self::META_MODEL, sanitize_text_field( $model ) );
		update_post_meta( $post_id, self::META_REGIONS, $this->encode_regions( $regions ) );
		update_post_meta( $post_id, self::META_IS_AI_GENERATED, '1' );

		$pending_key = $this->get_pending_revision_transient_key( $post_id );
		set_transient( $pending_key, '1', self::PENDING_AI_REVISION_TTL );
		if ( '1' !== (string) get_transient( $pending_key ) ) {
			$this->logger->error( 'Failed to store pending AI revision marker', array( 'post_id' => $post_id ) );
		}

		$this->logger->debug( 'AI summary metadata recorded for next post revision', array( 'post_id' => $post_id ) );
	}

	/**
	 * Ensures plugin metadata is included in WordPress post-meta revisions.
	 *
	 * The register_post_meta() call is the primary registration path. This filter
	 * keeps the keys present if another component adjusts the registry.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $keys      Existing revisioned meta keys.
	 * @param string        $post_type Post type being revisioned.
	 * @return array<string> Updated revisioned meta keys.
	 */
	public function add_revision_meta_keys( array $keys, string $post_type ): array {
		if ( Constants::SUPPORTED_POST_TYPE !== $post_type ) {
			return $keys;
		}

		$revisioned_keys = array_fill_keys( $keys, true );
		foreach ( array_keys( $this->get_revisioned_meta_definitions() ) as $meta_key ) {
			$revisioned_keys[ $meta_key ] = true;
		}

		return array_keys( $revisioned_keys );
	}

	/**
	 * Marks the just-created revision as an AI generation only when appropriate.
	 *
	 * @since 1.0.0
	 *
	 * @param int $revision_id Revision ID.
	 * @param int $post_id     Parent post ID.
	 */
	public function tag_revision( int $revision_id, int $post_id ): void {
		if ( Constants::SUPPORTED_POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $revision_id ) ) {
			$this->delete_ai_marker_from_revision( $revision_id );
			return;
		}

		$pending_key            = $this->get_pending_revision_transient_key( $post_id );
		$is_pending_ai_revision = '1' === (string) get_transient( $pending_key );
		if ( ! $is_pending_ai_revision ) {
			$this->delete_ai_marker_from_revision( $revision_id );
			return;
		}

		delete_transient( $pending_key );

		$this->logger->debug(
			'AI summary revision tagged',
			array(
				'post_id'     => $post_id,
				'revision_id' => $revision_id,
			)
		);
	}

	/**
	 * Clears a pending marker when a save did not produce a revision.
	 *
	 * Core saves post update revisions before this cleanup runs. If our later
	 * priority still sees the pending marker, revisions are disabled, the
	 * revision hooks were removed, no revision was created, or the pending
	 * marker outlived the editor session.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Post ID that was inserted or updated.
	 * @param object $post    Post object that was inserted or updated.
	 * @param bool   $update  Whether this insert updated an existing post.
	 */
	public function clear_unresolved_pending_revision( int $post_id, object $post, bool $update ): void {
		if ( ! $update || Constants::SUPPORTED_POST_TYPE !== ( $post->post_type ?? '' ) ) {
			return;
		}

		$pending_key = $this->get_pending_revision_transient_key( $post_id );
		if ( '1' !== (string) get_transient( $pending_key ) ) {
			return;
		}

		delete_transient( $pending_key );
		$this->logger->error(
			'AI summary revision marker cleared without a revision; revisions may be disabled or unavailable',
			array( 'post_id' => $post_id )
		);
	}

	/**
	 * Adds ACF and AI metadata fields to the WordPress revision compare UI.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $fields Revision fields keyed by field name.
	 * @param array<string, mixed>  $post   Post array being revisioned.
	 * @return array<string, string> Updated revision fields.
	 */
	public function add_revision_fields( array $fields, array $post ): array {
		if ( Constants::SUPPORTED_POST_TYPE !== ( $post['post_type'] ?? '' ) ) {
			return $fields;
		}

		return array_merge( $fields, $this->get_revision_ui_fields() );
	}

	/**
	 * Formats plugin meta values before WordPress computes the revision diff.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $revision_field Raw revision field value.
	 * @param string $field          Revision field name.
	 * @param object $revision       Revision post object.
	 * @param string $context        Diff context, either "from" or "to".
	 * @return string Formatted value for diffing.
	 */
	public function format_revision_field( mixed $revision_field, string $field, object $revision, string $context ): string {
		unset( $revision, $context );

		if ( self::META_REGIONS === $field ) {
			return implode( ', ', $this->decode_regions( (string) $revision_field ) );
		}

		if ( self::META_IS_AI_GENERATED === $field ) {
			return '1' === (string) $revision_field ? __( 'Ja', 'zw-ttvgpt' ) : __( 'Nee', 'zw-ttvgpt' );
		}

		return is_scalar( $revision_field ) ? (string) $revision_field : '';
	}

	/**
	 * Registers the editor sidebar metabox containing AI revision links.
	 *
	 * @since 1.0.0
	 */
	public function add_ai_revisions_meta_box(): void {
		add_meta_box(
			'zw-ttvgpt-ai-revisions',
			__( 'Tekst TV AI-versies', 'zw-ttvgpt' ),
			$this->render_ai_revisions_meta_box( ... ),
			Constants::SUPPORTED_POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Renders links to AI-tagged revisions for the current post.
	 *
	 * @since 1.0.0
	 *
	 * @param object $post Current post object.
	 */
	public function render_ai_revisions_meta_box( object $post ): void {
		$post_id   = isset( $post->ID ) ? (int) $post->ID : 0;
		$revisions = $this->get_ai_revisions( $post_id );

		if ( array() === $revisions ) {
			echo '<p>' . esc_html__( 'Nog geen AI-versies opgeslagen.', 'zw-ttvgpt' ) . '</p>';
			return;
		}

		echo '<ul>';
		foreach ( $revisions as $revision ) {
			$revision_id = (int) $revision->ID;
			$model       = (string) get_post_meta( $revision_id, self::META_MODEL, true );
			$link        = add_query_arg( array( 'revision' => $revision_id ), admin_url( 'revision.php' ) );
			$date        = mysql2date(
				sprintf(
					'%1$s %2$s',
					(string) get_option( 'date_format' ),
					(string) get_option( 'time_format' )
				),
				(string) $revision->post_date
			);
			$label       = '' !== $model ? sprintf( '%1$s - %2$s', $date, $model ) : $date;

			echo '<li><a href="' . esc_url( $link ) . '">' . esc_html( $label ) . '</a></li>';
		}
		echo '</ul>';
	}

	/**
	 * Retrieves revisions explicitly tagged as AI-generated.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Parent post ID.
	 * @return array<int, object> Revision post objects keyed by revision ID.
	 */
	public function get_ai_revisions( int $post_id ): array {
		if ( $post_id <= 0 || ! function_exists( 'wp_get_post_revisions' ) ) {
			return array();
		}

		$revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled'  => false,
				'meta_key'       => self::META_IS_AI_GENERATED,
				'meta_value'     => '1',
				'order'          => 'DESC',
				'orderby'        => 'date ID',
				'posts_per_page' => self::AI_REVISIONS_PER_PAGE,
			)
		);

		return $revisions;
	}

	/**
	 * Registers meta keys for native WordPress revision storage.
	 *
	 * @since 1.0.0
	 */
	private function register_revisioned_meta(): void {
		foreach ( $this->get_revisioned_meta_definitions() as $meta_key => $args ) {
			register_post_meta(
				Constants::SUPPORTED_POST_TYPE,
				$meta_key,
				array_merge(
					array(
						'single'            => true,
						'show_in_rest'      => false,
						'revisions_enabled' => true,
					),
					$args
				)
			);
		}
	}

	/**
	 * Returns all meta keys that should be copied into WordPress revisions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Meta definitions keyed by meta key.
	 */
	private function get_revisioned_meta_definitions(): array {
		return array(
			Constants::ACF_FIELD_HUMAN_CONTENT => array(
				'type'        => 'string',
				'label'       => __( 'Tekst TV samenvatting', 'zw-ttvgpt' ),
				'description' => __( 'De actuele Tekst TV-samenvatting.', 'zw-ttvgpt' ),
			),
			Constants::ACF_FIELD_AI_CONTENT    => array(
				'type'        => 'string',
				'label'       => __( 'Tekst TV AI-versie', 'zw-ttvgpt' ),
				'description' => __( 'De laatst door AI gegenereerde Tekst TV-samenvatting.', 'zw-ttvgpt' ),
			),
			self::META_MODEL                   => array(
				'type'        => 'string',
				'label'       => __( 'Tekst TV AI-model', 'zw-ttvgpt' ),
				'description' => __( 'OpenAI-model waarmee de samenvatting is gemaakt.', 'zw-ttvgpt' ),
			),
			self::META_REGIONS                 => array(
				'type'        => 'string',
				'label'       => __( 'Tekst TV regio\'s', 'zw-ttvgpt' ),
				'description' => __( 'Geselecteerde regio\'s tijdens generatie.', 'zw-ttvgpt' ),
			),
			self::META_IS_AI_GENERATED         => array(
				'type'        => 'string',
				'label'       => __( 'Tekst TV AI-generatie', 'zw-ttvgpt' ),
				'description' => __( 'Marker die aangeeft dat deze revisie door AI-generatie is ontstaan.', 'zw-ttvgpt' ),
			),
		);
	}

	/**
	 * Returns the meta fields shown in WordPress' revision compare UI.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> UI fields keyed by meta key.
	 */
	private function get_revision_ui_fields(): array {
		return array(
			Constants::ACF_FIELD_HUMAN_CONTENT => __( 'Tekst TV samenvatting', 'zw-ttvgpt' ),
			Constants::ACF_FIELD_AI_CONTENT    => __( 'Tekst TV AI-versie', 'zw-ttvgpt' ),
			self::META_MODEL                   => __( 'Tekst TV AI-model', 'zw-ttvgpt' ),
			self::META_REGIONS                 => __( 'Tekst TV regio\'s', 'zw-ttvgpt' ),
			self::META_IS_AI_GENERATED         => __( 'Tekst TV AI-generatie', 'zw-ttvgpt' ),
		);
	}

	/**
	 * Removes only the AI marker from revisions that were not just generated.
	 *
	 * @since 1.0.0
	 *
	 * @param int $revision_id Revision ID.
	 */
	private function delete_ai_marker_from_revision( int $revision_id ): void {
		delete_post_meta( $revision_id, self::META_IS_AI_GENERATED );
	}

	/**
	 * Builds the transient key for a post's pending AI revision marker.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string Transient key.
	 */
	private function get_pending_revision_transient_key( int $post_id ): string {
		return self::PENDING_AI_REVISION_TRANSIENT_PREFIX . $post_id;
	}

	/**
	 * Sanitizes and JSON-encodes selected region labels.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $regions Raw region labels.
	 * @return string JSON array of region labels.
	 */
	private function encode_regions( array $regions ): string {
		$sanitized = array();
		foreach ( $regions as $region ) {
			$region = sanitize_text_field( $region );
			if ( '' !== $region ) {
				$sanitized[] = $region;
			}
		}

		$sanitized = array_values( array_unique( $sanitized ) );
		$encoded   = wp_json_encode( $sanitized );

		return false === $encoded ? '[]' : $encoded;
	}

	/**
	 * Decodes stored region labels.
	 *
	 * @since 1.0.0
	 *
	 * @param string $regions_json Stored JSON array.
	 * @return array<string> Region labels.
	 */
	private function decode_regions( string $regions_json ): array {
		if ( '' === trim( $regions_json ) ) {
			return array();
		}

		$decoded = json_decode( $regions_json, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$regions = array();
		foreach ( $decoded as $region ) {
			if ( is_scalar( $region ) && '' !== (string) $region ) {
				$regions[] = (string) $region;
			}
		}

		return $regions;
	}
}
