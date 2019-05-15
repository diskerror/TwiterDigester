<?php

namespace Structure\Tweet\Entities;

/**
 * Class Entities
 *
 * @package Structure\Tweet\Entities
 * @package Diskerror\Typed\TypedArray $hashtags
 * @package Diskerror\Typed\TypedArray $user_mentions
 */
class Entities extends \Diskerror\Typed\TypedClass
{
	protected $hashtags      = '__class__Diskerror\Typed\TypedArray(null, "Structure\Tweet\Entities\Hashtags")';

//	protected $urls          = '__class__Diskerror\Typed\TypedArray(null, "Structure\Tweet\Entities\Urls")';

	protected $user_mentions = '__class__Diskerror\Typed\TypedArray(null, "Structure\Tweet\Entities\UserMentions")';

// 	protected $symbols  = '';
// 	protected $polls  = '';
}
