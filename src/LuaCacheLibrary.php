<?php
/**
 * LuaCache
 * LuaCache Scribunto Lua Library
 *
 * @author  Robert Nix
 * @license MIT
 * @package LuaCache
 * @link    https://github.com/HydraWiki/LuaCache
 *
 */

namespace LuaCache;

use BagOStuff;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use MediaWiki\MediaWikiServices;
use Scribunto_LuaEngine;
use Scribunto_LuaError;
use Scribunto_LuaLibraryBase;

class LuaCacheLibrary extends Scribunto_LuaLibraryBase {
	private const CACHE_PREFIX = 'LuaCache';

	private BagOStuff $cache;

	public function __construct( Scribunto_LuaEngine $engine ) {
		parent::__construct( $engine );
		$this->cache = MediaWikiServices::getInstance()->getService( 'LuaCacheStore' );
	}

	/**
	 * Register the Lua extension with Scribunto
	 *
	 * @return array Lua package
	 */
	public function register(): array {
		// Register the binser package dependency
		$this->getEngine()->registerInterface(
			__DIR__ . '/binser.lua', []
		);

		// Register the LuaCache package
		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.ext.LuaCache.lua', [
				'get' => [ $this, 'get' ],
				'set' => [ $this, 'set' ],
				'getMulti' => [ $this, 'getMulti' ],
				'setMulti' => [ $this, 'setMulti' ],
				'delete' => [ $this, 'delete' ],
			]
		);
	}

	/**
	 * Get an item from the main object cache
	 *
	 * @param string $key Cache key
	 * @return array Lua result array containing false or the string value
	 * @throws LuaError
	 */
	public function get( string $key ): array {
		$this->checkType( 'get', 1, $key, 'string' );

		$cacheKey = $this->cache->makeKey( self::CACHE_PREFIX, $key );
		return [ $this->cache->get( $cacheKey ) ];
	}

	/**
	 * Set an item in the main object cache
	 *
	 * @param string $key Cache key
	 * @param string $value Cache value
	 * @param int|null $exptime Expiration time in seconds
	 * @return array Lua result array containing boolean success
	 * @throws LuaError
	 */
	public function set( string $key, string $value, ?int $exptime = 0 ): array {
		$this->checkType( 'set', 1, $key, 'string' );
		$this->checkType( 'set', 2, $value, 'string' );
		$this->checkTypeOptional( 'set', 3, $exptime, 'number', 0 );

		$cacheKey = $this->cache->makeKey( self::CACHE_PREFIX, $key );
		return [ $this->cache->set( $cacheKey, $value, $exptime ) ];
	}

	/**
	 * Get multiple items from the main object cache
	 *
	 * @param array $keys Array of string cache keys
	 * @return array Lua result array containing an array of results (false or string)
	 * @throws LuaError
	 * @throws Scribunto_LuaError
	 */
	public function getMulti( array $keys ): array {
		$this->checkType( 'getMulti', 1, $keys, 'table' );

		$cacheKeys = [];
		$cacheKeyToKey = [];
		foreach ( $keys as $key ) {
			$keyType = $this->getLuaType( $key );
			if ( $keyType !== 'string' ) {
				throw new Scribunto_LuaError(
					"bad argument 1 to getMulti (string expected for table key, get $keyType)"
				);
			}

			$cacheKey = $this->cache->makeKey( self::CACHE_PREFIX, $key );
			$cacheKeys[] = $cacheKey;
			$cacheKeyToKey[$cacheKey] = $key;
		}
		$cacheData = $this->cache->getMulti( $cacheKeys );

		// Rename the keys to match what was passed in
		$data = [];
		foreach ( $cacheData as $cacheKey => $value ) {
			if ( array_key_exists( $cacheKey, $cacheKeyToKey ) ) {
				$key = $cacheKeyToKey[$cacheKey];
				$data[$key] = $value;
			}
		}
		return [ $data ];
	}

	/**
	 * Set multiple items in the main object cache
	 *
	 * @param array $data Array of string keys => string values
	 * @param int|null $exptime Expiration time in seconds
	 * @return array Lua result array containing an array of boolean results
	 * @throws LuaError
	 * @throws Scribunto_LuaError
	 */
	public function setMulti( array $data, ?int $exptime = 0 ): array {
		$this->checkType( 'setMulti', 1, $data, 'table' );
		$this->checkTypeOptional( 'setMulti', 2, $exptime, 'number', 0 );

		$cacheData = [];
		foreach ( $data as $key => $value ) {
			$keyType = $this->getLuaType( $key );
			if ( $keyType !== 'string' ) {
				throw new Scribunto_LuaError(
					"bad argument 1 to setMulti (string expected for table key, get $keyType)"
				);
			}
			$valueType = $this->getLuaType( $value );
			if ( $valueType !== 'string' ) {
				throw new Scribunto_LuaError(
					"bad argument 1 to setMulti (string expected for table value, get $valueType)"
				);
			}

			$cacheKey = $this->cache->makeKey( self::CACHE_PREFIX, $key );
			$cacheData[$cacheKey] = $value;
		}
		return [ $this->cache->setMulti( $cacheData, $exptime ) ];
	}

	/**
	 * Set multiple items in the main object cache
	 *
	 * @param string $key Name of the item to delete
	 * @return array Lua result array containing a boolean result
	 * @throws LuaError
	 */
	public function delete( string $key ): array {
		$this->checkType( 'delete', 1, $key, 'string' );

		$cacheKey = $this->cache->makeKey( self::CACHE_PREFIX, $key );
		return [ $this->cache->delete( $cacheKey ) ];
	}
}
