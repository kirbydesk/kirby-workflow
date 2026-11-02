# Kirby Workflow

Publish content from Staging to the live website, right from the Kirby Panel – and more editorial workflows to come.

Editors work on Staging. A **Publish** button in the Panel starts a GitHub Actions workflow that brings the saved content live. The code on the live website stays as it is.

> Work in progress – not ready for use yet.

## Requirements

- Kirby 5
- A GitHub repository with a workflow that can be started (`workflow_dispatch`)
- A GitHub App with “Actions: write” for that repository

Without settings the plugin does nothing and leaves the Panel untouched.

[KirbyDesk](https://kirbydesk.com) sets all of this up for you. Instructions for setting it up by hand will follow.

## License

MIT
