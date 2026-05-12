<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZW_TTVGPT_Core\Helper;

#[CoversClass(Helper::class)]
final class HelperApiKeyTest extends TestCase {

	#[DataProvider('apiKeyProvider')]
	public function test_is_valid_api_key( string $input, bool $expected ): void {
		self::assertSame( $expected, Helper::is_valid_api_key( $input ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function apiKeyProvider(): array {
		return array(
			'empty string is rejected'        => array( '', false ),
			'classic sk- key is accepted'     => array( 'sk-AAAAAAAAAAAAAAAAAAAAAAAA', true ),
			'project key is accepted'         => array( 'sk-proj-ABCDEF1234567890', true ),
			'service account key is accepted' => array( 'sk-svcacct-ABCDEF1234567890', true ),
			'admin key is accepted'           => array( 'sk-admin-ABCDEF1234567890', true ),
			// Pin the intentional looseness: anything with the sk- prefix should pass,
			// so a future OpenAI key flavour does not need a code change to be accepted.
			'unknown future flavour passes'   => array( 'sk-future-flavor-xyz', true ),
			'no prefix is rejected'           => array( 'totally-not-a-key', false ),
			'uppercase prefix is rejected'    => array( 'SK-AAAAAAAAAAAA', false ),
			'partial prefix is rejected'      => array( 's-AAAAAAAAAAAA', false ),
		);
	}
}
