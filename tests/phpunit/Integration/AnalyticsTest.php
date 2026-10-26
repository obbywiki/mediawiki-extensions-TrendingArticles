<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\Trending\Tests\Integration;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\Trending\Analytics\AnalyticsImporter;
use MediaWiki\Extension\Trending\Analytics\AnalyticsStore;
use MediaWiki\Extension\Trending\Analytics\SwetrixProvider;
use MediaWiki\Extension\Trending\PageViewCounter;
use MediaWiki\Extension\Trending\TrendingQuery;
use MediaWiki\Page\WikiPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group TrendingArticles
 * @group Database
 * @covers \MediaWiki\Extension\Trending\Analytics\AnalyticsImporter
 * @covers \MediaWiki\Extension\Trending\Analytics\AnalyticsStore
 * @covers \MediaWiki\Extension\Trending\PageViewCounter
 * @covers \MediaWiki\Extension\Trending\TrendingQuery
 */
class AnalyticsTest extends MediaWikiIntegrationTestCase {

	private AnalyticsStore $store;
	private WikiPage $page;
	private string $source;
	private string $today;

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'TrendingPageViewSource', 'swetrix' );
		$provider = new SwetrixProvider(
			'https://stats.invalid', 'test', 'wiki.example',
			static fn () => [
				'data' => [
					[ 'value' => '/Analytics_article', 'pageviews' => 80 ],
					[ 'value' => '/Talk:Analytics_article', 'pageviews' => 10 ],
					[ 'value' => '/Missing_article', 'pageviews' => 10 ],
				],
				'meta' => [ 'total' => 3 ],
			]
		);
		$this->setService( 'TrendingAnalyticsProvider', $provider );
		$this->store = $this->getServiceContainer()->getService( 'TrendingAnalyticsStore' );
		$this->source = $provider->getKey();
		$this->today = gmdate( 'Ymd' );
		$this->insertPage( 'Analytics article', '[[Category:Analytics_test]]' );
		$this->page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle(
			Title::newFromText( 'Analytics article' )
		);
		DeferredUpdates::doUpdates();
	}

	public function test_wired_importer_resolves_only_eligible_titles(): void {
		/** @var AnalyticsImporter $importer */
		$importer = $this->getServiceContainer()->getService( 'TrendingAnalyticsImporter' );
		$this->assertSame( 1, $importer->importDay( gmdate( 'Y-m-d' ) ) );
		$this->assertSame( 80, $this->store->getPageCount( $this->source, $this->page->getId() ) );
	}

	public function test_reimport_replaces_counts_and_feeds_rankings_and_footer(): void {
		$page_id = $this->page->getId();
		$this->store->replaceDay( $this->source, $this->today, [ $page_id => 80 ] );
		$this->store->replaceDay( $this->source, $this->today, [ $page_id => 60 ] );
		$this->assertSame( 60, $this->store->getPageCount( $this->source, $page_id ) );
		$this->assertSame( 60, PageViewCounter::getPageViewCount( $this->page->getTitle() ) );

		$category = Title::newFromText( 'Category:Analytics_test' );
		foreach ( [ TrendingQuery::PERIOD_WEEK, TrendingQuery::PERIOD_ALL ] as $period ) {
			$pages = TrendingQuery::getTopPagesInCategory( $category, 6, $period );
			$this->assertCount( 1, $pages );
			$this->assertSame( 60, $pages[0]['count'] );
			$this->assertTrue( $this->page->getTitle()->equals( $pages[0]['title'] ) );
		}
	}

	public function test_source_counts_are_isolated(): void {
		$page_id = $this->page->getId();
		$this->store->replaceDay( $this->source, $this->today, [ $page_id => 60 ] );
		$this->store->replaceDay( 'other-source', $this->today, [ $page_id => 999 ] );
		$this->assertSame( 60, PageViewCounter::getPageViewCount( $this->page->getTitle() ) );
	}

	public function test_empty_report_clears_counts_and_records_success(): void {
		$page_id = $this->page->getId();
		$this->store->replaceDay( $this->source, $this->today, [ $page_id => 60 ] );
		$this->store->replaceDay( $this->source, $this->today, [] );
		$this->assertSame( 0, $this->store->getPageCount( $this->source, $page_id ) );
		$this->assertSame( $this->today, $this->store->getLatestDate( $this->source ) );
	}

	public function test_outage_preserves_last_successful_weekly_window(): void {
		$old_date = gmdate( 'Ymd', time() - 10 * 86400 );
		$this->store->replaceDay( $this->source, $old_date, [ $this->page->getId() => 75 ] );
		$pages = TrendingQuery::getTopPagesInCategory(
			Title::newFromText( 'Category:Analytics_test' ), 6, TrendingQuery::PERIOD_WEEK
		);
		$this->assertCount( 1, $pages );
		$this->assertSame( 75, $pages[0]['count'] );
	}

	public function test_analytics_mode_disables_origin_increments(): void {
		PageViewCounter::persistIncrement( $this->page->getId() );
		$this->assertFalse( PageViewCounter::shouldCountPageView( $this->page, $this->getTestUser()->getUser() ) );
		$row = $this->getDb()->newSelectQueryBuilder()->select( 'tp_count' )->from( 'trending_pageview' )
			->where( [ 'tp_page_id' => $this->page->getId() ] )->caller( __METHOD__ )->fetchRow();
		$this->assertFalse( $row );
	}

	public function test_explicit_hits_source_reads_legacy_counts_without_analytics(): void {
		$page_id = $this->page->getId();
		$this->store->replaceDay( $this->source, $this->today, [ $page_id => 999 ] );
		$this->getDb()->insert( 'trending_pageview', [
			'tp_page_id' => $page_id, 'tp_count' => 1, 'tp_updated' => $this->getDb()->timestamp(),
		], __METHOD__ );
		$this->getDb()->insert( 'trending_pageview_daily', [
			'tpd_page_id' => $page_id, 'tpd_date' => $this->today, 'tpd_count' => 1,
		], __METHOD__ );
		$this->overrideConfigValue( 'TrendingPageViewSource', 'hits' );
		$this->assertTrue( PageViewCounter::usesOriginHits() );
		$this->assertTrue( PageViewCounter::shouldCountPageView( $this->page, $this->getTestUser()->getUser() ) );
		$this->assertSame( 1, PageViewCounter::getPageViewCount( $this->page->getTitle() ) );
		$this->assertSame( [], TrendingQuery::getTopPagesInCategory(
			Title::newFromText( 'Category:Analytics_test' ), 6, TrendingQuery::PERIOD_WEEK
		) );
	}
}
