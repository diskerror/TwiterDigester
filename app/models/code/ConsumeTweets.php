<?php

namespace Code;

use Ds\Set;
use Exception;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\WriteConcern;
use Phalcon\Config;
use Resource\Messages;
use Resource\Tallies;
use Resource\Tweets;
use Resource\TwitterClient\Stream;
use Structure\TallySet;
use Structure\Tweet;

final class ConsumeTweets
{
	//	512 meg memory limit
	const MEMORY_LIMIT = 512 * 1024 * 1024;
	const INSERT_COUNT = 16;

	private function __construct() { }

	public static function getOne(Config $config)
	{
		$stream = new Stream($config->auth);

		//	Send request to start a filtered stream.
		$stream->filter([
			'track'          => implode(',', (array) $config->track),
			'language'       => 'en',
			'stall_warnings' => true,
		]);

		return $stream->read();
	}

	/**
	 * Open and save a stream of tweets.
	 *
	 * @param Config $config
	 */
	public static function exec(Config $config)
	{
		ini_set('memory_limit', self::MEMORY_LIMIT);

		$pidHandler = new PidHandler($config->process);

//		$logger = LoggerFactory::getFileLogger(APP_PATH . '/' . $config->process->name . '.log');
		$logger = LoggerFactory::getStreamLogger();

//		$sh = new StemHandler();

		try {
			$stream         = new Stream($config->twitter->auth);
			$tweetsClient   = (new Tweets())->getClient();
			$talliesClient  = (new Tallies())->getClient();
			$messagesClient = (new Messages())->getClient();

			$insertOptions = ['writeConcern' => new WriteConcern(0, 100, false)];

			$stopWords = (array) $config->word_stats->stop;

			$tweet    = new Tweet();
			$tallySet = new TallySet();

			//	Send request to start a filtered stream.
			$stream->filter([
				'track'          => implode(',', (array) $config->twitter->track),
				'language'       => 'en',
				'stall_warnings' => true,
			]);

			//	Set PID file to indicate whether we should keep running.
			$pidHandler->setFile();

			//	Announce that we're running.
			$logger->info('Started capturing tweets.');

			while ($pidHandler->exists() && !$stream->isEOF()) {
				$tweets = [];
				$tallySet->assign(null);
				for ($i = 0; $i < self::INSERT_COUNT; ++$i) {
					//	get tweet
					try {
						$packet = $stream->read();
					}
					catch (Exception $e) {
						$logger->info((string) $e);
						continue;
					}

					//	Ignore bad data.
					if (!is_array($packet)) {
						$logger->info(gettype($packet) . ' packet');
						continue;
					}

					if ($stream::isMessage($packet)) {
						$packet['created'] = new UTCDateTime();
						$messagesClient->insertOne($packet);
						continue;
					}

					//	Filter. Use only part of returned structure.
					$tweet->assign($packet);

					//	If tweet is not in english then skip it.
					if ($tweet->lang !== 'en') {
						$logger->info('lang not en');
						continue;
					}

					//	Pre calculate tallySet for INSERT_COUNT of tweets.
					$uniqueWords = new Set();
					//	Make sure we have only one of a hashtag per tweet for uniqueHashtags.
					foreach ($tweet->entities->hashtags as $hashtag) {
						foreach ($hashtag as $h) {
							if (ord($h) & 0x80) {
								continue 2;    //	skip hashtag if it contains a non-ASCII byte
							}
						}
						$uniqueWords->add($hashtag->text);
						$tallySet->allHashtags->doTally($hashtag->text);
					}

					//	Count unique hashtags for this tweet.
					foreach ($uniqueWords as $uniqueWord) {
						$tallySet->uniqueHashtags->doTally($uniqueWord);
					}

					//	Tally the words in the text.
					$text  = preg_replace('#https?:[^ ]+#', ' ', $tweet->text);
					$split = preg_split('/[^a-zA-Z0-9\']/', $text, null, PREG_SPLIT_NO_EMPTY);
					foreach ($split as $s) {
						if (strlen($s) > 2 && !in_array(strtolower($s), $stopWords, true)) {
							$tallySet->textWords->doTally($s);
						}
					}

					//	Tally user mentions.
					foreach ($tweet->entities->user_mentions as $userMention) {
						$tallySet->userMentions->doTally($userMention->screen_name);
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

					$tweets[] = $tweet; //->toArray();
				}

				try {
					//	convert to Mongo compatible object and insert
					$tweetsClient->insertMany($tweets, $insertOptions);
					$talliesClient->insertOne($tallySet/*->toArray()*/, $insertOptions);
				}
				catch (Exception $e) {
					$m = $e->getMessage();

					if (false !== stripos($m, "Authentication")) {
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

		$pidHandler->removeIfExists();
	}

}