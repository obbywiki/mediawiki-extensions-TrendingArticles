<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;

class CategoryPopularListBlock {
	public static function render( Title $category, OutputPage $out ): string {
		$services = MediaWikiServices::getInstance();

		/** @var ExtensionConfig $config */
		$config = $services->getService( ExtensionConfig::SERVICE_NAME );
		$limit = $config->getCategoryLimit();

		$pages = TrendingQuery::getTopPagesInCategory( $category, $limit );

		if ( $pages === [] ) {
			return '';
		}

		$show_counts = $config->getShowCounts();
		$link_renderer = $services->getLinkRenderer();
		$language = $out->getLanguage();

		$items = [];
		foreach ( $pages as $entry ) {
			$title = $entry['title'];
			$link = $link_renderer->makeLink( $title, $title->getPrefixedText() );
			if ( $show_counts ) {
				$count_msg = $language->formatNum( $entry['count'] );
				$link .= ' ' . Html::rawElement(
					'span',
					[ 'class' => 'trending-category-block__count' ],
					"($count_msg)"
				);
			}
			$items[] = Html::rawElement( 'li', [ 'class' => 'trending-category-block__item' ], $link );
		}

		$heading = $out->msg( 'trending-category-popular-heading' )->text();
		return Html::rawElement(
			'section',
			[
				'class' => 'trending-category-block',
				'aria-labelledby' => 'trending-category-popular-heading',
			],
			Html::rawElement(
				'h2',
				[
					'id' => 'trending-category-popular-heading',
					'class' => 'trending-category-block__heading',
				],
				$heading
			) .
			Html::rawElement(
				'ul',
				[ 'class' => 'trending-category-block__list' ],
				implode( '', $items )
			)
		);
	}
}
