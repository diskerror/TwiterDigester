<?php

namespace Code;

use Ds\Set;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\WriteConcern;
use Phalcon\Config;
use Resource\Messages;
use Resource\Tweets;
use Resource\TwitterClient\Stream;
use Structure\Tallies;
use Structure\TallyWords;
use Structure\Tweet;

final class ConsumeTweets
{
	const INSERT_COUNT = 10;

	private function __construct() { }

	/**
	 * Open and save a stream of tweets.
	 *
	 * @param Config     $tconfig
	 * @param PidHandler $pid_handler
	 *
	 */
	public static function exec(Config $config)
	{
		ini_set('memory_limit', 268435456);

		try {
			$stream     = new Stream($config->twitter->auth);
			$pidHandler = new PidHandler($config->process);

//			$logger = LoggerFactory::getFileLogger(APP_PATH . '/' . $config->process->name . '.log');
			$logger = LoggerFactory::getStreamLogger();

//			$sh = new StemHandler();

			$tweetsClient   = (new Tweets())->getClient();
			$talliesClient   = (new \Resource\Tallies())->getClient();
			$messagesClient = (new Messages())->getClient();


			//	Send request to start a filtered stream.
			$stream->filter([
				'track'          => implode(',', (array)$config->twitter->track),
				'language'       => 'en',
				'stall_warnings' => true,
			]);

			//	Set PID file to indicate whether we should keep running.
			$pidHandler->setFile();

			$insertOptions = ['writeConcern' => new WriteConcern(0, 100, false)];

			//	Announce that we're running.
			$logger->info('Started capturing tweets.');

			while ($pidHandler->exists() && !$stream->isEOF()) {
				$tweets  = [];
				$tallies = new Tallies();
				for ($i = 0; $i < self::INSERT_COUNT; ++$i) {
					//	get tweet
					try {
						$packet = $stream->read();
					}
					catch (\Exception $e) {
						$logger->info((string)$e);
						continue;
					}

					//	Ignore bad data.
					if (!is_array($packet)) {
						continue;
					}

					if ($stream::isMessage($packet)) {
						$packet['created'] = new UTCDateTime();
						$messagesClient->insertOne($packet);
						continue;
					}

					$tweet = new Tweet($packet);

					$uniqueWords = new Set();
					//	Make sure we have only one of a hashtag per tweet.
					foreach ($tweet->entities->hashtags as $hashtag) {
						$uniqueWords->add($hashtag->text);
					}

					foreach ($uniqueWords as $uniqeWord) {
						$tallies->uniqueHashtags->doTally($uniqeWord);
					}

					//	remove URLs from text
//				$text  = preg_replace('#https?:[^ ]+#', '', $tweet->text);
//				$words = [];
//
//				//	build the two stem lists
//				$split = preg_split('/[^a-zA-Z0-9]/', $text, null, PREG_SPLIT_NO_EMPTY);
//				foreach ($split as $s) {
//					$words[] = $sh->get($s);
//				}
//
//				//	build stem pairs
//				$last = '';
//				foreach ($tweet->words as $w) {
//					$tweet->pairs[] = $last . $w;
//					$last           = $w;
//				}
//				$tweet->pairs[] = $last;

					$tweets[] = $tweet->toArray();
				}

				try {
					//	convert to Mongo compatible object and insert
					$tweetsClient->insertMany($tweets, $insertOptions);
					$talliesClient->insertOne($tallies->toArray(), $insertOptions);
				}
				catch (\Exception $e) {
					$m = $e->getMessage();

					if (preg_match('/Authentication/i', $m)) {
						$logger->emergency('Mongo ' . $m);
					}
					else {
						//	ignore duplicates
						if (!preg_match('/duplicate.*key/i', $m)) {
							$logger->warning('Mongo ' . $m);
						}
					}
				}
			}

			$logger->info('Stopped capturing tweets.');
		}
		catch (\Exception $e) {
			$logger->emergency((string)$e);
		}

		$pidHandler->removeIfExists();
	}

}