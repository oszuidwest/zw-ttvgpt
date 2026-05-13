<?php
declare(strict_types=1);

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	require_once __DIR__ . '/wp-load-helper.php';

	$GLOBALS['zw_test_registered_post_meta'] = array();
	$GLOBALS['zw_test_post_meta']           = array();
	$GLOBALS['zw_test_post_types']          = array();
	$GLOBALS['zw_test_autosave_revisions']  = array();

	if ( ! function_exists( 'register_post_meta' ) ) {
		function register_post_meta( string $post_type, string $meta_key, array $args ): bool {
			$GLOBALS['zw_test_registered_post_meta'][ $post_type ][ $meta_key ] = $args;
			return true;
		}
	}

	if ( ! function_exists( 'update_post_meta' ) ) {
		function update_post_meta( int $post_id, string $meta_key, mixed $meta_value, mixed $prev_value = '' ): bool {
			unset( $prev_value );

			$GLOBALS['zw_test_post_meta'][ $post_id ][ $meta_key ] = $meta_value;
			return true;
		}
	}

	if ( ! function_exists( 'delete_post_meta' ) ) {
		function delete_post_meta( int $post_id, string $meta_key, mixed $meta_value = '' ): bool {
			unset( $meta_value );

			unset( $GLOBALS['zw_test_post_meta'][ $post_id ][ $meta_key ] );
			return true;
		}
	}

	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( int $post_id, string $meta_key = '', bool $single = false ): mixed {
			$meta = $GLOBALS['zw_test_post_meta'][ $post_id ] ?? array();
			if ( '' === $meta_key ) {
				return $meta;
			}

			$value = $meta[ $meta_key ] ?? '';
			return $single ? $value : array( $value );
		}
	}

	if ( ! function_exists( 'get_post_type' ) ) {
		function get_post_type( int $post_id ): string|false {
			return $GLOBALS['zw_test_post_types'][ $post_id ] ?? 'post';
		}
	}

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( mixed $thing ): bool {
			return $thing instanceof WP_Error;
		}
	}

	if ( ! function_exists( 'wp_is_post_autosave' ) ) {
		function wp_is_post_autosave( mixed $post ): int|false {
			$revision_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
			return $GLOBALS['zw_test_autosave_revisions'][ $revision_id ] ?? false;
		}
	}

	if ( ! function_exists( 'add_meta_box' ) ) {
		function add_meta_box(
			string $id,
			string $title,
			callable $callback,
			string|array|\WP_Screen|null $screen = null,
			string $context = 'advanced',
			string $priority = 'default',
			array $callback_args = array()
		): void {
			unset( $id, $title, $callback, $screen, $context, $priority, $callback_args );
		}
	}
}

namespace ZW_TTVGPT_Core\Tests {

	use PHPUnit\Framework\Attributes\CoversClass;
	use PHPUnit\Framework\TestCase;
	use ZW_TTVGPT_Core\Constants;
	use ZW_TTVGPT_Core\RevisionManager;

	#[CoversClass(RevisionManager::class)]
	final class RevisionManagerTest extends TestCase {

		protected function setUp(): void {
			$GLOBALS['zw_test_registered_post_meta'] = array();
			$GLOBALS['zw_test_post_meta']           = array();
			$GLOBALS['zw_test_post_types']          = array( 123 => Constants::SUPPORTED_POST_TYPE );
			$GLOBALS['zw_test_autosave_revisions']  = array();
			$GLOBALS['zw_test_transients']          = array();
			$GLOBALS['zw_test_set_transient_calls'] = array();

			remove_all_filters( 'wp_post_revision_meta_keys' );
			remove_all_actions( '_wp_put_post_revision' );
			remove_all_actions( 'wp_after_insert_post' );
			remove_all_filters( '_wp_post_revision_fields' );
			remove_all_actions( 'add_meta_boxes_' . Constants::SUPPORTED_POST_TYPE );

			foreach ( self::revisionUiFieldHooks() as $hook ) {
				remove_all_filters( $hook );
			}
		}

		protected function tearDown(): void {
			remove_all_filters( 'wp_post_revision_meta_keys' );
			remove_all_actions( '_wp_put_post_revision' );
			remove_all_actions( 'wp_after_insert_post' );
			remove_all_filters( '_wp_post_revision_fields' );
			remove_all_actions( 'add_meta_boxes_' . Constants::SUPPORTED_POST_TYPE );

			foreach ( self::revisionUiFieldHooks() as $hook ) {
				remove_all_filters( $hook );
			}
		}

