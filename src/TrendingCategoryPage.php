<?php

namespace MediaWiki\Extension\Trending;

use CategoryPage;

class TrendingCategoryPage extends CategoryPage {
	public function closeShowCategory() {
		$out = $this->getContext()->getOutput();
		CategoryPopularBlock::inject( $this->getTitle(), $out );

		parent::closeShowCategory();
	}
}
