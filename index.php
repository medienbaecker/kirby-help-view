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
				'action'  => function (string $path): Response {
					$root = Help::root();
					$rootReal = realpath($root);

					// Help directory must exist
					if ($rootReal === false) {
						return new Response('Not found', 'text/plain', 404);
					}

					$file = $root . '/' . $path;
					$fileReal = realpath($file);

					// File must exist and be within help directory
					if ($fileReal === false || str_starts_with($fileReal, $rootReal) === false) {
						return new Response('Not found', 'text/plain', 404);
					}

					return Response::file($fileReal);
				}
			]
		]
	],
	'areas' => [
		'help' => function (): array {
			$root = Help::root();

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
						'action'  => function (): array {
							$articles = Help::articles(Help::root());

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
						'action'  => function (string $slug): array {
							$root = Help::root();
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
