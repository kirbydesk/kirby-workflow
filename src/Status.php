<?php

namespace Kirbydesk\Workflow;

use Kirby\Cms\App;

/**
 * State of publishing, read from the runs of the workflow: is one running, when was the last successful one
 * and who started it (from the run name „… · Name“), did the latest one fail.
 */
class Status
{
	protected const RUNNING = ['queued', 'in_progress', 'waiting', 'requested', 'pending'];

	public function __construct(
		protected App $kirby,
		protected GitHub $github
	) {
	}

	/** Just started: GitHub needs a few seconds until the run shows up – until then it counts as running */
	public function markDispatched(): void
	{
		$this->kirby->cache('kirbydesk.workflow')->set('dispatched', time(), 2);
	}

	public function toArray(): array
	{
		$runs       = $this->github->runs();
		$dispatched = $this->kirby->cache('kirbydesk.workflow')->get('dispatched');
		$running    = null;
		$published  = null;
		$failed     = null;

		foreach ($runs as $run) {
			if (in_array($run['status'], static::RUNNING, true) === true) {
				$running ??= $run;
				continue;
			}

			if ($run['conclusion'] === 'success') {
				$published = $run;
				break;
			}

			// The newest finished run failed (and none succeeded after it)
			$failed ??= $run['conclusion'] === 'failure' ? $run : null;
		}

		// Started here, but the run is not listed yet
		if ($running === null && $dispatched !== null) {
			$newer = array_filter($runs, fn ($run) => strtotime($run['created_at']) >= $dispatched - 5);
			if ($newer === []) {
				$running = ['created_at' => date('c', $dispatched)];
			}
		}

		return [
			'running'   => $running !== null ? [
				'since' => $running['created_at'],
				'time'  => date('H:i', strtotime($running['created_at'])),
			] : null,
			'published' => $published !== null ? [
				'at'   => $published['updated_at'],
				'date' => static::date($published['updated_at'], $this->kirby),
				'by'   => static::person($published),
				'id'   => $published['id'],
			] : null,
			'failed'    => $running === null && $failed !== null ? [
				'at'      => $failed['updated_at'],
				'id'      => $failed['id'],
				'message' => $this->failureMessage($failed['id']),
			] : null,
		];
	}

	/** Every publish (newest first): date, person and – if it failed – the reason */
	public function history(int $limit = 20): array
	{
		$entries = [];

		foreach ($this->github->runs($limit) as $run) {
			if (in_array($run['status'], static::RUNNING, true) === true || $run['conclusion'] === 'cancelled') {
				continue;
			}

			$failed    = $run['conclusion'] !== 'success';
			$entries[] = [
				'date'   => static::date($run['updated_at'], $this->kirby),
				'by'     => static::person($run),
				'failed' => $failed,
				'reason' => $failed ? $this->failureMessage($run['id']) : null,
			];
		}

		return $entries;
	}

	/** Tab „GitHub“: repositories, permissions, last publish of the code */
	public function github(Settings $settings): array
	{
		$permissions = $this->github->permissions();
		$content     = null;
		$code        = ['repository' => $settings->repository, 'last' => null];

		if ($settings->contentRepository !== null) {
			$content = [
				'repository' => $settings->contentRepository,
				'compare'    => $this->github->canRead($settings->contentRepository),
			];
		}

		if ($settings->codeWorkflow !== null) {
			foreach ($this->github->runsOf($settings->codeWorkflow) as $run) {
				if ($run['status'] !== 'completed' || $run['conclusion'] === 'cancelled') {
					continue;
				}

				$code['last'] = [
					'date'   => static::date($run['updated_at'], $this->kirby),
					'by'     => $run['triggering_actor']['login'] ?? $run['actor']['login'] ?? null,
					'failed' => $run['conclusion'] !== 'success',
				];
				break;
			}
		}

		return [
			'content' => $content,
			'publish' => ($permissions['actions'] ?? null) === 'write',
			'code'    => $code,
		];
	}

	/** The reason is extra – without it („Failed“ alone) the status still shows */
	protected function failureMessage(int $runId): string|null
	{
		try {
			return $this->github->failureMessage($runId);
		} catch (\Throwable) {
			return null;
		}
	}

	/** Date and time in the language of the Panel account („30. Oktober 2026, 14:12“) */
	public static function date(string $iso, App $kirby): string
	{
		$time   = strtotime($iso);
		$locale = $kirby->user()?->language() ?? 'en';

		if (class_exists('IntlDateFormatter') === true) {
			$format = new \IntlDateFormatter($locale, \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT, date_default_timezone_get());
			return $format->format($time);
		}

		return date('Y-m-d H:i', $time);
	}

	/** „Put Staging Live · Anna Muster“ → „Anna Muster“, otherwise the GitHub account that started it */
	protected static function person(array $run): string|null
	{
		$title = $run['display_title'] ?? '';
		$pos   = mb_strrpos($title, ' · ');

		if ($pos !== false) {
			return mb_substr($title, $pos + 3);
		}

		return $run['triggering_actor']['login'] ?? $run['actor']['login'] ?? null;
	}
}
