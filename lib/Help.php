<?php

namespace Medienbaecker\HelpView;

use Kirby\Data\Txt;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;

class Help
{
	public static function articles(string $root): array
	{
		$ext      = kirby()->contentExtension();
		$articles = [];

		if (Dir::exists($root) === false) {
			return $articles;
		}

		$items = Dir::read($root);
		sort($items, SORT_NATURAL);

		foreach ($items as $item) {
			$path = $root . '/' . $item;

			if (is_dir($path) === false) {
				continue;
			}

			$articleFile = $path . '/article.' . $ext;
			if (F::exists($articleFile)) {
				$articles[] = self::parseArticle($path, $root);
				continue;
			}

			$categoryFile  = $path . '/category.' . $ext;
			$categoryTitle = Str::label(self::slug($item));

			if (F::exists($categoryFile)) {
				$categoryData  = Txt::read($categoryFile);
				$categoryTitle = $categoryData['title'] ?? $categoryTitle;
			}

			$category = [
				'slug'     => self::slug($item),
				'title'    => $categoryTitle,
				'children' => []
			];

			$children = Dir::read($path);
			sort($children, SORT_NATURAL);

			foreach ($children as $child) {
				$childPath    = $path . '/' . $child;
				$childArticle = $childPath . '/article.' . $ext;

				if (is_dir($childPath) && F::exists($childArticle)) {
					$category['children'][] = self::parseArticle($childPath, $root);
				}
			}

			if (count($category['children']) > 0) {
				$articles[] = $category;
			}
		}

		return $articles;
	}

	public static function find(array $articles, string $slug): array
	{
		foreach ($articles as $article) {
			if (isset($article['content']) && $article['slug'] === $slug) {
				return ['article' => $article, 'category' => null];
			}

			if (isset($article['children'])) {
				foreach ($article['children'] as $child) {
					if ($child['slug'] === $slug) {
						return ['article' => $child, 'category' => $article['title']];
					}
				}
			}
		}

		return ['article' => null, 'category' => null];
	}

	private static function parseArticle(string $folder, string $root): array
	{
		$ext  = kirby()->contentExtension();
		$file = $folder . '/article.' . $ext;
		$data = Txt::read($file);

		$slug  = self::slug(basename($folder));
		$title = $data['title'] ?? Str::label($slug);

		$relativePath = ltrim(str_replace($root, '', $folder), '/');

		$text = $data['text'] ?? '';
		$text = preg_replace_callback(
			'/(?<!`)\(image:\s*([^)\s]+)/',
			fn($m) => '(image: /api/help/image/' . $relativePath . '/' . $m[1],
			$text
		);

		$html = kirbytext($text);

		return [
			'slug'    => $slug,
			'title'   => $title,
			'content' => $html,
			'icon'    => $data['icon'] ?? 'question',
			'color'   => $data['color'] ?? null,
			'back'    => $data['back'] ?? null
		];
	}

	private static function slug(string $name): string
	{
		if (($pos = strpos($name, Dir::$numSeparator)) !== false) {
			return substr($name, $pos + 1);
		}
		return $name;
	}
}
