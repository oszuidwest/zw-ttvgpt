<?php
/**
 * Audit Status enum for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Audit Status enum
 *
 * Represents the possible states of content in the audit system
 *
 * @package ZW_TTVGPT
 */
enum AuditStatus: string {
	case FullyHumanWritten  = 'fully_human_written';
	case AiWrittenNotEdited = 'ai_written_not_edited';
	case AiWrittenEdited    = 'ai_written_edited';

	/**
	 * Get the translated label for this status
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
	 * Get the CSS class for this status
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
