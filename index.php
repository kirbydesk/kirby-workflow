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
					$github   = new GitHub($kirby, $settings);
					$status   = new Status($kirby, $github);
					$tab      = $kirby->request()->get('tab', $settings->contentRepository !== null ? 'changes' : 'history');

					// Without a connection every tab would be empty – one clear message instead (GitHub's own answer)
					try {
						$github->permissions();
					} catch (Throwable $e) {
						return [
							'component' => 'k-workflow-view',
							'title'     => I18n::translate('kirbydesk.workflow'),
							'props'     => ['connection' => $e->getMessage()],
						];
					}

					// Each part on its own – one failing (e.g. a missing permission) must not hide the others
					$try = function (callable $load) {
						try {
							return ['data' => $load(), 'error' => null];
						} catch (Throwable $e) {
							return ['data' => null, 'error' => $e->getMessage()];
						}
					};

					return [
						'component' => 'k-workflow-view',
						'title'     => I18n::translate('kirbydesk.workflow'),
						'props'     => [
							'tab'     => $tab,
							// Changes always – their number is on the tab
							'changes' => $settings->contentRepository !== null ? $try(fn () => (new Changes($kirby, $github, $settings))->toArray()) : null,
							'history' => $tab === 'history' ? $try(fn () => $status->history()) : null,
							'github'  => $tab === 'github' ? $try(fn () => $status->github($settings)) : null,
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

$extension['api'] = [
	'routes' => [
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
