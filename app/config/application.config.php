<?php
/**
 * If you need an environment-specific system or application configuration,
 * there is an example in the documentation
 *
 * @see https://docs.zendframework.com/tutorials/advanced-config/#environment-specific-system-configuration
 * @see https://docs.zendframework.com/tutorials/advanced-config/#environment-specific-application-configuration
 */
return [
	'APPLICATION_NAME' => 'digester';

    'user_config_name'         => '._APPLICATION_NAME_.php',

    // Whether or not to enable a configuration cache.
    // If enabled, the merged configuration will be cached and used in
    // subsequent requests.
    'config_cache_enabled'     => true,

    // The key used to create the configuration cache file name.
//    'config_cache_key'         => 'application.config.cache',

    // Whether or not to enable a module class map cache.
    // If enabled, creates a module class map cache which will be used
    // by in future requests, to reduce the autoloading process.
//    'module_map_cache_enabled' => false,

    // The key used to create the class map cache file name.
//    'module_map_cache_key'     => 'application.module.cache',

    // The path in which to cache merged configuration.
//    'cache_dir'                => 'data/cache/',

    // Initial configuration with which to seed the ServiceManager.
    // Should be compatible with Zend\ServiceManager\Config.
    // 'service_manager' => [],

	'mongodb' => [
		'host'        => 'mongodb://localhost:27017',
		'database'    => '_APPLICATION_NAME_',

		//  The list of active collections. Listing the names here prevents typos from
		//      creating new and mysterious collections.
		//  The keys are the collection names and the values are a list of the index definitions.
		'collections' => [
			'tweet'    => [
				['keys' => ['created_at' => 1], 'options' => ['expireAfterSeconds' => 60 * 20]],
				['keys' => ['entities.hashtags.0.text' => 1]],
				['keys' => ['text' => 1]],
			],
			'message'  => [
				['keys' => ['created' => 1], 'options' => ['expireAfterSeconds' => 60 * 60]],
			],
			'snapshot' => [
				//	_id is automatically indexed
			],

		],
	],

	'wordStats' => [
		'quantity' => 100,    //	return the top X items
		'window'   => 300,    //	summarize the last X seconds
		'stop'     => [],     //	stop words
	],

	'twitter' => [
		'url' => 'https://stream.twitter.com/1.1/',

		'auth' => [
			'consumer_key'       => '',
			'consumer_secret'    => '',
			'oauth_token'        => '',
			'oauth_token_secret' => '',
		],

		'track' => [
			'chuckschumer',
			'constitution',
			'democrat',
			'donald',
			'donaldtrump',
			'green',
			'kevinmccarthy',
			'libertarian',
			'mccarthy',
			'mcconnell',
			'mikepence',
			'mitch',
			'mitchmcconnell',
			'nancypelosi',
			'pelosi',
			'pence',
			'potus',
			'republican',
			'schumer',
			'scotus',
			'socialdemocrat',
			'trump',
		],
	],

	'process' => [
		'name'    => '_APPLICATION_NAME_',
		'path'    => '/var/run/_APPLICATION_NAME_',
		'procDir' => '/proc/'    //	location of actual PID
	],

	'caches' => [
		'index' => [
			'front' => [
				'lifetime' => 600,    //	ten minutes
				'adapter'  => 'data',
			],
			'back'  => [
				'cacheDir' => '/dev/shm/_APPLICATION_NAME_/',
				'prefix'   => 'index',
				'frontend' => null,
				'adapter'  => 'file',
			],
		],

		'tag_cloud' => [
			'front' => [
				'lifetime' => 2,    //	two seconds
				'adapter'  => 'data',
			],
			'back'  => [
				'cacheDir' => '/dev/shm/_APPLICATION_NAME_/',
				'prefix'   => 'tag_cloud',
				'frontend' => null,
				'adapter'  => 'file',
			],
		],

		'summary' => [
			'front' => [
				'lifetime' => 6,    //	six seconds
				'adapter'  => 'data',
			],
			'back'  => [
				'cacheDir' => '/dev/shm/_APPLICATION_NAME_/',
				'prefix'   => 'summary',
				'frontend' => null,
				'adapter'  => 'file',
			],
		],
	],
];