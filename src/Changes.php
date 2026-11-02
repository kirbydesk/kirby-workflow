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

	/** Not live yet: pages and files with `status` changed / new / deleted, in the order of the content folder */
	public function toArray(): array
	{
		$published = $this->github->tree($this->settings->contentRepository, $this->settings->contentRef);
		$local     = $this->localFiles();
		$paths     = [];

		foreach ($local as $path => $sha) {
			if (isset($published[$path]) === false) {
				$paths[$path] = 'new';
			} elseif ($published[$path] !== $sha) {
				$paths[$path] = 'changed';
			}
		}

		foreach (array_diff_key($published, $local) as $path => $sha) {
			$paths[$path] = 'deleted';
		}

		return $this->group($paths);
	}

	/** What one publish brought live – the files of its commit in the content repository */
	public function fromCommit(string $sha): array
	{
		// A commit never changes – only its files are kept. Titles and links are described anew each time, pages
		// may have been renamed or moved since
		$files = $this->kirby->cache('kirbydesk.workflow')->getOrSet('commit-files-' . $sha, fn () => array_map(
			fn ($file) => array_intersect_key($file, array_flip(['filename', 'status', 'previous_filename'])),
			$this->github->commit($this->settings->contentRepository, $sha)['files'] ?? []
		));
		$paths = [];

		foreach ($files as $file) {
			// A renamed file: gone under the old name, new under the new one
			if ($file['status'] === 'renamed' && isset($file['previous_filename']) === true) {
				$paths[$file['previous_filename']] = 'deleted';
			}

			$paths[$file['filename']] = match ($file['status']) {
				'added'   => 'new',
				'removed' => 'deleted',
				'renamed' => 'new',
				default   => 'changed',
			};
		}

		return $this->group($paths);
	}

	/** Changed files grouped by page and file, described, in the order of the content folder */
	protected function group(array $paths): array
	{
		$items = [];

		foreach ($paths as $path => $status) {
			$this->note($items, $path, $status);
		}

		// A page is new or deleted only if all of its text files are – otherwise it changed
		$items = array_map(function ($item) {
			$statuses       = array_unique($item['statuses']);
			$item['status'] = count($statuses) === 1 ? $statuses[0] : 'changed';
			unset($item['statuses']);
			return [...$this->describe($item), 'sort' => $item['directory'] . '/' . ($item['name'] ?? '')];
		}, $items);

		// Parents before their children, files after their page
		usort($items, fn ($a, $b) => strcmp($a['sort'], $b['sort']));

		return array_values(array_map(function ($item) {
			unset($item['sort']);
			return $item;
		}, $items));
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

	/** Title, crumbs of the parent pages (Panel links like in kirby-explorer), Panel link and the page on the website */
	protected function describe(array $item): array
	{
		$site = $this->kirby->site();
		$id   = static::pageId($item['directory']);
		$page = $id === '' ? null : $site->findPageOrDraft($id);

		if ($item['type'] === 'file') {
			$file   = $id === '' ? $site->file($item['name']) : $page?->file($item['name']);
			$exists = $item['status'] !== 'deleted' && $file !== null;

			return [
				'type'   => 'file',
				'status' => $item['status'],
				'title'  => $item['name'],
				// The page it belongs to is the last crumb
				'crumbs' => $this->crumbs($id, includeSelf: true),
				'panel'  => $exists ? $file->panel()->url() : null,
				'url'    => $exists ? $file->url() : null,
				// Preview like in Kirby's file lists – small, the list only shows it as a tile
				'image'  => $exists && $file->type() === 'image' ? $file->thumb(['width' => 96, 'height' => 96, 'crop' => true])->url() : null,
			];
		}

		$exists = $item['status'] !== 'deleted';

		if ($id === '') {
			return [
				'type'   => 'page',
				'status' => $item['status'],
				'title'  => $site->title()->value(),
				'crumbs' => [],
				'panel'  => $site->panel()->url(),
				'url'    => $site->url(),
			];
		}

		return [
			'type'   => 'page',
			'status' => $item['status'],
			'title'  => $page?->title()->value() ?? basename($id),
			'crumbs' => $this->crumbs($id, includeSelf: false),
			'panel'  => $exists ? $page?->panel()->url() : null,
			'url'    => $exists ? static::url($page) : null,
		];
	}

	/** Site and the parent pages that still exist here – each with its Panel link */
	protected function crumbs(string $id, bool $includeSelf): array
	{
		$site   = $this->kirby->site();
		$crumbs = [['title' => $site->title()->value(), 'panel' => $site->panel()->url()]];
		$parts  = $id === '' ? [] : explode('/', $id);

		if ($includeSelf === false) {
			array_pop($parts);
		}

		$path = [];
		foreach ($parts as $part) {
			$path[] = $part;
			$page   = $site->findPageOrDraft(implode('/', $path));

			if ($page === null) {
				break;
			}

			$crumbs[] = ['title' => $page->title()->value(), 'panel' => $page->panel()->url()];
		}

		return $crumbs;
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
