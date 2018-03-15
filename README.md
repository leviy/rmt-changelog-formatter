# Pull request changelog formatter

This changelog formatter for [RMT](https://github.com/liip/RMT) allows you to
automatically generate and update a changelog based on merged pull requests.

## Installation

```bash
composer require --dev leviy/rmt-changelog-formatter
``` 

## Configuration

This changelog formatter requires Git as VCS and the `vcs-tag` version
persister. It is also recommended to configure the `vcs-commit` pre-release
action to commit the changelog before tagging a new version, and to add the 
`vcs-publish` post-release action to push it.

Add a `changelog-update` pre-release action to your `.rmt.yml`, with `format:
pullRequest`:

```yaml
vcs: git

version-generator: semantic
version-persister: vcs-tag

pre-release-actions:
  changelog-update:
    format: pullRequest
  vcs-commit: ~

post-release-actions:
  vcs-publish:
    ask-confirmation: true
```

### GitHub

If you are working with GitHub, set the `repo` option to automatically configure
the pull request, compare, and issue URLs:

```yaml
pre-release-actions:
  changelog-update:
    format: pullRequest
    repo: leviy/rmt-changelog-formatter
```

### JIRA

If you are using JIRA as your issue tracking system, you can configure this
formatter to automatically detect and link to your issues in JIRA. To do so,
add the `issue-pattern` and `issue-url` options:

```yaml
pre-release-actions:
  changelog-update:
    format: pullRequest
    issue-pattern: '(JIR-[0-9]+)'
    issue-url: 'https://jira.example.com/browse/$1'
```

### Bitbucket

If you are using Bitbucket, set the `pull-request-pattern`, `pull-request-url`
and `compare-url` options:

```yaml
pre-release-actions:
  changelog-update:
    format: pullRequest
    pull-request-pattern: 'Merged in .* \(pull request #([0-9]+)\)'
    pull-request-url: 'https://bitbucket.org/organization/repository/pull-requests/%s'
    compare-url: 'https://bitbucket.org/organization/repository/branches/compare/%2$s..%1$s#pull-requests'
```

### Full configuration reference

```yaml
pre-release-actions:
  changelog-update:
    format: pullRequest
    file: CHANGELOG.md
    repo: organization/repository # has no effect if pull-request-url, compare-url and issue-url are set
    pull-request-pattern: 'Merged in .* \(pull request #([0-9]+)\)'
    pull-request-url: 'https://bitbucket.org/organization/repository/pull-requests/%s'
    compare-url: 'https://bitbucket.org/organization/repository/branches/compare/%2$s..%1$s#pull-requests'
    issue-pattern: '(JIR-[0-9]+)'
    issue-url: 'https://jira.example.com/browse/$1'
```

Note that this formatter ignores some options that are available for other formatters, such as `dump-commits` and
`exclude-merge-commits`.
