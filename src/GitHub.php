<?php

namespace Kirbydesk\Workflow;

use Kirby\Cms\App;
use Kirby\Exception\Exception;
use Kirby\Http\Remote;

/**
 * Starts the workflow and reads its runs – as the GitHub App, with a short-lived installation token.
 */
class GitHub
{
	protected const API = 'https://api.github.com';

	public function __construct(
		protected App $kirby,
		protected Settings $settings
	) {
	}

	/** Starts the workflow; the name of the Panel account goes along if the settings name an input for it */
	public function dispatch(string|null $user): void
	{
		$inputs = $this->settings->inputs;

		if ($this->settings->userInput !== null && $user !== null) {
			$inputs[$this->settings->userInput] = $user;
		}

		$this->request('POST', $this->workflowPath() . '/dispatches', [
			'ref'    => $this->settings->ref,
			'inputs' => (object)$inputs
		]);
	}

	/** Latest runs of the publishing workflow started by hand (newest first) */
	public function runs(int $limit = 20): array
	{
		return $this->runsWithTotal($limit)['runs'];
	}

	/** Latest runs and how many there are in total */
	public function runsWithTotal(int $limit = 20): array
	{
		$data = $this->request('GET', $this->workflowPath() . '/runs?event=workflow_dispatch&per_page=' . $limit);
		return ['runs' => $data['workflow_runs'] ?? [], 'total' => $data['total_count'] ?? 0];
	}

	/** Latest runs of any workflow in the repository of the settings (newest first) */
	public function runsOf(string $workflow, int $limit = 5): array
	{
		$data = $this->request('GET', '/repos/' . $this->settings->repository . '/actions/workflows/' . rawurlencode($workflow) . '/runs?per_page=' . $limit);
		return $data['workflow_runs'] ?? [];
	}

	/** All files of a repository at a branch: path => blob SHA */
	public function tree(string $repository, string $ref): array
	{
		$data  = $this->request('GET', '/repos/' . $repository . '/git/trees/' . rawurlencode($ref) . '?recursive=1');
		$files = [];

		foreach ($data['tree'] ?? [] as $item) {
			if ($item['type'] === 'blob') {
				$files[$item['path']] = $item['sha'];
			}
		}

		return $files;
	}

	/** One commit with its changed files */
	public function commit(string $repository, string $sha): array
	{
		return $this->request('GET', '/repos/' . $repository . '/commits/' . rawurlencode($sha));
	}

	/** Latest commits of a repository (newest first) */
	public function commits(string $repository, int $limit = 30): array
	{
		return $this->request('GET', '/repos/' . $repository . '/commits?per_page=' . $limit);
	}

	/** Can the App read this repository? */
	public function canRead(string $repository): bool
	{
		try {
			$this->request('GET', '/repos/' . $repository);
			return true;
		} catch (Exception) {
			return false;
		}
	}

	/** Permissions of the App's installation (`actions` => `write`, `contents` => `read` …) */
	public function permissions(): array
	{
		return $this->access()['permissions'];
	}

	/** First error message of a failed run (`::error::` annotations), if there is one */
	public function failureMessage(int $runId): string|null
	{
		$jobs = $this->request('GET', '/repos/' . $this->settings->repository . '/actions/runs/' . $runId . '/jobs');

		foreach ($jobs['jobs'] ?? [] as $job) {
			if (($job['conclusion'] ?? null) !== 'failure') {
				continue;
			}

			$annotations = $this->request('GET', '/repos/' . $this->settings->repository . '/check-runs/' . $job['id'] . '/annotations');

			foreach ($annotations as $annotation) {
				if (($annotation['annotation_level'] ?? null) === 'failure' && str_starts_with($annotation['message'] ?? '', 'Process completed') === false) {
					return $annotation['message'];
				}
			}
		}

		return null;
	}

	protected function workflowPath(): string
	{
		return '/repos/' . $this->settings->repository . '/actions/workflows/' . rawurlencode($this->settings->workflow);
	}

	protected function request(string $method, string $path, array|null $body = null): array
	{
		$response = Remote::request(static::API . $path, [
			'method'  => $method,
			'headers' => [
				'Accept'               => 'application/vnd.github+json',
				'Authorization'        => 'Bearer ' . $this->token(),
				'User-Agent'           => 'kirby-workflow',
				'X-GitHub-Api-Version' => '2022-11-28',
				'Content-Type'         => 'application/json',
			],
			'data'    => $body !== null ? json_encode($body) : null,
		]);

		if ($response->code() >= 300) {
			throw new Exception(message: static::message($response));
		}

		return $response->json() ?? [];
	}

	protected function token(): string
	{
		return $this->access()['token'];
	}

	/** Installation token of the App and its permissions – cached until shortly before the token expires */
	protected function access(): array
	{
		$cache = $this->kirby->cache('kirbydesk.workflow');
		$key   = 'access-' . md5($this->settings->repository . $this->settings->appId);

		if ($access = $cache->get($key)) {
			return $access;
		}

		$jwt          = $this->jwt();
		$installation = static::call('GET', '/repos/' . $this->settings->repository . '/installation', $jwt);
		$response     = static::call('POST', '/app/installations/' . $installation['id'] . '/access_tokens', $jwt);
		$access       = ['token' => $response['token'], 'permissions' => $response['permissions'] ?? []];

		// Valid for an hour – keep it for 50 minutes
		$cache->set($key, $access, 50);

		return $access;
	}

	/** Signed with the App's key – valid for nine minutes, a minute back against clock drift */
	protected function jwt(): string
	{
		$encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
		$now    = time();
		$data   = $encode(['alg' => 'RS256', 'typ' => 'JWT']) . '.' .
			$encode(['iat' => $now - 60, 'exp' => $now + 540, 'iss' => $this->settings->appId]);

		if (openssl_sign($data, $signature, $this->settings->privateKey, OPENSSL_ALGO_SHA256) === false) {
			throw new Exception(message: 'The key of the GitHub App could not be read.');
		}

		return $data . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
	}

	protected static function call(string $method, string $path, string $jwt): array
	{
		$response = Remote::request(static::API . $path, [
			'method'  => $method,
			'headers' => [
				'Accept'               => 'application/vnd.github+json',
				'Authorization'        => 'Bearer ' . $jwt,
				'User-Agent'           => 'kirby-workflow',
				'X-GitHub-Api-Version' => '2022-11-28',
			],
		]);

		if ($response->code() >= 300) {
			throw new Exception(message: static::message($response));
		}

		return $response->json() ?? [];
	}

	/** GitHub's own answer – shown as it is */
	protected static function message(Remote $response): string
	{
		$message = $response->json()['message'] ?? null;
		return 'GitHub: ' . ($message ?? 'HTTP ' . $response->code());
	}
}
