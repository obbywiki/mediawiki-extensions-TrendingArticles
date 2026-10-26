<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\Trending\Tests\Unit;

use MediaWiki\Extension\Trending\Analytics\AnalyticsImporter;
use MediaWiki\Extension\Trending\Analytics\AnalyticsStore;
use MediaWiki\Extension\Trending\Analytics\PageViewProvider;
use MediaWikiUnitTestCase;
use RuntimeException;

/**
 * @group TrendingArticles
 * @covers \MediaWiki\Extension\Trending\Analytics\AnalyticsImporter
 */
class AnalyticsImporterTest extends MediaWikiUnitTestCase {

	public function test_resolves_and_aggregates_eligible_pages_before_replacing_snapshot(): void {
		$provider = $this->createMock( PageViewProvider::class );
		$provider->method( 'getKey' )->willReturn( 'source' );
		$provider->method( 'fetchDailyViews' )->with( '2026-10-20' )->willReturn( [
			'A' => 10, 'A_alias' => 5, 'Talk:A' => 9, 'Deleted' => 3,
		] );
		$store = $this->createMock( AnalyticsStore::class );
		$store->expects( $this->once() )->method( 'replaceDay' )
			->with( 'source', '20261020', [ 7 => 15 ] );
		$importer = new AnalyticsImporter( $provider, $store,
			static fn ( string $name ) => in_array( $name, [ 'A', 'A_alias' ], true ) ? 7 : null
		);
		$this->assertSame( 1, $importer->importDay( '2026-10-20' ) );
	}

	public function test_failed_fetch_never_touches_stored_counts(): void {
		$provider = $this->createMock( PageViewProvider::class );
		$provider->method( 'fetchDailyViews' )->willThrowException( new RuntimeException( 'Unavailable' ) );
		$store = $this->createMock( AnalyticsStore::class );
		$store->expects( $this->never() )->method( 'replaceDay' );
		$importer = new AnalyticsImporter( $provider, $store, static fn () => 7 );
		$this->expectException( RuntimeException::class );
		$importer->importDay( '2026-10-20' );
	}

	public function test_successful_zero_day_replaces_previous_snapshot(): void {
		$provider = $this->createMock( PageViewProvider::class );
		$provider->method( 'getKey' )->willReturn( 'source' );
		$provider->method( 'fetchDailyViews' )->willReturn( [] );
		$store = $this->createMock( AnalyticsStore::class );
		$store->expects( $this->once() )->method( 'replaceDay' )->with( 'source', '20261020', [] );
		$importer = new AnalyticsImporter( $provider, $store, static fn () => null );
		$this->assertSame( 0, $importer->importDay( '2026-10-20' ) );
	}
}
