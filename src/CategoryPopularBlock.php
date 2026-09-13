<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;

class CategoryPopularBlock {
	private const STYLE_MODULE = 'ext.trending.grid.styles';

	private static bool $injected = false;
	private static bool $styles_registered = false;

	public static function wasInjected(): bool {
		return self::$injected;
	}

	public static function registerStyles( OutputPage $out ): void {
		$title = $out->getTitle();
		if ( !( $title instanceof Title ) || !$title->inNamespace( NS_CATEGORY ) || self::$styles_registered ) {
			return;
		}

		$out->addModuleStyles( [ self::STYLE_MODULE ] );
		self::$styles_registered = true;
	}

	public static function inject( Title $category, OutputPage $out ): void {
		if ( self::$injected ) {
			return;
		}

		$html = self::render( $category, $out );
		if ( $html === '' ) {
			return;
		}

		self::registerStyles( $out );
		$out->addHTML( $html );
		self::$injected = true;
	}

	public static function render( Title $category, OutputPage $out ): string {
		return CategoryTrendingGridBlock::render( $category, $out );
	}
}