		public function test_registers_revisioned_meta_for_acf_and_ai_generation_fields(): void {
			new RevisionManager( new RecordingLogger() );

			$registered = $GLOBALS['zw_test_registered_post_meta'][ Constants::SUPPORTED_POST_TYPE ] ?? array();

			self::assertArrayHasKey( Constants::ACF_FIELD_HUMAN_CONTENT, $registered );
			self::assertArrayHasKey( Constants::ACF_FIELD_AI_CONTENT, $registered );
			self::assertArrayHasKey( RevisionManager::META_MODEL, $registered );
			self::assertArrayHasKey( RevisionManager::META_REGIONS, $registered );
			self::assertArrayHasKey( RevisionManager::META_IS_AI_GENERATED, $registered );
			self::assertTrue( $registered[ RevisionManager::META_MODEL ]['revisions_enabled'] );
			self::assertTrue( $registered[ Constants::ACF_FIELD_HUMAN_CONTENT ]['single'] );
		}

		public function test_revision_meta_filter_adds_keys_only_for_supported_post_type(): void {
			new RevisionManager( new RecordingLogger() );

			$post_keys = apply_filters( 'wp_post_revision_meta_keys', array( 'existing_key' ), Constants::SUPPORTED_POST_TYPE );
			$page_keys = apply_filters( 'wp_post_revision_meta_keys', array( 'existing_key' ), 'page' );

			self::assertContains( 'existing_key', $post_keys );
			self::assertContains( RevisionManager::META_MODEL, $post_keys );
			self::assertContains( Constants::ACF_FIELD_HUMAN_CONTENT, $post_keys );
			self::assertSame( array( 'existing_key' ), $page_keys );
		}

		public function test_record_generation_marks_metadata_for_next_native_revision(): void {
			$logger  = new RecordingLogger();
			$manager = new RevisionManager( $logger );

			$manager->record_generation(
				123,
				'gpt-5.5',
				array( 'Bergen op Zoom', 'Roosendaal', 'Bergen op Zoom', '' )
			);

			self::assertSame( 'gpt-5.5', $GLOBALS['zw_test_post_meta'][123][ RevisionManager::META_MODEL ] );
			self::assertSame( '["Bergen op Zoom","Roosendaal"]', $GLOBALS['zw_test_post_meta'][123][ RevisionManager::META_REGIONS ] );
			self::assertSame( '1', $GLOBALS['zw_test_post_meta'][123][ RevisionManager::META_IS_AI_GENERATED ] );
			self::assertSame( '1', $GLOBALS['zw_test_transients']['zw_ttvgpt_pending_revision_123'] );
			self::assertSame( HOUR_IN_SECONDS, $GLOBALS['zw_test_set_transient_calls'][0]['expiration'] );
			self::assertSame( 'AI summary metadata recorded for next post revision', $logger->debugs[0]['message'] );
		}

		public function test_pending_revision_is_tagged_after_core_meta_copy(): void {
			$logger  = new RecordingLogger();
			$manager = new RevisionManager( $logger );

			$manager->record_generation( 123, 'gpt-5.5', array( 'Roosendaal' ) );
			self::copyGenerationMetaToRevision( 123, 9001 );

			do_action( '_wp_put_post_revision', 9001, 123 );

			self::assertArrayNotHasKey( 'zw_ttvgpt_pending_revision_123', $GLOBALS['zw_test_transients'] );
			self::assertSame( '1', $GLOBALS['zw_test_post_meta'][9001][ RevisionManager::META_IS_AI_GENERATED ] );
			self::assertSame( 'gpt-5.5', $GLOBALS['zw_test_post_meta'][9001][ RevisionManager::META_MODEL ] );
			self::assertSame( '["Roosendaal"]', $GLOBALS['zw_test_post_meta'][9001][ RevisionManager::META_REGIONS ] );
			self::assertSame( 'AI summary revision tagged', $logger->debugs[1]['message'] );
		}

		public function test_autosave_revision_does_not_consume_pending_marker(): void {
			$logger  = new RecordingLogger();
			$manager = new RevisionManager( $logger );

			$manager->record_generation( 123, 'gpt-5.5', array( 'Roosendaal' ) );
			self::copyGenerationMetaToRevision( 123, 800 );
			$GLOBALS['zw_test_autosave_revisions'][800] = 123;

			do_action( '_wp_put_post_revision', 800, 123 );

			self::assertSame( '1', $GLOBALS['zw_test_transients']['zw_ttvgpt_pending_revision_123'] );
			self::assertArrayNotHasKey( RevisionManager::META_IS_AI_GENERATED, $GLOBALS['zw_test_post_meta'][800] );

			self::copyGenerationMetaToRevision( 123, 9001 );
			do_action( '_wp_put_post_revision', 9001, 123 );

			self::assertArrayNotHasKey( 'zw_ttvgpt_pending_revision_123', $GLOBALS['zw_test_transients'] );
			self::assertSame( '1', $GLOBALS['zw_test_post_meta'][9001][ RevisionManager::META_IS_AI_GENERATED ] );
		}

