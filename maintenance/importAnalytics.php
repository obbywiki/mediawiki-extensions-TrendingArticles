<?php

namespace MediaWiki\Extension\Trending\Maintenance;

use DateTimeImmutable;
use DateTimeZone;
use MediaWiki\Maintenance\Maintenance;
use Throwable;

$installPath = getenv( 'MW_INSTALL_PATH' ) ?: __DIR__ . '/../../..';
require_once "$installPath/maintenance/Maintenance.php";

/** Should be run hourly from cron (supports historical backfills). */
class ImportAnalytics extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Import daily analytics snapshots for TrendingArticles.' );
		$this->addOption( 'from', 'First UTC day (YYYY-MM-DD), defaults to six days ago.', false, true );
		$this->addOption( 'to', 'Last UTC day (YYYY-MM-DD), defaults to today.', false, true );
		$this->requireExtension( 'TrendingArticles' );
	}

	public function execute() {
		$services = $this->getServiceContainer();
		if ( $services->getMainConfig()->get( 'TrendingPageViewSource' ) === 'hits' ) {
			$this->fatalError( 'Select an analytics source before running this importer.' );
		}

		$utc = new DateTimeZone( 'UTC' );
		$today = new DateTimeImmutable( 'today', $utc );
		$from = $this->getOption( 'from', $today->modify( '-6 days' )->format( 'Y-m-d' ) );
		$to = $this->getOption( 'to', $today->format( 'Y-m-d' ) );
		$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $from, $utc );
		$end = DateTimeImmutable::createFromFormat( '!Y-m-d', $to, $utc );

		if ( !$start || !$end || $start->format( 'Y-m-d' ) !== $from || $end->format( 'Y-m-d' ) !== $to || $start > $end || $end > $today ) {
			$this->fatalError( 'Use a valid UTC date range ending no later than today.' );
		}

		try {
			$importer = $services->getService( 'TrendingAnalyticsImporter' );
			for ( $day = $start; $day <= $end; $day = $day->modify( '+1 day' ) ) {
				$date = $day->format( 'Y-m-d' );
				$count = $importer->importDay( $date );
				$this->output( "$date: imported $count pages.\n" );
			}
		} catch ( Throwable $e ) {
			$this->fatalError( 'Analytics import failed: ' . $e->getMessage() );
		}
	}
}

$maintClass = ImportAnalytics::class;
require_once RUN_MAINTENANCE_IF_MAIN;
