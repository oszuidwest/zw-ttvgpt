<?php
/**
 * Version Manager class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Version Manager class
 *
 * Handles storage and retrieval of multiple AI-generated summary versions
 */
class TTVGPTVersionManager {
	/**
	 * Meta key for storing version history
	 */
	const VERSION_HISTORY_KEY = '_zw_ttvgpt_version_history';

	/**
	 * Maximum number of versions to keep per post
	 */
	const MAX_VERSIONS = 10;

	/**
	 * Logger instance
	 *
	 * @var TTVGPTLogger
	 */
	private TTVGPTLogger $logger;

	/**
	 * Constructor
	 *
	 * @param TTVGPTLogger $logger Logger instance
	 */
	public function __construct( TTVGPTLogger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Save a new AI-generated version
	 *
	 * @param int    $post_id    Post ID
	 * @param string $summary    Generated summary
	 * @param string $model      AI model used
	 * @param array  $regions    Selected regions
	 * @return bool Success status
	 */
	public function save_version( int $post_id, string $summary, string $model, array $regions = array() ): bool {
		$history = $this->get_version_history( $post_id );

		// Create new version entry
		$version = array(
			'id'         => uniqid( 'v_' ),
			'summary'    => $summary,
			'model'      => $model,
			'regions'    => $regions,
			'created_at' => current_time( 'mysql' ),
			'created_by' => get_current_user_id(),
			'is_active'  => true,
		);

		// Mark all other versions as inactive
		foreach ( $history as &$item ) {
			$item['is_active'] = false;
		}

		// Add new version to beginning
		array_unshift( $history, $version );

		// Keep only the most recent versions
		if ( count( $history ) > self::MAX_VERSIONS ) {
			$history = array_slice( $history, 0, self::MAX_VERSIONS );
		}

		// Save to post meta
		$result = update_post_meta( $post_id, self::VERSION_HISTORY_KEY, $history );

		$this->logger->debug(
			'Saved new version',
			array(
				'post_id'    => $post_id,
				'version_id' => $version['id'],
			)
		);

		return $result !== false;
	}

	/**
	 * Get version history for a post
	 *
	 * @param int $post_id Post ID
	 * @return array Version history
	 */
	public function get_version_history( int $post_id ): array {
		$history = get_post_meta( $post_id, self::VERSION_HISTORY_KEY, true );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Get the active version for a post
	 *
	 * @param int $post_id Post ID
	 * @return array|null Active version or null if none
	 */
	public function get_active_version( int $post_id ): ?array {
		$history = $this->get_version_history( $post_id );

		foreach ( $history as $version ) {
			if ( ! empty( $version['is_active'] ) ) {
				return $version;
			}
		}

		// Return most recent if no active version found
		return ! empty( $history ) ? $history[0] : null;
	}

	/**
	 * Set a specific version as active
	 *
	 * @param int    $post_id    Post ID
	 * @param string $version_id Version ID to activate
	 * @return bool Success status
	 */
	public function set_active_version( int $post_id, string $version_id ): bool {
		$history = $this->get_version_history( $post_id );

		$found = false;
		foreach ( $history as &$version ) {
			if ( $version['id'] === $version_id ) {
				$version['is_active'] = true;
				$found               = true;

				// Update ACF fields with this version
				if ( function_exists( 'update_field' ) ) {
					update_field( TTVGPTConstants::ACF_SUMMARY_FIELD, $version['summary'], $post_id );
					update_field( TTVGPTConstants::ACF_GPT_MARKER_FIELD, $version['summary'], $post_id );
				}
			} else {
				$version['is_active'] = false;
			}
		}

		if ( ! $found ) {
			$this->logger->error( 'Version not found', array( 'version_id' => $version_id ) );
			return false;
		}

		return update_post_meta( $post_id, self::VERSION_HISTORY_KEY, $history ) !== false;
	}

	/**
	 * Delete a specific version
	 *
	 * @param int    $post_id    Post ID
	 * @param string $version_id Version ID to delete
	 * @return bool Success status
	 */
	public function delete_version( int $post_id, string $version_id ): bool {
		$history = $this->get_version_history( $post_id );

		// Filter out the version to delete
		$filtered = array_filter(
			$history,
			function ( $version ) use ( $version_id ) {
				return $version['id'] !== $version_id;
			}
		);

		// Don't allow deleting the last version
		if ( empty( $filtered ) ) {
			$this->logger->error( 'Cannot delete last version' );
			return false;
		}

		// If deleted version was active, activate the most recent
		$was_active = false;
		foreach ( $history as $version ) {
			if ( $version['id'] === $version_id && ! empty( $version['is_active'] ) ) {
				$was_active = true;
				break;
			}
		}

		if ( $was_active && ! empty( $filtered ) ) {
			$filtered = array_values( $filtered );
			$filtered[0]['is_active'] = true;

			// Update ACF fields
			if ( function_exists( 'update_field' ) ) {
				update_field( TTVGPTConstants::ACF_SUMMARY_FIELD, $filtered[0]['summary'], $post_id );
				update_field( TTVGPTConstants::ACF_GPT_MARKER_FIELD, $filtered[0]['summary'], $post_id );
			}
		}

		return update_post_meta( $post_id, self::VERSION_HISTORY_KEY, array_values( $filtered ) ) !== false;
	}

	/**
	 * Clear all version history for a post
	 *
	 * @param int $post_id Post ID
	 * @return bool Success status
	 */
	public function clear_history( int $post_id ): bool {
		return delete_post_meta( $post_id, self::VERSION_HISTORY_KEY );
	}
}