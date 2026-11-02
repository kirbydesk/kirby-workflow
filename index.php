<?php

use Kirby\Cms\App as Kirby;
use Kirbydesk\Workflow\GitHub;
use Kirbydesk\Workflow\Settings;
use Kirbydesk\Workflow\Status;
use Kirby\Exception\NotFoundException;
use Kirby\Toolkit\I18n;

load([
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
					$settings();

					return [
						'component' => 'k-workflow-view',
						'title'     => I18n::translate('kirbydesk.workflow'),
					];
				},
			],
		],
		'dialogs' => [
			'workflow/publish' => [
				'load' => function () use ($settings) {
					$kirby     = Kirby::instance();
					$published = (new Status($kirby, new GitHub($kirby, $settings())))->toArray()['published'];
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
