<?php

namespace Medienbaecker\HelpView;

use Kirby\Cms\File;

class HelpFile extends File
{
	public function url(): string
	{
		return $this->url;
	}
}
