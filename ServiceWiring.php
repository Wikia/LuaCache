<?php

use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;

return [
	'LuaCacheStore' => static function ( MediaWikiServices $services ): BagOStuff {
		$mainConfig = $services->getMainConfig();
		$cacheType = $mainConfig->get( MainConfigNames::MainCacheType );
		return ObjectCache::getInstance( $cacheType );
	}
];
