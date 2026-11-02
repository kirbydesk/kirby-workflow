<?php

use Kirby\Cms\App as Kirby;
use Kirbydesk\Workflow\Changes;
use Kirbydesk\Workflow\GitHub;
use Kirbydesk\Workflow\Settings;
use Kirbydesk\Workflow\Status;
use Kirby\Exception\NotFoundException;
use Kirby\Toolkit\I18n;

load([
	'Kirbydesk\\Workflow\\Changes'  => __DIR__ . '/src/Changes.php',
	'Kirbydesk\\Workflow\\GitHub'   => __DIR__ . '/src/GitHub.php',
	'Kirbydesk\\Workflow\\Settings' => __DIR__ . '/src/Settings.php',
	'Kirbydesk\\Workflow\\Status'   => __DIR__ . '/src/Status.php',
]);

$extension = [
	'options' => [
		// Path of the settings file – by default `.workflow/settings.json` in the project folder
		'settings' => null,
		'cache'    => true,
	],
	// Every file in `translations/` is a language – its name is the code (`de.php`, `fr.php` …)
	'translations' => array_combine(
		array_map(fn ($file) => basename($file, '.php'), $translations = glob(__DIR__ . '/translations/*.php')),
		array_map(fn ($file) => require $file, $translations)
	),
];

// Without settings the plugin does nothing and leaves the Panel untouched: no menu entry, no view, no API.
// Settings are read when needed – at plugin load time Kirby is not ready yet
$settings = function (): Settings {
	$settings = Settings::load(Kirby::instance());

	if ($settings === null) {
		throw new NotFoundException(message: 'Workflow settings not found');
	}

	return $settings;
};

$extension['areas'] = [
	'workflow' => fn (Kirby $kirby) => [
		'label' => I18n::translate('kirbydesk.workflow'),
		'icon'  => 'kirbydesk',
		// Only where the settings are (usually Staging)
		'menu'  => Settings::load($kirby) !== null,
		'link'  => 'workflow',
		'views' => [
			[
				'pattern' => 'workflow',
				'action'  => function () use ($settings) {
					$kirby    = Kirby::instance();
					$settings = $settings();

					// Right away – everything from GitHub loads afterwards (API routes below), so the page shows at once
					return [
						'component' => 'k-workflow-view',
						'title'     => I18n::translate('kirbydesk.workflow'),
						'props'     => [
							'tab'        => $kirby->request()->get('tab'),
							'hasChanges' => $settings->contentRepository !== null,
						],
					];
				},
			],
		],
		'drawers' => [
			// What one publish brought live – opened from „History“
			'workflow/commit/(:any)' => [
				'load' => function (string $sha) use ($settings) {
					$kirby    = Kirby::instance();
					$settings = $settings();
					$title    = $kirby->request()->get('title');

					return [
						'component' => 'k-workflow-commit-drawer',
						'props'     => [
							'icon'  => 'clock',
							'title' => is_string($title) && $title !== '' ? $title : I18n::translate('kirbydesk.workflow.history'),
							'items' => (new Changes($kirby, new GitHub($kirby, $settings), $settings))->fromCommit($sha),
						],
					];
				},
			],
		],
		'dialogs' => [
			'workflow/publish' => [
				'load' => function () use ($settings) {
					$kirby     = Kirby::instance();
					// Without „last published“ the question still works
					try {
						$published = (new Status($kirby, new GitHub($kirby, $settings())))->toArray()['published'];
					} catch (Throwable) {
						$published = null;
					}
					$text      = '<p><strong>' . I18n::translate('kirbydesk.workflow.confirm') . '</strong></p>' .
						'<p>' . I18n::translate('kirbydesk.workflow.confirm.text') . '</p>';

					if ($published !== null) {
						$text .= '<p>' . I18n::template('kirbydesk.workflow.confirm.last', null, [
							'date' => Status::date($published['at'], $kirby),
							'name' => htmlspecialchars($published['by'] ?? ''),
						]) . '</p>';
					}

					$text .= '<p>' . I18n::translate('kirbydesk.workflow.confirm.unsaved') . '</p>';

					return [
						'component' => 'k-text-dialog',
						'props'     => [
							'text'         => $text,
							'size'         => 'medium',
							'submitButton' => [
								'icon'  => 'cloud-upload',
								'text'  => I18n::translate('kirbydesk.workflow.publish'),
								'theme' => 'positive',
							],
						],
					];
				},
				'submit' => function () use ($settings) {
					$kirby  = Kirby::instance();
					$github = new GitHub($kirby, $settings());
					$user   = $kirby->user();
					$github->dispatch($user?->name()->isNotEmpty() ? $user->name()->value() : $user?->email());
					(new Status($kirby, $github))->markDispatched();

					return ['event' => 'workflow.started'];
				},
			],
		],
	],
];

// Each part on its own – one failing (e.g. a missing permission) must not hide the others
$part = function (callable $load): array {
	try {
		return ['data' => $load(), 'error' => null];
	} catch (Throwable $e) {
		return ['data' => null, 'error' => $e->getMessage()];
	}
};

$route = fn (string $pattern, callable $load) => [
	'pattern' => 'workflow/' . $pattern,
	'method'  => 'GET',
	'action'  => function () use ($settings, $load, $part) {
		$kirby    = Kirby::instance();
		$settings = $settings();
		$github   = new GitHub($kirby, $settings);
		return $part(fn () => $load($kirby, $settings, $github));
	},
];

$extension['api'] = [
	'routes' => [
		// Without a connection every tab would be empty – the view then shows one message (GitHub's own answer)
		$route('connection', fn ($kirby, $settings, $github) => $github->permissions() !== null),
		$route('changes', fn ($kirby, $settings, $github) => (new Changes($kirby, $github, $settings))->toArray()),
		$route('history', function ($kirby, $settings, $github) {
			$history = (new Status($kirby, $github))->history(contentRepository: $settings->contentRepository);
			$changes = new Changes($kirby, $github, $settings);

			// Number of changed pages and files per publish – a commit never changes, so it is cached
			return array_map(function ($entry) use ($kirby, $changes) {
				if ($entry['commit'] !== null) {
					$cache          = $kirby->cache('kirbydesk.workflow');
					$entry['count'] = $cache->getOrSet('count-' . $entry['commit'], fn () => count($changes->fromCommit($entry['commit'])));
				}
				return $entry;
			}, $history);
		}),
		$route('github', fn ($kirby, $settings, $github) => (new Status($kirby, $github))->github($settings)),
		[
			'pattern' => 'workflow/status',
			'method'  => 'GET',
			'action'  => function () use ($settings) {
				$kirby = Kirby::instance();
				return (new Status($kirby, new GitHub($kirby, $settings())))->toArray();
			},
		],
	],
];

Kirby::plugin('kirbydesk/workflow', $extension);
