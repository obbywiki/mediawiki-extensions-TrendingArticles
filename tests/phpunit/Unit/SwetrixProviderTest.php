<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\Trending\Tests\Unit;

use MediaWiki\Extension\Trending\Analytics\SwetrixProvider;
use MediaWikiUnitTestCase;
use RuntimeException;

/**
 * @group TrendingArticles
 * @covers \MediaWiki\Extension\Trending\Analytics\SwetrixProvider
 */
class SwetrixProviderTest extends MediaWikiUnitTestCase {

	public function test_pagination_uses_pageviews_and_scopes_day_host_and_action(): void {
		$requests = [];
		$provider = new SwetrixProvider( 'https://stats.example/backend/', 'project', 'wiki.example',
			static function ( string $url ) use ( &$requests ): array {
				parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
				$requests[] = $query;
				$offset = (int)$query['offset'];
				$rows = [];
				for ( $i = $offset; $i < min( $offset + 100, 101 ); $i++ ) {
					$rows[] = [ 'value' => '/Article_' . $i, 'pageviews' => 10 + $i, 'visitors' => 1 ];
				}
				return [ 'data' => $rows, 'meta' => [ 'total' => 101 ] ];
			}
		);
		$counts = $provider->fetchDailyViews( '2026-10-20' );
		$this->assertCount( 101, $counts );
		$this->assertSame( 110, $counts['Article_100'] );
		$this->assertCount( 2, $requests );
		$this->assertSame( '100', $requests[1]['offset'] );
		$this->assertSame( 'pageviews', $requests[0]['metrics'] );
		$this->assertSame( '2026-10-20', $requests[0]['from'] );
		$this->assertSame( $requests[0]['from'], $requests[0]['to'] );
		$this->assertSame( 'Etc/GMT', $requests[0]['timezone'] );
		$this->assertSame( [
			[ 'dimension' => 'host', 'operator' => 'is', 'value' => 'wiki.example' ],
			[ 'dimension' => 'page_property', 'key' => 'action', 'operator' => 'is', 'value' => 'view' ],
		], json_decode( $requests[0]['filters'], true ) );
	}

	public function test_empty_report_is_successful(): void {
		$provider = $this->provider_returning( [ 'data' => [], 'meta' => [ 'total' => 0 ] ] );
		$this->assertSame( [], $provider->fetchDailyViews( '2026-10-20' ) );
	}

	public function test_literal_percent_and_unicode_titles_are_preserved(): void {
		$provider = $this->provider_returning( [
			'data' => [
				[ 'value' => '/100%25', 'pageviews' => 4 ],
				[ 'value' => '/Café/攻略', 'pageviews' => 3 ],
			], 'meta' => [ 'total' => 2 ],
		] );
		$this->assertSame( [ '100%25' => 4, 'Café/攻略' => 3 ], $provider->fetchDailyViews( '2026-10-20' ) );
	}

	public function test_incomplete_report_fails_instead_of_returning_partial_data(): void {
		$calls = 0;
		$provider = new SwetrixProvider( 'https://stats.example', 'project', 'wiki.example',
			static function () use ( &$calls ): array {
				return [
					'data' => $calls++ === 0 ? [ [ 'value' => '/A', 'pageviews' => 12 ] ] : [],
					'meta' => [ 'total' => 2 ],
				];
			}
		);
		$this->expectException( RuntimeException::class );
		$provider->fetchDailyViews( '2026-10-20' );
	}

	public function test_repeated_pages_fail_instead_of_double_counting(): void {
		$provider = $this->provider_returning( [
			'data' => [ [ 'value' => '/A', 'pageviews' => 12 ] ], 'meta' => [ 'total' => 2 ],
		] );
		$this->expectException( RuntimeException::class );
		$provider->fetchDailyViews( '2026-10-20' );
	}

	public function test_missing_total_is_not_mistaken_for_empty_report(): void {
		$provider = $this->provider_returning( [ 'data' => [] ] );
		$this->expectException( RuntimeException::class );
		$provider->fetchDailyViews( '2026-10-20' );
	}

	public function test_visitors_cannot_substitute_for_pageviews(): void {
		$provider = $this->provider_returning( [
			'data' => [ [ 'value' => '/A', 'visitors' => 2 ] ], 'meta' => [ 'total' => 1 ],
		] );
		$this->expectException( RuntimeException::class );
		$provider->fetchDailyViews( '2026-10-20' );
	}

	public function test_provider_identity_separates_projects_and_hosts(): void {
		$a = $this->provider_returning( [] );
		$b = new SwetrixProvider( 'https://stats.example', 'other', 'wiki.example', static fn () => [] );
		$c = new SwetrixProvider( 'https://stats.example', 'project', 'other.example', static fn () => [] );
		$this->assertNotSame( $a->getKey(), $b->getKey() );
		$this->assertNotSame( $a->getKey(), $c->getKey() );
	}

	private function provider_returning( array $response ): SwetrixProvider {
		return new SwetrixProvider( 'https://stats.example', 'project', 'wiki.example', static fn () => $response );
	}
}
