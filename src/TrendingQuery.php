<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\SelectQueryBuilder;

class TrendingQuery {
	public const PERIOD_ALL = 'all';
	public const PERIOD_WEEK = 'week';
	public const MIN_WEEKLY_VIEWS = 50;

	/**
	 * @return list<array{title:Title,count:int}>
	 */
	public static function getTopPagesInCategory( Title $category, int $limit, string $period = self::PERIOD_ALL ): array {
		if ( !$category->inNamespace( NS_CATEGORY ) || $limit <= 0 ) {
			return [];
		}

		$services = MediaWikiServices::getInstance();
		$db_provider = $services->getConnectionProvider();
		$dbr = $db_provider->getReplicaDatabase();

		$contentNamespaces = $services->getNamespaceInfo()->getContentNamespaces();
		$is_week = $period === self::PERIOD_WEEK;

		if ( $is_week ) {
			$countJoin = [ 'trending_pageview_daily', null, 'tpd_page_id = page_id' ];
		} else {
			$countJoin = [ 'trending_pageview', null, 'tp_page_id = page_id' ];
		}

		$select = [
			'page_id',
			'page_namespace',
			'page_title'
		];

		if ( $is_week ) {
			$select['view_count'] = 'SUM(tpd_count)';
		} else {
			$select['view_count'] = 'tp_count';
		}

		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( $select )
			->from( 'categorylinks' )
			->join( 'page', null, 'page_id = cl_from' )
			->join( $countJoin[0], $countJoin[1], $countJoin[2] )
			->where( [
				'cl_type' => 'page',
				'page_is_redirect' => 0,
			] )
			->andWhere( $dbr->expr( 'page_namespace', '=', $contentNamespaces ) );

		if ( $is_week ) {
			$cutoff_date = substr(
				wfTimestamp( TS_MW, time() - PageViewCounter::DAILY_RETENTION_DAYS * 86400 ),
				0,
				8
			);
			$queryBuilder
				->andWhere( $dbr->expr( 'tpd_date', '>=', $cutoff_date ) )
				->groupBy( [ 'page_id', 'page_namespace', 'page_title' ], __METHOD__ )
				->having( 'SUM(tpd_count) >= ' . self::MIN_WEEKLY_VIEWS );
		}

		$queryBuilder
			->orderBy( 'view_count', SelectQueryBuilder::SORT_DESC )
			->limit( $limit )
			->caller( __METHOD__ );

		if ( $dbr->fieldExists( 'categorylinks', 'cl_target_id', __METHOD__ ) ) {
			$queryBuilder
				->join( 'linktarget', null, 'cl_target_id = lt_id' )
				->andWhere( [
					'lt_title' => $category->getDBkey(),
					'lt_namespace' => NS_CATEGORY,
				] );
		} else {
			$queryBuilder->andWhere( [ 'cl_to' => $category->getDBkey() ] );
		}

		$res = $queryBuilder->fetchResultSet();

		$pages = [];
		foreach ( $res as $row ) {
			$title = Title::makeTitleSafe( (int)$row->page_namespace, (string)$row->page_title );

			if ( !$title || !$title->isContentPage() ) {
				continue;
			}

			$view_count = (int)$row->view_count;
			if ( $is_week && $view_count < self::MIN_WEEKLY_VIEWS ) {
				continue;
			}

			$pages[] = [
				'title' => $title,
				'count' => $view_count,
			];
		}

		return $pages;
	}
}
