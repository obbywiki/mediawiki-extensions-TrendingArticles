<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Trending\Analytics\AnalyticsImporter;
use MediaWiki\Extension\Trending\Analytics\AnalyticsStore;
use MediaWiki\Extension\Trending\Analytics\PageViewProvider;
use MediaWiki\Extension\Trending\Analytics\SwetrixProvider;
use MediaWiki\Extension\Trending\ExtensionConfig;
use MediaWiki\Title\Title;

return [
	'TrendingAnalyticsStore' => static function ( MediaWiki\MediaWikiServices $services ): AnalyticsStore {
		return new AnalyticsStore( $services->getConnectionProvider() );
	},
	'TrendingAnalyticsProvider' => static function ( MediaWiki\MediaWikiServices $services ): PageViewProvider {
		$config = $services->getMainConfig();
		if ( $config->get( 'TrendingPageViewSource' ) !== 'swetrix' ) {
			throw new RuntimeException( 'Unknown TrendingPageViewSource; configure a provider service.' );
		}

		$settings = $config->get( 'TrendingSwetrix' );
		$apiKey = $settings['apiKey'] ?? '';
		if ( !is_string( $apiKey ) || $apiKey === '' ) {
			throw new RuntimeException( 'Configure a server-side Swetrix statistics API key.' );
		}

		$http = $services->getHttpRequestFactory();
		return new SwetrixProvider(
			$settings['apiBase'] ?? '',
			$settings['projectId'] ?? '',
			$settings['hostname'] ?? '',
			static function ( string $url ) use ( $http, $apiKey ): array {
				$request = $http->create( $url, [ 'method' => 'GET', 'timeout' => 30, 'followRedirects' => false ], 'TrendingAnalyticsProvider' );
				$request->setHeader( 'X-Api-Key', $apiKey );
				$status = $request->execute();
				if ( !$status->isOK() || $request->getStatus() !== 200 ) {
					throw new RuntimeException( 'Swetrix statistics request failed. Check connectivity and API access.' );
				}

				$response = json_decode( $request->getContent(), true, 512, JSON_THROW_ON_ERROR );
				if ( !is_array( $response ) ) {
					throw new RuntimeException( 'Invalid Swetrix statistics response.' );
				}

				return $response;
			}
		);
	},
	'TrendingAnalyticsImporter' => static function ( MediaWiki\MediaWikiServices $services ): AnalyticsImporter {
		return new AnalyticsImporter(
			$services->getService( 'TrendingAnalyticsProvider' ),
			$services->getService( 'TrendingAnalyticsStore' ),
			static function ( string $name ): ?int {
				$title = Title::newFromText( $name );
				if ( !$title || $title->isExternal() || !$title->isContentPage() ||
					$title->getFragment() !== '' || !$title->exists() || $title->isRedirect()
				) {
					return null;
				}
				return (int)$title->getArticleID();
			}
		);
	},
	ExtensionConfig::SERVICE_NAME => static function ( MediaWiki\MediaWikiServices $services ): ExtensionConfig {
		return new ExtensionConfig(
			new ServiceOptions( ExtensionConfig::OPTIONS, $services->getMainConfig() )
		);
	},
];
