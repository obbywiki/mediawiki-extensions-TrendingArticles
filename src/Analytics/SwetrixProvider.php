<?php

namespace MediaWiki\Extension\Trending\Analytics;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

// reads the stats API v2 and the /Page_Name paths emitted by Extension:Swetrix
class SwetrixProvider implements PageViewProvider {

	private const PAGE_SIZE = 100;
	private const MAX_PAGES = 10000;

	/**
	 * @param string $apiBase API root, before /v2 (CE: https://analytics.example/backend)
	 * @param string $projectId Project dedicated to this wiki, or filtered by $hostname
	 * @param string $hostname Exact tracked hostname
	 * @param Closure $request Receives a URL and returns a decoded JSON response
	 */
	public function __construct(
		private readonly string $apiBase,
		private readonly string $projectId,
		private readonly string $hostname,
		private readonly Closure $request
	) {
		if ( !filter_var( $apiBase, FILTER_VALIDATE_URL ) || !in_array( parse_url( $apiBase, PHP_URL_SCHEME ), [ 'https', 'http' ], true ) || parse_url( $apiBase, PHP_URL_QUERY ) !== null || parse_url( $apiBase, PHP_URL_FRAGMENT ) !== null || $projectId === '' || $hostname === '' ) {
			throw new InvalidArgumentException( 'Configure the Swetrix API base, project ID, and hostname.' );
		}
	}

	/** @inheritDoc */
	public function getKey(): string {
		return hash( 'sha256', 'swetrix:' . rtrim( $this->apiBase, '/' ) . ':' . $this->projectId . ':' . $this->hostname );
	}

	/** @inheritDoc */
	public function fetchDailyViews( string $date ): array {
		$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		if ( !$day || $day->format( 'Y-m-d' ) !== $date ) {
			throw new InvalidArgumentException( 'Expected a YYYY-MM-DD date.' );
		}

		$result = [];
		$seen = [];
		$total = null;
		$offset = 0;
		for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
			$query = [
				'dimension' => 'page',
				'metrics' => 'pageviews',
				'from' => $date,
				'to' => $date,
				'timezone' => 'Etc/GMT',
				'limit' => self::PAGE_SIZE,
				'offset' => $offset,
				'sort' => 'value:asc',
				'filters' => json_encode( [
					[ 'dimension' => 'host', 'operator' => 'is', 'value' => $this->hostname ],
					[ 'dimension' => 'page_property', 'key' => 'action', 'operator' => 'is', 'value' => 'view' ],
				], JSON_THROW_ON_ERROR ),
			];
			$url = rtrim( $this->apiBase, '/' ) . '/v2/projects/' . rawurlencode( $this->projectId ) .
				'/traffic/breakdown?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
			$response = ( $this->request )( $url );
			$rows = $response['data'] ?? null;
			$rowTotal = $response['meta']['total'] ?? null;

			if ( !is_array( $rows ) || !array_is_list( $rows ) || !is_int( $rowTotal ) || $rowTotal < 0 || ( $total !== null && $total !== $rowTotal ) || count( $rows ) > self::PAGE_SIZE ) {
				throw new RuntimeException( 'Invalid or changing Swetrix pagination; snapshot was not imported.' );
			}

			$total = $rowTotal;
			foreach ( $rows as $row ) {
				$path = $row['value'] ?? null;
				$count = $row['pageviews'] ?? null;

				if ( !is_string( $path ) || !is_int( $count ) || $count < 0 || isset( $seen[$path] ) ) {
					throw new RuntimeException( 'Invalid or repeated Swetrix page row; snapshot was not imported.' );
				}

				$seen[$path] = true;

				// (!) Extension:Swetrix sends '/' + wgPageName, not wgArticlePath
				if ( str_starts_with( $path, '/' ) && !str_starts_with( $path, '//' ) ) {
					$title = str_replace( ' ', '_', substr( $path, 1 ) );
					if ( $title !== '' ) {
						$result[$title] = ( $result[$title] ?? 0 ) + $count;
					}
				}
			}

			$offset += count( $rows );
			if ( $offset === $total ) {
				return $result;
			}
			if ( !$rows || $offset > $total ) {
				throw new RuntimeException( 'Incomplete Swetrix report; snapshot was not imported.' );
			}
		}
		throw new RuntimeException( 'Swetrix pagination limit exceeded; snapshot was not imported.' );
	}
}
