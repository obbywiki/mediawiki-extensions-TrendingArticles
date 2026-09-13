<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\Html\TemplateParser;
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

			$item = [
				'url' => $title->getLinkURL(),
				'title' => self::resolveTitleText( $title, $page_media['display_title'] ?? null ),
			];

			$shortdesc = $page_media['shortdesc'] ?? '';
			if ( is_string( $shortdesc ) && $shortdesc !== '' ) {
				$item['has_shortdesc'] = true;
				$item['shortdesc'] = $shortdesc;
			}

			$thumbnail = $page_media['thumbnail'] ?? null;
			if ( is_array( $thumbnail ) ) {
				$item['thumbnail'] = [
					'source' => (string)$thumbnail['source'],
					'width' => (int)$thumbnail['width'],
					'height' => (int)$thumbnail['height'],
				];
			}

			$items[] = $item;
		}

		return self::getTemplateParser()->processTemplate( 'TrendingGrid', [
			'heading' => $out->msg( 'trending-category-trending-heading' )->text(),
			'items' => $items,
		] );
	}

	private static function getTemplateParser(): TemplateParser {
		static $template_parser = null;
		if ( $template_parser === null ) {
			$template_parser = new TemplateParser( dirname( __DIR__ ) . '/templates' );
		}
		return $template_parser;
	}

	private static function resolveTitleText( Title $title, ?string $display_title ): string {
		$text = $title->getText();

		if ( is_string( $display_title ) && $display_title !== '' ) {
			$stripped = Sanitizer::stripAllTags( $display_title );
			if ( $stripped !== '' ) {
				$text = $stripped;
			}
		}

		return $text;
	}
}