		public function test_non_pending_revision_loses_only_ai_marker(): void {
			new RevisionManager( new RecordingLogger() );

			$GLOBALS['zw_test_post_meta'][123][ RevisionManager::META_MODEL ]           = 'gpt-5.5';
			$GLOBALS['zw_test_post_meta'][123][ RevisionManager::META_REGIONS ]         = '["Roosendaal"]';
			$GLOBALS['zw_test_post_meta'][123][ RevisionManager::META_IS_AI_GENERATED ] = '1';
			$GLOBALS['zw_test_post_meta'][700][ RevisionManager::META_MODEL ]           = 'gpt-5.5';
			$GLOBALS['zw_test_post_meta'][700][ RevisionManager::META_REGIONS ]         = '["Roosendaal"]';
			$GLOBALS['zw_test_post_meta'][700][ RevisionManager::META_IS_AI_GENERATED ] = '1';

			do_action( '_wp_put_post_revision', 700, 123 );

			self::assertSame( 'gpt-5.5', $GLOBALS['zw_test_post_meta'][700][ RevisionManager::META_MODEL ] );
			self::assertSame( '["Roosendaal"]', $GLOBALS['zw_test_post_meta'][700][ RevisionManager::META_REGIONS ] );
			self::assertArrayNotHasKey( RevisionManager::META_IS_AI_GENERATED, $GLOBALS['zw_test_post_meta'][700] );
			self::assertSame( 'gpt-5.5', $GLOBALS['zw_test_post_meta'][123][ RevisionManager::META_MODEL ] );
			self::assertSame( '["Roosendaal"]', $GLOBALS['zw_test_post_meta'][123][ RevisionManager::META_REGIONS ] );
			self::assertSame( '1', $GLOBALS['zw_test_post_meta'][123][ RevisionManager::META_IS_AI_GENERATED ] );
		}

		public function test_unresolved_pending_marker_is_cleared_after_save_without_revision(): void {
			$logger  = new RecordingLogger();
			$manager = new RevisionManager( $logger );

			$manager->record_generation( 123, 'gpt-5.5', array() );

			do_action( 'wp_after_insert_post', 123, (object) array( 'post_type' => Constants::SUPPORTED_POST_TYPE ), true );

			self::assertArrayNotHasKey( 'zw_ttvgpt_pending_revision_123', $GLOBALS['zw_test_transients'] );
			self::assertCount( 1, $logger->errors );
			self::assertSame( 'AI summary revision marker cleared without a revision; revisions may be disabled or unavailable', $logger->errors[0]['message'] );
		}

		public function test_adds_and_formats_revision_compare_fields(): void {
			new RevisionManager( new RecordingLogger() );

			$fields = apply_filters(
				'_wp_post_revision_fields',
				array( 'post_title' => 'Title' ),
				array( 'post_type' => Constants::SUPPORTED_POST_TYPE )
			);
			$page_fields = apply_filters(
				'_wp_post_revision_fields',
				array( 'post_title' => 'Title' ),
				array( 'post_type' => 'page' )
			);
			$regions = apply_filters(
				'_wp_post_revision_field__zw_ttvgpt_regions',
				'["Bergen op Zoom","Roosendaal"]',
				RevisionManager::META_REGIONS,
				(object) array( 'ID' => 9001 ),
				'to'
			);
			$marker = apply_filters(
				'_wp_post_revision_field__zw_ttvgpt_is_ai_generated',
				'1',
				RevisionManager::META_IS_AI_GENERATED,
				(object) array( 'ID' => 9001 ),
				'to'
			);

			self::assertArrayHasKey( Constants::ACF_FIELD_HUMAN_CONTENT, $fields );
			self::assertArrayHasKey( RevisionManager::META_MODEL, $fields );
			self::assertSame( array( 'post_title' => 'Title' ), $page_fields );
			self::assertSame( 'Bergen op Zoom, Roosendaal', $regions );
			self::assertSame( 'Ja', $marker );
		}

		/**
		 * @return array<string>
		 */
		private static function revisionUiFieldHooks(): array {
			return array(
				'_wp_post_revision_field_' . Constants::ACF_FIELD_HUMAN_CONTENT,
				'_wp_post_revision_field_' . Constants::ACF_FIELD_AI_CONTENT,
				'_wp_post_revision_field_' . RevisionManager::META_MODEL,
				'_wp_post_revision_field_' . RevisionManager::META_REGIONS,
				'_wp_post_revision_field_' . RevisionManager::META_IS_AI_GENERATED,
			);
		}

		private static function copyGenerationMetaToRevision( int $post_id, int $revision_id ): void {
			foreach (
				array(
					RevisionManager::META_MODEL,
					RevisionManager::META_REGIONS,
					RevisionManager::META_IS_AI_GENERATED,
				) as $meta_key
			) {
				$GLOBALS['zw_test_post_meta'][ $revision_id ][ $meta_key ] = $GLOBALS['zw_test_post_meta'][ $post_id ][ $meta_key ];
			}
		}
	}
}
