<?php

use Kirby\Cms\App as Kirby;
use Kirbydesk\Workflow\Changes;
use Kirbydesk\Workflow\Exposure;
use Kirbydesk\Workflow\GitHub;
use Kirbydesk\Workflow\Settings;
use Kirbydesk\Workflow\Status;
use Kirby\Exception\Exception;
use Kirby\Exception\NotFoundException;
use Kirby\Panel\Panel;
use Kirby\Toolkit\I18n;

load([
	'Kirbydesk\\Workflow\\Changes'  => __DIR__ . '/src/Changes.php',
	'Kirbydesk\\Workflow\\Exposure' => __DIR__ . '/src/Exposure.php',
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
				// Like Kirby SEO: /workflow goes to the first tab
				'pattern' => 'workflow',
				'action'  => fn () => Panel::go('workflow/' . ($settings()->contentRepository !== null ? 'changes' : 'history')),
			],
			[
				// Each tab its own address, like Kirby SEO: /workflow/changes, /workflow/history, /workflow/github
				'pattern' => 'workflow/(changes|history|github)',
				'action'  => function (string $tab) use ($settings) {
					$kirby    = Kirby::instance();
					$settings = $settings();

					// Right away – everything from GitHub loads afterwards (API routes below), so the page shows at once
					return [
						'component' => 'k-workflow-view',
						'title'     => I18n::translate('kirbydesk.workflow'),
						'props'     => [
							'tab'        => $tab,
							'hasChanges' => $settings->contentRepository !== null,
						],
					];
				},
			],
		],
		'dialogs' => [
			// Like the activation question of kirby-explorer: small headline, the facts in a notice box. The dialog
			// stays open while publishing runs and shows the result – it starts the run itself (API `workflow/publish`)
			'workflow/publish' => [
				'load' => fn () => [
					'component' => 'k-workflow-publish-dialog',
					'props'     => [
						'headline'     => I18n::translate('kirbydesk.workflow.confirm'),
						'text'         => I18n::translate('kirbydesk.workflow.confirm.text') . ' ' . I18n::translate('kirbydesk.workflow.confirm.unsaved'),
						'size'         => 'medium',
						'submitButton' => I18n::translate('kirbydesk.workflow.publish'),
					],
				],
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

$route = fn (string $pattern, callable $load, string $method = 'GET') => [
	'pattern' => 'workflow/' . $pattern,
	'method'  => $method,
	'action'  => function (...$arguments) use ($settings, $load, $part) {
		$kirby    = Kirby::instance();
		$settings = $settings();
		$github   = new GitHub($kirby, $settings);
		return $part(fn () => $load($kirby, $settings, $github, ...$arguments));
	},
];

$extension['api'] = [
	'routes' => [
		// First: are the settings (with the key) open on the web? Then only that message. Without a connection every
		// tab would be empty – the view then shows one message (GitHub's own answer)
		$route('connection', function ($kirby, $settings, $github) {
			if (($exposed = Exposure::url($kirby)) !== null) {
				return ['exposed' => $exposed];
			}

			return ['exposed' => null, 'connected' => $github->permissions() !== null];
		}),
		$route('changes', fn ($kirby, $settings, $github) => (new Changes($kirby, $github, $settings))->toArray()),
		$route('history', function ($kirby, $settings, $github) {
			$status  = new Status($kirby, $github);
			$entries = $status->history(25, $settings->contentRepository);

			return [
				'entries' => $entries,
				// Probably older publishes than shown: link to the commits of the content repository on GitHub
				'more'    => $status->more,
				'url'     => 'https://github.com/' . ($settings->contentRepository ?? $settings->repository) . '/commits',
			];
		}),
		$route('github', fn ($kirby, $settings, $github) => (new Status($kirby, $github))->github($settings)),
		// What one publish brought live
		$route('commit/(:any)', fn ($kirby, $settings, $github, $sha) => (new Changes($kirby, $github, $settings))->fromCommit($sha)),
		$route('status', fn ($kirby, $settings, $github) => (new Status($kirby, $github))->toArray()),
		// Starts publishing – with the name of the Panel account (or its email) for the history
		$route('publish', function ($kirby, $settings, $github) {
			// Not while the key can be read by anyone – the view says what to do
			if (Exposure::url($kirby) !== null) {
				throw new Exception(message: I18n::translate('kirbydesk.workflow.exposed'));
			}

			$user = $kirby->user();
			$github->dispatch($user?->name()->isNotEmpty() ? $user->name()->value() : $user?->email());
			(new Status($kirby, $github))->markDispatched();
			return true;
		}, 'POST'),
	],
];

Kirby::plugin('kirbydesk/workflow', $extension);
