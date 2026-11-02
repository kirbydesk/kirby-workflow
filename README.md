# Kirby Workflow

Publish content from Staging to the live website, right from the Kirby Panel – and more editorial workflows to come.

Editors work on Staging. The Panel gets a new area **Workflow**: **Publish Content** starts a GitHub Actions workflow that brings the saved content live. The code on the live website stays as it is.

> Early version – feedback welcome.

## What it shows

The area **Workflow** has three tabs:

- **Changes** – pages and files that are not live yet: new, changed or deleted. Compared with the content repository like Git does, so unsaved changes never count.
- **History** – every publish with date and person. A click shows what it brought live. Failed publishes show GitHub's reason.
- **GitHub** – the repositories and whether the GitHub App may compare and publish. Also the last publish of the code.

Without settings the plugin does nothing and leaves the Panel untouched – no menu entry, no area.

## Requirements

- Kirby 5
- A GitHub repository with a workflow that can be started (`workflow_dispatch`)
- A GitHub App installed on that repository with “Actions: write” (and “Contents: read” for the content repository)
- Optional: a separate repository with the published content – needed for **Changes** and for what a publish brought live

## Installation

```
composer require kirbydesk/kirby-workflow
```

## Settings

The settings live outside the repository, so they exist only where content is edited (usually Staging). By default the plugin reads `.workflow/settings.json` in the project folder (the folder above `site`):

```json
{
	"github": {
		"repository": "owner/website",
		"workflow": "promote.yml",
		"ref": "main",
		"inputs": { "mode": "content" },
		"userInput": "published_by",
		"appId": 123456,
		"privateKey": "github-app.pem",
		"contentRepository": "owner/website-content",
		"contentRef": "main",
		"codeWorkflow": "deploy-staging.yml"
	}
}
```

| Key | |
| --- | --- |
| `repository` | Repository with the workflow (required) |
| `workflow` | File name of the workflow that publishes (required) |
| `ref` | Branch the workflow runs on – default `main` |
| `inputs` | Inputs passed to the workflow |
| `userInput` | Input that gets the name of the Panel account who publishes. With a run name like `Put Staging Live · ${{ inputs.published_by }}` the history shows that name |
| `appId` | ID of the GitHub App (required) |
| `privateKey` | Private key of the GitHub App – a path relative to the settings or absolute. Default `github-app.pem` |
| `contentRepository` | Repository with the published content – without it there is no tab **Changes** |
| `contentRef` | Its branch – default `main` |
| `codeWorkflow` | Workflow that deploys the code – shown under **GitHub** |

Keep the folder `.workflow` away from the web: Kirby's `.htaccess` blocks folders starting with a dot, a separate public folder does too. Make the key readable only for the account running PHP.

Another place for the settings:

```php
// site/config/config.php
return [
	'kirbydesk.workflow.settings' => '/path/to/settings.json',
];
```

[KirbyDesk](https://kirbydesk.com) sets all of this up for you.

## Languages

The Panel texts come in all languages of the Kirby Panel. They were translated carefully, but mostly not by native speakers – corrections are welcome.

## License

MIT
