<?php

namespace Medienbaecker\HelpView;

use Kirby\Content\Content;
use Kirby\Data\Txt;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Html;
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
	 * Process kirbytext while protecting code blocks and Panel elements
	 * (<k-button>, <k-icon>) from markdown
	 */
	private static function kirbytext(string $text, HelpPage $parent): string
	{
		$protected = [];
		$placeholder = '⌘HELP_PROTECTED_' . bin2hex(random_bytes(8)) . '_';

		// Protect code blocks from KirbyTag parsing
		$text = preg_replace_callback(
			'/```(\w*)\n([\s\S]*?)```|`([^`\n]+)`/',
			function (array $m) use (&$protected, $placeholder): string {
				$index = count($protected);
				if (isset($m[3])) {
					// Inline code
					$protected[$index] = ['type' => 'inline', 'code' => $m[3]];
				} else {
					// Fenced code block
					$protected[$index] = ['type' => 'fenced', 'lang' => $m[1], 'code' => $m[2]];
				}
				return $placeholder . $index . '⌘';
			},
			$text
		);

		// Protect <k-button> elements (scoped to help articles, no global tag)
		$text = preg_replace_callback(
			'/<k-button(?![\w-])([^>]*)>(.*?)<\/k-button>/is',
			function (array $m) use (&$protected, $placeholder): string {
				$index = count($protected);
				$protected[$index] = ['type' => 'button', 'attrs' => $m[1], 'label' => $m[2]];
				return $placeholder . $index . '⌘';
			},
			$text
		);

		// Protect <k-icon> elements
		$text = preg_replace_callback(
			'/<k-icon(?![\w-])([^>]*?)\s*\/?>/i',
			function (array $m) use (&$protected, $placeholder): string {
				$index = count($protected);
				$protected[$index] = ['type' => 'icon', 'attrs' => $m[1]];
				return $placeholder . $index . '⌘';
			},
			$text
		);

		$kirby = kirby();
		$html = $kirby->kirbytags($text, ['parent' => $parent]);
		// Strip leading whitespace from KirbyTag output and protected
		// placeholders so Parsedown doesn't treat indented lines as code blocks
		$html = preg_replace('/^[\t ]+(<[a-zA-Z\/!]|⌘)/m', '$1', $html);
		$html = $kirby->markdown($html);
		$html = $kirby->smartypants($html);

		// Restore protected spans as HTML
		return preg_replace_callback(
			'/<p>' . preg_quote($placeholder, '/') . '(\d+)⌘<\/p>|' . preg_quote($placeholder, '/') . '(\d+)⌘/',
			function (array $m) use ($protected): string {
				$wrappedInP = $m[1] !== '';
				$block = $protected[(int)($wrappedInP ? $m[1] : $m[2])];

				$rendered = match ($block['type']) {
					'inline' => '<code>' . esc($block['code']) . '</code>',
					'button' => self::panelButton($block['attrs'], $block['label']),
					'icon'   => self::panelIcon($block['attrs']),
					'fenced' => '<pre><code' . ($block['lang'] ? ' class="language-' . esc($block['lang']) . '"' : '') . '>' . esc(trim($block['code'])) . '</code></pre>',
				};

				// Markdown wraps every lone placeholder in its own <p>. Keep that
				// <p> only around text-level output (inline code → <code>,
				// buttons → <span>/<a>), exactly as Kirby's markdown does; <pre>
				// and <svg> are block-level, so leave them bare.
				$textLevel = in_array($block['type'], ['inline', 'button'], true);
				return $wrappedInP && $textLevel ? '<p>' . $rendered . '</p>' : $rendered;
			},
			$html
		);
	}

	/**
	 * Allow only safe link targets. Returns the URL if it's relative or uses a
	 * safe scheme (http, https, mailto, tel), otherwise null — blocks
	 * javascript:, data:, vbscript: etc. (incl. whitespace/case obfuscation),
	 * since the output is injected into the Panel via v-html.
	 */
	private static function safeUrl(?string $url): ?string
	{
		if ($url === null || $url === '') {
			return null;
		}

		// Browsers ignore leading/embedded whitespace and control characters
		// when resolving the scheme, so strip them before validating.
		$bare = preg_replace('/[\x00-\x20]+/', '', $url);

		// A scheme is present but not on the allowlist → reject.
		// No scheme → relative/root-relative URL → allow.
		if (
			preg_match('/^[a-z][a-z0-9+.\-]*:/i', $bare) &&
			!preg_match('/^(https?|mailto|tel):/i', $bare)
		) {
			return null;
		}

		return $url;
	}

	/**
	 * Extract a double-quoted attribute value from a raw attribute string
	 */
	private static function attr(string $attrs, string $name): ?string
	{
		if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*"([^"]*)"/i', $attrs, $m)) {
			return $m[1];
		}
		return null;
	}

	/**
	 * Render an icon from Kirby's own Panel icon sprite. A non-empty $alt makes
	 * it meaningful (role="img" + label); without it the icon is decorative.
	 */
	private static function icon(string $name, string $class = '', ?string $alt = null): string
	{
		$a11y = $alt
			? 'role="img" aria-label="' . esc($alt) . '"'
			: 'aria-hidden="true"';

		return '<svg ' . $a11y . ' class="' . trim('k-icon ' . $class) . '"><use xlink:href="#icon-' . esc($name) . '"></use></svg>';
	}

	/**
	 * Render an inline Panel icon. Add `alt` when the icon carries meaning on
	 * its own; otherwise it stays decorative (hidden from screen readers).
	 */
	private static function panelIcon(string $attrs): string
	{
		$name = self::attr($attrs, 'name');

		return $name ? self::icon($name, 'k-help-icon', self::attr($attrs, 'alt')) : '';
	}

	/**
	 * Render a replica of a Panel button using Kirby's own .k-button markup,
	 * so the Panel's existing CSS + icon sprite style it. A `link` makes it an
	 * interactive <a>, otherwise it's a static <span> representation.
	 */
	private static function panelButton(string $attrs, string $label): string
	{
		$icon = self::attr($attrs, 'icon');
		$link = self::safeUrl(self::attr($attrs, 'link'));
		$text = trim($label);

		// Nothing to show
		if (!$icon && $text === '') {
			return '';
		}

		$external = $link !== null && preg_match('#^\s*https?://#i', $link) === 1;

		$attr = [
			'class'         => 'k-button k-help-button',
			'data-has-icon' => $icon ? 'true' : null,
			'data-has-text' => $text !== '' ? 'true' : null,
			'data-theme'    => self::attr($attrs, 'theme'),
			'data-variant'  => self::attr($attrs, 'variant') ?: 'filled',
			'data-size'     => self::attr($attrs, 'size'),
			'href'          => $link,
			'target'        => $external ? '_blank' : null,
			'rel'           => $external ? 'noreferrer noopener' : null,
		];

		$content = [];
		if ($icon) {
			// With visible text the icon is decorative; for an icon-only button
			// it carries the accessible name via `title` (as k-button does).
			$iconAlt = $text === '' ? self::attr($attrs, 'title') : null;
			$content[] = '<span class="k-button-icon">' . self::icon($icon, '', $iconAlt) . '</span>';
		}
		if ($text !== '') {
			$content[] = Html::tag('span', $text, ['class' => 'k-button-text']);
		}

		return Html::tag($link ? 'a' : 'span', $content, $attr);
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
