<?php

namespace Code;

use Phalcon\Logger\Adapter\File;
use Phalcon\Logger\Adapter\Stream;
use Phalcon\Logger\Formatter\Line;

/**
 * This logger class writes to both a named file and to STDERR.
 *
 * @copyright     Copyright (c) 2016 Reid Woodbury Jr.
 * @license       http://www.apache.org/licenses/LICENSE-2.0.html	Apache License, Version 2.0
 */
class LoggerFactory
{
	/**
	 * Log message output format.
	 */
	const OUTPUT_FORMAT = "%type%\t%date%\t%message%";

	/**
	 * Log format object.
	 * @type Line
	 */
	protected static $_format;

	/**
	 * @type File
	 */
	protected $_file;

	/**
	 * @type Stream
	 */
	protected $_stream;

	/**
	 */
	function __construct($fileName)
	{
		$this->_file   = self::getFile($fileName);
		$this->_stream = self::getStream();
	}

	public static function getFileLogger($fileName)
	{
		$file = new File($fileName);
		$file->setFormatter(self::getFormatter());
		return $file;
	}

	public static function getFormatter()
	{
		if (!isset(self::$_format)) {
			self::$_format = new Line(self::OUTPUT_FORMAT);
		}
		return self::$_format;
	}

	public static function getStreamLogger()
	{
		$stream = new Stream('php://stderr');
		$stream->setFormatter(self::getFormatter());
		return $stream;
	}

	/**
	 * Log the message.
	 * The function name becomes the log level
	 *
	 * @param string $key
	 */
	function __call($level, $params)
	{
		$message = $params[0];
		$this->_file->$level($message);

		switch ($level) {
			case 'critical':
			case 'emergency':
			case 'debug':
				$this->_stream->$level($message);
		}
	}

}