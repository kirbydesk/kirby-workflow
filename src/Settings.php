<?php

namespace Kirbydesk\Workflow;

use Kirby\Cms\App;
use Kirby\Filesystem\F;

/**
 * Settings of the plugin – a JSON file outside the repository (default `.workflow/settings.json` in the
 * project folder), so they exist only where content is edited (usually Staging). Without them the plugin does
 * nothing.
 */
class Settings
{
	public function __construct(
		public readonly string $repository,
		public readonly string $workflow,
		public readonly string $ref,
		public readonly array $inputs,
		public readonly string|null $userInput,
		public readonly int $appId,
		public readonly string $privateKey,
		/** Repository with the published content (for „Changes“) – `null`: no comparison */
		public readonly string|null $contentRepository = null,
		public readonly string $contentRef = 'main',
		/** Workflow that publishes the code (for „Last published“ under „Code“) */
		public readonly string|null $codeWorkflow = null,
		/** The live website – „View Website“ after publishing */
		public readonly string|null $website = null
	) {
	}

	/** `null` if there are no (valid) settings */
	public static function load(App $kirby): static|null
	{
		$file = static::file($kirby);

		if (F::exists($file) === false) {
			return null;
		}

		$data   = json_decode(F::read($file), true);
		$github = $data['github'] ?? null;

		if (is_array($github) === false || empty($github['repository']) || empty($github['workflow']) || empty($github['appId'])) {
			return null;
		}

		// The key: a file next to the settings (relative path) or an absolute path
		$keyFile = $github['privateKey'] ?? 'github-app.pem';
		if (str_starts_with($keyFile, '/') === false) {
			$keyFile = dirname($file) . '/' . $keyFile;
		}

		if (F::exists($keyFile) === false) {
			return null;
		}

		return new static(
			repository: $github['repository'],
			workflow:   $github['workflow'],
			ref:        $github['ref'] ?? 'main',
			inputs:     $github['inputs'] ?? [],
			userInput:  $github['userInput'] ?? null,
			appId:      (int)$github['appId'],
			privateKey: F::read($keyFile),
			contentRepository: $github['contentRepository'] ?? null,
			contentRef:        $github['contentRef'] ?? 'main',
			codeWorkflow:      $github['codeWorkflow'] ?? null,
			website:           is_string($data['website'] ?? null) ? $data['website'] : null
		);
	}

	/** Option `kirbydesk.workflow.settings`, otherwise `.workflow/settings.json` in the project folder */
	public static function file(App $kirby): string
	{
		$option = $kirby->option('kirbydesk.workflow.settings');

		if (is_string($option) === true && $option !== '') {
			return $option;
		}

		// The project folder: one level above `site` (also with a separate public folder)
		return dirname($kirby->root('site')) . '/.workflow/settings.json';
	}
}
