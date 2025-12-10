<?php

use Kirby\Cms\App as Kirby;
use Kirby\Data\Json;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Http\Response;
use Kirby\Toolkit\A;
use Medienbaecker\HelpView\Help;

require __DIR__ . '/lib/Help.php';

Kirby::plugin('medienbaecker/help-view', [
	'options' => [
		'root' => null,
	],
	'hooks' => [
		// Protect code blocks from KirbyTag parsing
		'kirbytags:before' => function (string $text): string {
			// Replace fenced code blocks with placeholders
			return preg_replace_callback(
				'/```[\s\S]*?```|`[^`\n]+`/',
				fn($m) => '{{CODE:' . base64_encode($m[0]) . '}}',
				$text
			);
		},
		'kirbytags:after' => function (string $text): string {
			// Restore code blocks from placeholders
			return preg_replace_callback(
				'/\{\{CODE:([A-Za-z0-9+\/=]+)\}\}/',
				fn($m) => base64_decode($m[1]),
				$text
			);
		}
	],
	'translations' => A::keyBy(
		A::map(
			Dir::read(__DIR__ . '/translations'),
			function ($file) {
				$translations = [];
				foreach (Json::read(__DIR__ . '/translations/' . $file) as $key => $value) {
					$translations["medienbaecker.help-view.{$key}"] = $value;
				}
				return A::merge(
					['lang' => F::name($file)],
					$translations
				);
			}
		),
		'lang'
	),
	'api' => [
		'routes' => [
			[
				'pattern' => 'help/image/(:all)',
				'auth'    => false,
				'action'  => function (string $path) {
					$root = kirby()->option('medienbaecker.help-view.root')
						?? kirby()->root('site') . '/help';
					$file = $root . '/' . $path;

					if (F::exists($file)) {
						return Response::file($file);
					}

					return new Response('Not found', 'text/plain', 404);
				}
			]
		]
	],
	'areas' => [
		'help' => function ($kirby) {
			$root = $kirby->option('medienbaecker.help-view.root') ?? $kirby->root('site') . '/help';

			// Don't show menu item if help folder doesn't exist
			if (Dir::exists($root) === false) {
				return [];
			}

			return [
				'label' => t('medienbaecker.help-view.title'),
				'icon'  => 'question',
				'menu'  => true,
				'link'  => 'help',
				'views' => [
					[
						'pattern' => 'help',
						'action'  => function () use ($root) {
							$articles = Help::articles($root);

							return [
								'component' => 'k-help-view',
								'title'     => t('medienbaecker.help-view.title'),
								'props'     => [
									'articles' => $articles,
									'current'  => null,
								]
							];
						}
					],
					[
						'pattern' => 'help/(:all)',
						'action'  => function ($slug) use ($root) {
							$articles = Help::articles($root);
							$result   = Help::find($articles, $slug);
							$current  = $result['article'];
							$category = $result['category'];

							// Build breadcrumb (area name is added automatically)
							$breadcrumb = [];

							if ($category) {
								$breadcrumb[] = ['label' => $category];
							}

							if ($current) {
								$breadcrumb[] = [
									'label' => $current['title'],
									'link'  => 'help/' . $current['slug']
								];
							}

							return [
								'component'  => 'k-help-view',
								'title'      => $current['title'] ?? t('medienbaecker.help-view.title'),
								'breadcrumb' => $breadcrumb,
								'props'      => [
									'articles' => $articles,
									'current'  => $current,
								]
							];
						}
					]
				]
			];
		}
	]
]);
