<?php

namespace Kirbydesk\Workflow;

use Kirby\Cms\App;
use Kirby\Http\Remote;
use Throwable;

/**
 * Can the settings – and with them the key of the GitHub App – be opened on the web? Only possible if they lie in
 * the web root (Kirby's root `index`): a project without a separate public folder, on a server that doesn't block
 * folders starting with a dot (Kirby's `.htaccess` does, nginx and others don't by themselves).
 */
class Exposure
{
	/** The address of the settings if the server hands them out – otherwise `null`. Asked once an hour */
	public static function url(App $kirby): string|null
	{
		$file = realpath(Settings::file($kirby));
		$root = realpath($kirby->root('index'));

		// Outside the web root (e.g. next to a public folder): no server hands it out
		if ($file === false || $root === false || str_starts_with($file, $root . '/') === false) {
			return null;
		}

		$cache = $kirby->cache('kirbydesk.workflow');

		if (is_array($known = $cache->get('exposed')) === true) {
			return $known['url'];
		}

		$path = implode('/', array_map('rawurlencode', explode('/', substr($file, strlen($root) + 1))));
		$url  = rtrim($kirby->url('index'), '/') . '/' . $path;

		try {
			$response = Remote::get($url, ['timeout' => 5]);
			$open     = $response->code() === 200 && str_contains($response->content() ?? '', '"github"');
		} catch (Throwable) {
			// No answer (e.g. a development server that serves one request at a time): nothing handed out
			$open = false;
		}

		$cache->set('exposed', ['url' => $open ? $url : null], 60);

		return $open ? $url : null;
	}
}
