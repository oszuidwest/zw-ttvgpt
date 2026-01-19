<?php
/**
 * Audit Status enum for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit Status enum.
 *
 * Represents the possible states of content in the audit system.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
enum AuditStatus: string {
	/**
	 * Content was fully written by a human without AI assistance.
	 *
	 * @since 1.0.0
	 */
	case FullyHumanWritten = 'fully_human_written';

	/**
	 * Content was AI-generated and has not been edited by a human.
	 *
	 * @since 1.0.0
	 */
	case AiWrittenNotEdited = 'ai_written_not_edited';

	/**
	 * Content was AI-generated and has been edited by a human.
	 *
	 * @since 1.0.0
	 */
	case AiWrittenEdited = 'ai_written_edited';

	/**
	 * Gets the translated label for this status.
	 *
	 * @since 1.0.0
	 *
	 * @return string Translated label.
	 */
	public function get_label(): string {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Valid in PHP 8.1+ enums
		return match ( $this ) {
			self::FullyHumanWritten  => __( 'Handgeschreven', 'zw-ttvgpt' ),
			self::AiWrittenNotEdited => __( 'AI-gegenereerd', 'zw-ttvgpt' ),
			self::AiWrittenEdited    => __( 'AI-bewerkt', 'zw-ttvgpt' ),
		};
	}

	/**
	 * Gets the CSS class for this status.
	 *
	 * @since 1.0.0
	 *
	 * @return string CSS class name.
	 */
	public function get_css_class(): string {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Valid in PHP 8.1+ enums
		return match ( $this ) {
			self::FullyHumanWritten  => 'human',
			self::AiWrittenNotEdited => 'ai-unedited',
			self::AiWrittenEdited    => 'ai-edited',
		};
	}
}
