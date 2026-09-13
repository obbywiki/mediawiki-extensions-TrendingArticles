<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Title\Title;

class CategoryTrendingGridBlock {
	public static function render( Title $category, OutputPage $out ): string {
		$services = MediaWikiServices::getInstance();
		/** @var ExtensionConfig $config */
		$config = $services->getService( ExtensionConfig::SERVICE_NAME );
		$limit = $config->getCategoryLimit();
		$thumb_size = $config->getThumbSize();

		$pages = TrendingQuery::getTopPagesInCategory(
			$category,
			$limit,
			TrendingQuery::PERIOD_WEEK
		);
		if ( $pages === [] ) {
			return '';
		}

		$media = TrendingPageMedia::getForPages( $pages, $thumb_size );

		$items = [];
		foreach ( $pages as $entry ) {
			$title = $entry['title'];
			$page_id = $title->getArticleID();
			$page_media = $media[$page_id] ?? [];

			$thumb_html = self::renderThumb( $page_media['thumbnail'] ?? null );
			$body_parts = [ self::renderTitle( $title, $page_media['display_title'] ?? null ) ];

			$shortdesc = $page_media['shortdesc'] ?? '';
			if ( is_string( $shortdesc ) && $shortdesc !== '' ) {
				$body_parts[] = Html::element(
					'span',
					[ 'class' => 'cdx-card__text__description' ],
					$shortdesc
				);
			}

			$card_body = Html::rawElement(
				'span',
				[ 'class' => 'cdx-card__text' ],
				implode( '', $body_parts )
			);

			$items[] = Html::rawElement(
				'li',
				[ 'class' => 'trending-grid__item' ],
				Html::rawElement(
					'a',
					[
						'class' => 'cdx-card cdx-card--is-link trending-grid__card',
						'href' => $title->getLinkURL(),
					],
					$thumb_html . $card_body
				)
			);
		}

		$heading = $out->msg( 'trending-category-trending-heading' )->text();
		return Html::rawElement(
			'section',
			[
				'class' => 'trending-grid',
				'aria-labelledby' => 'trending-category-trending-heading',
			],
			Html::rawElement(
				'h2',
				[
					'id' => 'trending-category-trending-heading',
					'class' => 'trending-grid__heading',
				],
				$heading
			) .
			Html::rawElement(
				'ul',
				[
					'class' => 'trending-grid__list',
					'role' => 'list',
				],
				implode( '', $items )
			)
		);
	}

	/**
	 * @param array{source:string,width:int,height:int}|null $thumbnail
	 */
	private static function renderThumb( ?array $thumbnail ): string {
		if ( $thumbnail !== null ) {
			return Html::rawElement(
				'span',
				[ 'class' => 'trending-grid__media' ],
				Html::element( 'img', [
					'class' => 'trending-grid__image',
					'src' => (string)$thumbnail['source'],
					'width' => (string)(int)$thumbnail['width'],
					'height' => (string)(int)$thumbnail['height'],
					'alt' => '',
					'loading' => 'lazy',
					'decoding' => 'async',
				] )
			);
		}

		return Html::element(
			'span',
			[
				'class' => 'trending-grid__media trending-grid__media--placeholder',
				'aria-hidden' => 'true',
			],
			''
		);
	}

	private static function renderTitle( Title $title, ?string $display_title ): string {
		$text = $title->getText();

		if ( is_string( $display_title ) && $display_title !== '' ) {
			$stripped = Sanitizer::stripAllTags( $display_title );
			if ( $stripped !== '' ) {
				$text = $stripped;
			}
		}

		return Html::element(
			'span',
			[ 'class' => 'cdx-card__text__title' ],
			$text
		);
	}
}
