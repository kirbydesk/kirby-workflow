<?php

namespace Kirbydesk\Workflow;

use Kirby\Cms\App;
use Kirby\Cms\Page;

/**
 * What is not live yet: the content here compared with the last published state in the content repository.
 * Compared like Git (blob SHA per file), so deleted pages are found too. Unsaved changes (`_changes`) and lock
 * files never leave this server and don't count – like the workflow when it fetches the content.
 */
class Changes
{
	public function __construct(
		protected App $kirby,
		protected GitHub $github,
		protected Settings $settings
	) {
	}

	/** Pages and files with `status` changed / new / deleted, sorted by path */
	public function toArray(): array
	{
		$published = $this->github->tree($this->settings->contentRepository, $this->settings->contentRef);
		$local     = $this->localFiles();
		$items     = [];

		foreach ($local as $path => $sha) {
			if (isset($published[$path]) === false) {
				$this->note($items, $path, 'new');
			} elseif ($published[$path] !== $sha) {
				$this->note($items, $path, 'changed');
			}
		}

		foreach (array_diff_key($published, $local) as $path => $sha) {
			$this->note($items, $path, 'deleted');
		}

		// A page is new or deleted only if all of its text files are – otherwise it changed
		$items = array_map(function ($item) {
			$statuses       = array_unique($item['statuses']);
			$item['status'] = count($statuses) === 1 ? $statuses[0] : 'changed';
			unset($item['statuses']);
			return $this->describe($item);
		}, $items);

		usort($items, fn ($a, $b) => strcmp($a['path'], $b['path']));

		return array_values($items);
	}

	/** Groups a changed file under its page or as a file of a page */
	protected function note(array &$items, string $path, string $status): void
	{
		$directory = dirname($path) === '.' ? '' : dirname($path);
		$name      = basename($path);

		// Text files: the page itself (`article.txt`, `article.de.txt`) or the meta data of a file (`photo.jpg.txt`)
		if (str_ends_with($name, '.txt') === true) {
			$base = preg_replace('/(\.[a-z]{2}(-[a-z]{2})?)?\.txt$/i', '', $name);

			if (str_contains($base, '.') === true) {
				$key = $directory . '/' . $base;
				$items[$key] ??= ['type' => 'file', 'directory' => $directory, 'name' => $base, 'statuses' => []];
				// New or removed meta data: the file itself changed
				$items[$key]['statuses'][] = 'changed';
				return;
			}

			$key = $directory;
			$items[$key] ??= ['type' => 'page', 'directory' => $directory, 'statuses' => []];
			$items[$key]['statuses'][] = $status;
			return;
		}

		$key = $directory . '/' . $name;
		$items[$key] ??= ['type' => 'file', 'directory' => $directory, 'name' => $name, 'statuses' => []];
		$items[$key]['statuses'][] = $status;
	}

	/** Title, path and – if it exists here – a link */
	protected function describe(array $item): array
	{
		$id   = static::pageId($item['directory']);
		$page = $id === '' ? null : $this->kirby->site()->findPageOrDraft($id);

		if ($item['type'] === 'file') {
			$file = $id === '' ? $this->kirby->site()->file($item['name']) : $page?->file($item['name']);

			return [
				'type'   => 'file',
				'status' => $item['status'],
				'title'  => $item['name'],
				'path'   => $id,
				'url'    => $item['status'] !== 'deleted' ? $file?->url() : null,
			];
		}

		if ($id === '') {
			return [
				'type'   => 'page',
				'status' => $item['status'],
				'title'  => $this->kirby->site()->title()->value(),
				'path'   => '/',
				'url'    => $this->kirby->site()->url(),
			];
		}

		return [
			'type'   => 'page',
			'status' => $item['status'],
			'title'  => $page?->title()->value() ?? basename($id),
			'path'   => $id,
			'url'    => $item['status'] !== 'deleted' ? static::url($page) : null,
		];
	}

	protected static function url(Page|null $page): string|null
	{
		if ($page === null) {
			return null;
		}

		return $page->isDraft() === true ? $page->previewUrl() : $page->url();
	}

	/** `1_aktuelles/_drafts/herbst` → `aktuelles/herbst` */
	protected static function pageId(string $directory): string
	{
		if ($directory === '') {
			return '';
		}

		$segments = array_filter(explode('/', $directory), fn ($segment) => $segment !== '_drafts');
		$segments = array_map(fn ($segment) => preg_replace('/^\d+_/', '', $segment), $segments);

		return implode('/', $segments);
	}

	/** All content files here: path relative to the content folder => blob SHA (cached by size and time) */
	protected function localFiles(): array
	{
		$root  = $this->kirby->root('content');
		$cache = $this->kirby->cache('kirbydesk.workflow');
		$known = $cache->get('hashes') ?? [];
		$files = [];
		$seen  = [];

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
				fn ($file) => in_array($file->getFilename(), ['_changes', '.git', '.lock', '.DS_Store'], true) === false
			)
		);

		foreach ($iterator as $file) {
			$path = substr($file->getPathname(), strlen($root) + 1);
			$sign = $file->getSize() . '-' . $file->getMTime();

			if (($known[$path]['sign'] ?? null) !== $sign) {
				$data         = file_get_contents($file->getPathname());
				$known[$path] = ['sign' => $sign, 'sha' => sha1('blob ' . strlen($data) . "\0" . $data)];
			}

			$files[$path] = $known[$path]['sha'];
			$seen[$path]  = true;
		}

		$cache->set('hashes', array_intersect_key($known, $seen));

		return $files;
	}
}
