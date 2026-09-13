<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\Config\ServiceOptions;

// this might not need to be an independent service in the future

class ExtensionConfig {
	public const SERVICE_NAME = 'TrendingConfig';

	public const CATEGORY_LIMIT = 'TrendingCategoryLimit';
	public const THUMB_SIZE = 'TrendingThumbSize';

	public const OPTIONS = [
		self::CATEGORY_LIMIT,
		self::THUMB_SIZE,
	];

	public function __construct( private readonly ServiceOptions $options ) {
		$this->options->assertRequiredOptions( self::OPTIONS );
	}

	public function getCategoryLimit(): int {
		return max( 1, (int)$this->options->get( self::CATEGORY_LIMIT ) );
	}

	public function getThumbSize(): int {
		return max( 50, (int)$this->options->get( self::THUMB_SIZE ) );
	}
}
