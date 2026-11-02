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

	/** Latest runs started by hand (newest first) */
	public function runs(int $limit = 20): array
	{
		$data = $this->request('GET', $this->workflowPath() . '/runs?event=workflow_dispatch&per_page=' . $limit);
		return $data['workflow_runs'] ?? [];
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

	/** Installation token of the App – cached until shortly before it expires */
	protected function token(): string
	{
		$cache = $this->kirby->cache('kirbydesk.workflow');
		$key   = 'token-' . md5($this->settings->repository . $this->settings->appId);

		if ($token = $cache->get($key)) {
			return $token;
		}

		$jwt          = $this->jwt();
		$installation = static::call('GET', '/repos/' . $this->settings->repository . '/installation', $jwt);
		$access       = static::call('POST', '/app/installations/' . $installation['id'] . '/access_tokens', $jwt);

		// Valid for an hour – keep it for 50 minutes
		$cache->set($key, $access['token'], 50);

		return $access['token'];
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
