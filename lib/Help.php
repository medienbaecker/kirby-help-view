<?php

namespace Medienbaecker\HelpView;

use Kirby\Content\Content;
use Kirby\Data\Txt;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;

class Help
{
	/**
	 * Get the help content root directory
	 */
	public static function root(): string
	{
		return kirby()->option('medienbaecker.help-view.root')
			?? kirby()->root('site') . '/help';
	}

	/**
	 * Read directory contents sorted naturally
	 */
	private static function readSorted(string $path): array
	{
		$items = Dir::read($path);
		sort($items, SORT_NATURAL);
		return $items;
	}

	public static function articles(string $root): array
	{
		$articles = [];

		if (Dir::exists($root) === false) {
			return $articles;
		}

		foreach (self::readSorted($root) as $item) {
			$path = $root . '/' . $item;

			if (Dir::exists($path) === false) {
				continue;
			}

			$articleFile = self::contentFile($path, 'article');
			if (F::exists($articleFile)) {
				$articles[] = self::parseArticle($path, $root);
				continue;
			}

			$categoryFile = self::contentFile($path, 'category');
			$categoryTitle = Str::label(self::slug($item));

			if (F::exists($categoryFile)) {
				$content = new Content(Txt::read($categoryFile));
				$categoryTitle = $content->title()->or($categoryTitle)->value();
			}

			$category = [
				'slug'     => self::slug($item),
				'title'    => $categoryTitle,
				'children' => []
			];

			foreach (self::readSorted($path) as $child) {
				$childPath    = $path . '/' . $child;
				$childArticle = self::contentFile($childPath, 'article');

				if (Dir::exists($childPath) && F::exists($childArticle)) {
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
		$file = self::contentFile($folder, 'article');
		$content = new Content(Txt::read($file));

		$slug  = self::slug(basename($folder));
		$title = $content->title()->or(Str::label($slug))->value();

		$relativePath = ltrim(str_replace($root, '', $folder), '/');

		$page = new HelpPage([
			'slug'     => $slug,
			'root'     => $folder,
			'content'  => ['title' => $title],
			'helpPath' => $relativePath,
		]);

		$text = $content->text()->or('')->value();
		$html = self::kirbytext($text, $page);

		return [
			'slug'    => $slug,
			'title'   => $title,
			'content' => $html,
			'icon'    => $content->icon()->or('question')->value(),
			'color'   => $content->color()->or('')->value(),
			'back'    => $content->back()->or('')->value()
		];
	}

	/**
	 * Process kirbytext while protecting code blocks
	 */
	private static function kirbytext(string $text, HelpPage $parent): string
	{
		$codeBlocks = [];
		$placeholder = '⌘HELP_CODE_' . bin2hex(random_bytes(8)) . '_';

		// Protect code blocks from KirbyTag parsing
		$text = preg_replace_callback(
			'/```(\w*)\n([\s\S]*?)```|`([^`\n]+)`/',
			function (array $m) use (&$codeBlocks, $placeholder): string {
				$index = count($codeBlocks);
				if (isset($m[3])) {
					// Inline code
					$codeBlocks[$index] = ['type' => 'inline', 'code' => $m[3]];
				} else {
					// Fenced code block
					$codeBlocks[$index] = ['type' => 'fenced', 'lang' => $m[1], 'code' => $m[2]];
				}
				return $placeholder . $index . '⌘';
			},
			$text
		);

		$kirby = kirby();
		$html = $kirby->kirbytags($text, ['parent' => $parent]);
		// Strip leading whitespace from HTML lines to prevent
		// Parsedown from treating indented KirbyTag output as code blocks
		$html = preg_replace('/^[\t ]+(<[a-zA-Z\/!])/m', '$1', $html);
		$html = $kirby->markdown($html);
		$html = $kirby->smartypants($html);

		// Restore code blocks as HTML
		return preg_replace_callback(
			'/<p>' . preg_quote($placeholder, '/') . '(\d+)⌘<\/p>|' . preg_quote($placeholder, '/') . '(\d+)⌘/',
			function (array $m) use ($codeBlocks): string {
				$index = (int)($m[1] !== '' ? $m[1] : $m[2]);
				$block = $codeBlocks[$index];

				if ($block['type'] === 'inline') {
					return '<code>' . esc($block['code']) . '</code>';
				}

				$langAttr = $block['lang'] ? ' class="language-' . esc($block['lang']) . '"' : '';
				return '<pre><code' . $langAttr . '>' . esc(trim($block['code'])) . '</code></pre>';
			},
			$html
		);
	}

	private static function slug(string $name): string
	{
		if (($pos = strpos($name, Dir::$numSeparator)) !== false) {
			return substr($name, $pos + 1);
		}
		return $name;
	}

	private static function contentFile(string $path, string $name): string
	{
		$kirby = kirby();
		$ext = $kirby->contentExtension();
		$simple = $path . '/' . $name . '.' . $ext;

		// Use Panel language from user settings
		$lang = $kirby->user()->language();

		// Try Panel language first
		$file = $path . '/' . $name . '.' . $lang . '.' . $ext;
		if (F::exists($file)) {
			return $file;
		}

		// Fallback to simple filename (no language code)
		return $simple;
	}
}
