<?php

namespace MediaWiki\Extension\Trending;

use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use Skin;

class CategoryPopularBlock {
	private const STYLE_MODULE = 'ext.trending.citizenGrid.styles';

	private static bool $injected = false;
	private static bool $styles_registered = false;

	public static function wasInjected(): bool {
		return self::$injected;
	}

	public static function categoryHasIntroText( Title $category ): bool {
		if ( !$category->exists() ) {
			return false;
		}

		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $category );
		$content = $wikiPage->getContent( RevisionRecord::RAW );
		if ( !$content ) {
			return false;
		}

		return trim( $content->getText() ) !== '';
	}

	public static function registerStyles( OutputPage $out, ?Skin $skin ): void {
		$title = $out->getTitle();
		if ( !( $title instanceof Title ) || !self::categoryHasIntroText( $title ) || !self::usesGridRenderer( $skin ) || self::$styles_registered ) {
			return;
		}

		$out->addModuleStyles( [ self::STYLE_MODULE ] );

		// fallback
		$css = self::getGridStyles();

		if ( $css !== '' ) {
			$out->addInlineStyle( $css );
		}

		self::$styles_registered = true;
	}

	public static function inject( Title $category, OutputPage $out, ?Skin $skin = null ): void {
		if ( self::$injected ) {
			return;
		}

		$html = self::render( $category, $out, $skin );
		if ( $html === '' ) {
			return;
		}

		$out->addHTML( $html );
		self::$injected = true;
	}

	public static function render( Title $category, OutputPage $out, ?Skin $skin = null ): string {
		if ( self::usesGridRenderer( $skin ) && !self::categoryHasIntroText( $category ) ) {
			return '';
		}

		if ( self::usesGridRenderer( $skin ) ) {
			return CategoryTrendingGridBlock::render( $category, $out );
		}

		return CategoryPopularListBlock::render( $category, $out );
	}

	private static function usesGridRenderer( ?Skin $skin ): bool {
		return $skin !== null
			&& SkinHelper::isCitizen( $skin )
			&& ExtensionRegistry::getInstance()->isLoaded( 'PageImages' );
	}

	private static function getGridStyles(): string {
		static $css = null;
		if ( $css !== null ) {
			return $css;
		}

		$path = dirname( __DIR__ ) . '/resources/ext.trending.citizenGrid.styles.css';
		if ( !is_readable( $path ) ) {
			$css = '';
			return $css;
		}

		$contents = file_get_contents( $path );
		$css = is_string( $contents ) ? $contents : '';

		return $css;
	}
}
