<?php

namespace Kirbydesk\Workflow;

use Kirby\Cms\App;

/**
 * State of publishing, read from the runs of the workflow: is one running, did the latest one fail, and the
 * history – who published when (from the run name „… · Name“).
 */
class Status
{
	protected const RUNNING = ['queued', 'in_progress', 'waiting', 'requested', 'pending'];

	/** After `history()`: are there older runs than those shown? */
	public bool $more = false;

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
		$runs       = $this->github->runs()['runs'];
		$dispatched = $this->kirby->cache('kirbydesk.workflow')->get('dispatched');
		$running    = null;
		$failed     = null;

		foreach ($runs as $run) {
			if (in_array($run['status'], static::RUNNING, true) === true) {
				$running ??= $run;
				continue;
			}

			if ($run['conclusion'] === 'success') {
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
			'running' => $running !== null,
			'failed'  => $running === null && $failed !== null ? [
				'message' => $this->failureMessage($failed['id']),
			] : null,
		];
	}

	/** Every publish (newest first): date, person and – if it failed – the reason */
	public function history(int $limit = 25, string|null $contentRepository = null): array
	{
		$entries = [];
		$commits = [];

		// The commit of a publish: made in the content repository while its run ran (runs never overlap)
		if ($contentRepository !== null) {
			try {
				$commits = $this->github->commits($contentRepository, 50);
			} catch (\Throwable) {
				$commits = [];
			}
		}

		$result     = $this->github->runs($limit);
		$this->more = $result['total'] > count($result['runs']);

		foreach ($result['runs'] as $run) {
			if (in_array($run['status'], static::RUNNING, true) === true || $run['conclusion'] === 'cancelled') {
				continue;
			}

			$failed = $run['conclusion'] !== 'success';
			$commit = $failed ? null : static::commitOf($run, $commits);

			// Content unchanged: nothing went live for the content – not in the history. Failed runs stay
			// (someone wanted to publish), and without a content repository nothing can be told apart
			if ($failed === false && $commit === null && $contentRepository !== null) {
				continue;
			}

			$date      = static::date($run['updated_at'], $this->kirby);
			$person    = static::person($run);
			$entries[] = [
				'date'   => $date,
				'by'     => $person,
				'failed' => $failed,
				'reason' => $failed ? $this->failureMessage($run['id']) : null,
				'commit' => $commit,
				'title'  => $date . ($person ? ' · ' . $person : ''),
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

	/** SHA of the content commit made during a run – `null` if nothing changed (no commit) */
	protected static function commitOf(array $run, array $commits): string|null
	{
		$start = strtotime($run['run_started_at'] ?? $run['created_at']);
		$end   = strtotime($run['updated_at']);

		foreach ($commits as $commit) {
			$time = strtotime($commit['commit']['committer']['date'] ?? '');
			if ($time >= $start && $time <= $end) {
				return $commit['sha'];
			}
		}

		return null;
	}

	/** The reason is extra – without it („Failed“ alone) the status still shows. A finished run never changes,
	 so its reason is kept (also when there is none) */
	protected function failureMessage(int $runId): string|null
	{
		$cache = $this->kirby->cache('kirbydesk.workflow');
		$key   = 'failure-' . $runId;

		if (is_array($known = $cache->get($key)) === true) {
			return $known['message'];
		}

		try {
			$message = $this->github->failureMessage($runId);
		} catch (\Throwable) {
			return null;
		}

		$cache->set($key, ['message' => $message]);

		return $message;
	}

	/** Date and time in the language of the Panel account („30. Oktober 2026, 14:12“) */
	protected static function date(string $iso, App $kirby): string
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
