<?php

namespace MediaWiki\Extension\Trending\Analytics;

/** A complete daily snapshot of pageviews, never visits or unique visitors. */
interface PageViewProvider {

	/** @return string a stable identity including the backend, project, and wiki */
	public function getKey(): string;

	/**
	 * Returns all pages for one UTC calendar day. Throws on incomplete or failed reports.
	 * An empty array means a successful report with zero views.
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array<string,int> MediaWiki prefixed DB keys mapped to pageview totals
	 * @throws RuntimeException if the report is incomplete or failed
	 */
	public function fetchDailyViews( string $date ): array;
}
