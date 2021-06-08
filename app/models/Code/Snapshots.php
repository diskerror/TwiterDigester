<?php

namespace Code;

use Phalcon\Config;
use Structure\Snapshot;

final class Snapshots
{
	private function __construct() { }

	/**
	 * Grab and save the current state of data.
	 *
	 * @param Config $config
	 *
	 * @return array
	 */
	public static function make(Config $config) : int
	{
		$snap = new Snapshot([
			'id_'      => time(),
			'track'    => (array)$config->twitter->track,
			'tagCloud' => Tally\TagCloud::getHashtags($config->word_stats),
			'summary'  => Summary::get($config->word_stats),
		]);
		(new \Resource\Snapshots())->getClient()->insertOne($snap);
		return $snap->id_;
	}
}