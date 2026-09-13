<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\Context\IContextSource;
use MediaWiki\Title\Title;
use Skin;
use SkinTemplate;

/**
 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinAddFooterLinks
 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
 */
class PageViewFooterItem {
	private const ITEM_ID = 'trending-viewcount';

	/**
	 * footer-info via SkinComponentFooter (MW 1.35-1.46)
	 *
	 * @param Skin $skin
	 * @param string $key
	 * @param array &$footerItems
	 */
	public static function addToFooterInfoLinks( Skin $skin, string $key, array &$footerItems ): void {
		if ( $key !== 'info' ) {
			return;
		}

		$html = self::buildHtml( $skin );

		if ( $html !== null ) {
			$footerItems[self::ITEM_ID] = $html;
		}
	}

	/**
	 * footer-info portlet (MW 1.47+)
	 *
	 * @param SkinTemplate $skinTemplate
	 * @param array &$links
	 */
	public static function addToFooterInfoPortlet( SkinTemplate $skinTemplate, array &$links ): void {
		if ( !isset( $links['footer-info'] ) ) {
			return;
		}

		$html = self::buildHtml( $skinTemplate );

		if ( $html === null ) {
			return;
		}

		$links['footer-info'][self::ITEM_ID] = [
			'html' => $html,
		];
	}

	private static function buildHtml( Skin $skin ): ?string {
		$context = $skin->getContext();
		$title = self::getRelevantTitle( $skin );

		if ( !$title instanceof Title || !$title->exists() || !$title->isContentPage() ) {
			return null;
		}

		return self::buildMessageText( $context, $title );
	}

	private static function getRelevantTitle( Skin $skin ): ?Title {
		if ( method_exists( $skin, 'getRelevantTitle' ) ) {
			return $skin->getRelevantTitle();
		}

		return $skin->getTitle();
	}

	private static function buildMessageText( IContextSource $context, Title $title ): ?string {
		$viewcount = PageViewCounter::getDisplayCount( $title, $context );

		if ( $viewcount <= 0 ) {
			return null;
		}

		return $context->msg( 'trending-viewcount' )
			->numParams( $viewcount )
			->parse();
	}
}
