<?php

namespace MediaWiki\Extension\Trending\Analytics;

use InvalidArgumentException;
use Wikimedia\Rdbms\IConnectionProvider;

/** Imported history is separate from origin counters and is retained for lifetime totals. */
class AnalyticsStore {

	public function __construct( private readonly IConnectionProvider $connections ) {
	}

	/**
	 * Replaces a complete snapshot, including removing pages corrected to zero.
	 * The marker also records successfully imported empty days.
	 *
	 * @param string $source Provider identity
	 * @param string $date YYYYMMDD UTC
	 * @param array<int,int> $counts Page IDs to pageview totals
	 */
	public function replaceDay( string $source, string $date, array $counts ): void {
		foreach ( $counts as $id => $count ) {
			if ( !is_int( $id ) || $id <= 0 || !is_int( $count ) || $count < 0 ) {
				throw new InvalidArgumentException( 'Invalid analytics page count.' );
			}
		}

		$dbw = $this->connections->getPrimaryDatabase();
		$fname = __METHOD__;
		$dbw->doAtomicSection( $fname, static function () use ( $dbw, $source, $date, $counts, $fname ) {
			// update the marker first to serialize writers for the same source/day
			$dbw->upsert( 'trending_analytics_import', [ 'tai_source' => $source, 'tai_date' => $date, 'tai_updated' => $dbw->timestamp() ], [ [ 'tai_source', 'tai_date' ] ], [ 'tai_updated' => $dbw->timestamp() ], $fname );
			$dbw->delete( 'trending_analytics_daily', [ 'tad_source' => $source, 'tad_date' => $date ], $fname );

			$rows = [];
			foreach ( $counts as $id => $count ) {
				$rows[] = [
					'tad_source' => $source, 'tad_date' => $date,
					'tad_page_id' => $id, 'tad_count' => $count,
				];
			}

			foreach ( array_chunk( $rows, 500 ) as $chunk ) {
				$dbw->insert( 'trending_analytics_daily', $chunk, $fname );
			}
		} );
	}

	/** @return string|null YYYYMMDD of the latest successful snapshot */
	public function getLatestDate( string $source ): ?string {
		$value = $this->connections->getReplicaDatabase()->newSelectQueryBuilder()
			->select( 'MAX(tai_date)' )->from( 'trending_analytics_import' )
			->where( [ 'tai_source' => $source ] )->caller( __METHOD__ )->fetchField();
		return $value ? (string)$value : null;
	}

	/** @return int Views over the imported history, excluding origin hits */
	public function getPageCount( string $source, int $pageId ): int {
		return (int)$this->connections->getReplicaDatabase()->newSelectQueryBuilder()
			->select( 'SUM(tad_count)' )->from( 'trending_analytics_daily' )
			->where( [ 'tad_source' => $source, 'tad_page_id' => $pageId ] )
			->caller( __METHOD__ )->fetchField();
	}
}
