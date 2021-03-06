<?php

namespace Logic;

use Ds\Set as DsSet;
use Ds\Vector;
use Exception;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\WriteConcern;
use Resource\Messages;
use Resource\Tallies;
use Resource\Tweets;
use Resource\TwitterClient\Stream;
use Structure\Config;
use Structure\Tally;
use Structure\Tweet;

final class ConsumeTweets
{
	//	512 meg memory limit
	const MEMORY_LIMIT = 512 * 1024 * 1024;
	const INSERT_COUNT = 16;

	private function __construct() { }

	/**
	 * Open and save a stream of tweets.
	 *
	 * @param Config $config
	 */
	public static function exec(Config $config)
	{
		ini_set('memory_limit', self::MEMORY_LIMIT);

		$pidHandler = new PidHandler($config->process);

		$logger = new LoggerFactory(BASE_PATH . '/consume.log');
//		$logger = new LoggerFactory('php://stderr');

//		$sh = new StemHandler();

		try {
			$stream         = new Stream($config->twitter->auth);
			$tweetsClient   = new Tweets($config->mongo_db);
			$talliesClient  = new Tallies($config->mongo_db);
			$messagesClient = new Messages($config->mongo_db);

			//	Send request to start a filtered stream.
			$stream->filter([
				'track'          => implode(',', $config->twitter->track->toArray()),
				'language'       => 'en',
				'stall_warnings' => true,
			]);

			//	Set PID file to indicate whether we should keep running.
			if ($pidHandler->setFile() === false) {
				$logger->error('Process "' . $config->process->path . $config->process->name . '" is already running or not stopped properly');
				return;
			}

			$insertOptions = ['writeConcern' => new WriteConcern(0, 100, false)];

			$stopWords = new Vector($config->word_stats->stop->toArray());

			//	Announce that we're running.
			$logger->info('Started capturing tweets.');

			$tweet  = new Tweet();
			$tweets = new Vector();
			$tweets->allocate(self::INSERT_COUNT);
			$tally = new Tally();

			while ($pidHandler->exists() && !$stream->isEOF()) {
				$tweets->clear();
				$tally->assign(null);

				for ($i = 0; $i < self::INSERT_COUNT; ++$i) {
					//	get tweet
					$packet = $stream->read();

					//	Ignore nulls.
					if (null === $packet) {
						continue;
					}

					//	Log bad data.
					if (!is_array($packet)) {
						$logger->info('bad packet' . PHP_EOL . var_export($packet, true));
						continue;
					}

					//	Save Twitter messages.
					if ($stream::isMessage($packet)) {
						$packet['created'] = new UTCDateTime(time() * 1000);
						$messagesClient->insertOne($packet);
						continue;
					}

					//	Filter. Tweet structure accepts only part of the packet.
					$tweet->assign($packet);

					//	If tweet is not in english then skip it.
					if ($tweet->lang !== 'en') {
						$logger->info('packet lang not en');
						continue;
					}

					// Check for and use extended tweet if it exists.
					if (strlen($tweet->extended_tweet->full_text) > strlen($tweet->text)) {
						$tweet->text = $tweet->extended_tweet->full_text;
						unset($tweet->extended_tweet->full_text);

						$tweet->entities = $tweet->extended_tweet->entities;
						unset($tweet->extended_tweet->entities);
					}

					//	Pre calculate tally for INSERT_COUNT of tweets.
					$hashtagSet = new DsSet();

					//	Make sure we have only one of a hashtag per tweet for uniqueHashtags.
					foreach ($tweet->entities->hashtags as $hashtag) {
						$htext = str_split($hashtag->text);
						foreach ($htext as $t) {
							if ($t & chr(0x80)) {
								continue 2;    //	skip hashtag if it contains a non-ASCII byte
							}
						}

						$hashtagSet->add($hashtag->text);
						$tally->allHashtags->doTally($hashtag->text);
					}

					//	Count unique hashtags for this tweet.
					foreach ($hashtagSet->toArray() as $uniqueHashtag) {
						$tally->uniqueHashtags->doTally($uniqueHashtag);
					}

					//	Tally the words in the text.
					$split = preg_split('/[^a-zA-Z0-9_\']/', $tweet->text, null, PREG_SPLIT_NO_EMPTY);
					foreach ($split as $s) {
						if (strlen($s) > 2 && !$stopWords->contains(strtolower($s))) {
							$tally->textWords->doTally($s);
						}
					}

					//	Tally user mentions.
					foreach ($tweet->entities->user_mentions as $userMention) {
						$tally->userMentions->doTally($userMention->screen_name);
					}

//					$words = [];
//
//					//	build the two stem lists
//					$split = preg_split('/[^a-zA-Z0-9]/', $text, null, PREG_SPLIT_NO_EMPTY);
//					foreach ($split as $s) {
//						$words[] = $sh->get($s);
//					}
//
//					//	build stem pairs
//					$last = '';
//					foreach ($tweet->words as $w) {
//						$tweet->pairs[] = $last . $w;
//						$last           = $w;
//					}
//					$tweet->pairs[] = $last;

					$tweets->push($tweet->bsonSerialize());
				}

				try {
					//	convert to Mongo compatible object and insert
					$tweetsClient->insertMany($tweets->toArray(), $insertOptions);
					$talliesClient->insertOne($tally->bsonSerialize(), $insertOptions);
				}
				catch (Exception $e) {
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
		catch (Exception $e) {
			$logger->emergency((string) $e);
		}
	}

}
