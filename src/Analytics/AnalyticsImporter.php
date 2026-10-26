<?php

namespace MediaWiki\Extension\Trending\Analytics;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

class AnalyticsImporter {

	/**
	 * @param PageViewProvider $provider
	 * @param AnalyticsStore $store
	 * @param Closure $resolveTitle Resolves a prefixed title to an eligible page ID, or null
	 */
	public function __construct(
		private readonly PageViewProvider $provider,
		private readonly AnalyticsStore $store,
		private readonly Closure $resolveTitle
	) {
	}

	/**
	 * Fetch and validate everything before replacing the stored day.
	 *
	 * @param string $date YYYY-MM-DD UTC, including today for provisional counts
	 * @return int Number of eligible pages imported
	 */
	public function importDay( string $date ): int {
		$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );
		if ( !$day || $day->format( 'Y-m-d' ) !== $date || $date > gmdate( 'Y-m-d' ) ) {
			throw new InvalidArgumentException( 'Expected a valid UTC date no later than today.' );
		}

		$counts = [];
		foreach ( $this->provider->fetchDailyViews( $date ) as $title => $count ) {
			if ( !is_int( $count ) || $count < 0 ) {
				throw new RuntimeException( 'Provider returned an invalid pageview count.' );
			}
			$id = ( $this->resolveTitle )( (string)$title );
			if ( $id !== null && $id > 0 ) {
				$counts[$id] = ( $counts[$id] ?? 0 ) + $count;
			}
		}

		$this->store->replaceDay( $this->provider->getKey(), $day->format( 'Ymd' ), $counts );
		return count( $counts );
	}
}
