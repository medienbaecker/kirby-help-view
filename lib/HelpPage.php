<?php

namespace Medienbaecker\HelpView;

use Kirby\Cms\Files;
use Kirby\Cms\Page;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;

class HelpPage extends Page
{
	protected string $helpPath;

	public function __construct(array $props)
	{
		$this->helpPath = $props['helpPath'] ?? '';
		unset($props['helpPath']);
		parent::__construct($props);
	}

	public function files(): Files
	{
		if ($this->files !== null) {
			return $this->files;
		}

		$collection = new Files([], $this);
		$root = $this->root();

		if ($root === null || Dir::exists($root) === false) {
			return $this->files = $collection;
		}

		$ext = kirby()->contentExtension();

		foreach (Dir::read($root) as $item) {
			if (F::extension($item) === $ext || is_file($root . '/' . $item) === false) {
				continue;
			}

			$file = new HelpFile([
				'filename' => $item,
				'parent'   => $this,
				'url'      => '/api/help/file/' . $this->helpPath . '/' . $item,
			]);
			$collection->append($file->id(), $file);
		}

		return $this->files = $collection;
	}
}
