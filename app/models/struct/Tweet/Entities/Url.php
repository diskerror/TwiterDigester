<?php

namespace Structure\Tweet\Entities;

use Diskerror\TypedBSON\TypedArray;
use Diskerror\TypedBSON\TypedClass;

class Url extends TypedClass
{
	protected $url          = '';

	protected $expanded_url = '';

	protected $display_url  = '';

	protected $indices      = [TypedArray::class, 'int'];
}
