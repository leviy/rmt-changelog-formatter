<?php
declare(strict_types=1);

namespace Liip\RMT\Changelog\Formatter;

use Liip\RMT\Context;
use Liip\RMT\Exception;
use Liip\RMT\Version\Persister\VcsTagPersister;
use RuntimeException;
use function array_keys;
use function array_map;
use function array_merge;
use function array_slice;
use function get_class;
use function implode;
use function preg_grep;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_replace;

final class PullRequestChangelogFormatter
{
    private const GITHUB_PULL_URL = 'https://github.com/{repo}/pull/%d';

    private const GITHUB_COMPARE_URL = 'https://github.com/{repo}/compare/%s...%s';

    private const GITHUB_ISSUE_URL = 'https://github.com/{repo}/issues/$1';

    private const HEADER = [
        '# Changelog',
        'All notable changes to this project will be documented in this file.',
        '',
        'The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)',
        'and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).',
        '',
    ];

    /**
     * @var VcsTagPersister
     */
    private $versionPersister;

    /**
     * @var string
     */
    private $pullRequestPattern = 'Merge pull request #([0-9]+) from .*';

    /**
     * @var string
     */
    private $pullRequestUrl;

    /**
     * @var string
     */
    private $compareUrl;

    /**
     * @var string
     */
    private $issuePattern = '#([0-9]+)';

    /**
     * @var string
     */
    private $issueUrl;

    public function __construct()
    {
        $this->versionPersister = Context::get('version-persister');

        if (!$this->versionPersister instanceof VcsTagPersister) {
            throw new RuntimeException(
                sprintf('version-persister should be vcs-tag, "%s" given', get_class($this->versionPersister))
            );
        }
    }

    public function updateExistingLines(array $lines, string $version, string $comment, array $options): array
    {
        // automatically set default URLs for GitHub repositories
        if (isset($options['repo'])) {
            $this->pullRequestUrl = str_replace('{repo}', $options['repo'], self::GITHUB_PULL_URL);
            $this->compareUrl = str_replace('{repo}', $options['repo'], self::GITHUB_COMPARE_URL);
            $this->issueUrl = str_replace('{repo}', $options['repo'], self::GITHUB_ISSUE_URL);
        }

        $this->pullRequestPattern = $options['pull-request-pattern'] ?? $this->pullRequestPattern;
        $this->pullRequestUrl = $options['pull-request-url'] ?? $this->pullRequestUrl;
        $this->compareUrl = $options['compare-url'] ?? $this->compareUrl;
        $this->issuePattern = $options['issue-pattern'] ?? $this->issuePattern;
        $this->issueUrl = $options['issue-url'] ?? $this->issueUrl;

        $currentTag = $this->versionPersister->getCurrentVersionTag();

        $changes = array_map(
            function (array $pullRequest): string {
                return sprintf(
                    '- %s (pull request [#%s](%s))',
                    $this->linkToIssue($pullRequest['title']),
                    $pullRequest['number'],
                    $this->getPullRequestUrl($pullRequest['number'])
                );
            },
            $this->getMergedPullRequestsSince($currentTag)
        );

        $compareUrl = $this->getVersionCompareUrl($version, $currentTag);

        return array_merge(
            self::HEADER,
            [sprintf('## [%s] - %s', $version, date('Y-m-d'))],
            $changes,
            [''],
            $this->getCurrentBody($lines),
            [sprintf('[%s]: %s', $version, $compareUrl)],
            ['']
        );
    }

    private function getPullRequestUrl(string $id): string
    {
        return sprintf($this->pullRequestUrl, $id);
    }

    private function linkToIssue(string $title): string
    {
        $pattern = '/' . $this->issuePattern . '/';

        return preg_replace($pattern, '[$0](' . $this->issueUrl . ')', $title);
    }

    private function getMergedPullRequestsSince(string $oldTag): array
    {
        $command = implode(
            ' ',
            [
                'log',
                $oldTag . '..HEAD',
                '--grep="' . $this->pullRequestPattern . '"',
                '--extended-regexp',
                '--format="%s%b"',
            ]
        );

        $mergedPullRequests = $this->executeGitCommand($command);

        $phpPattern = '/' . $this->pullRequestPattern . '/';

        $mergedPullRequests = preg_grep($phpPattern, $mergedPullRequests);

        return array_map(
            function (string $commitLine) use ($phpPattern): array {
                preg_match($phpPattern, $commitLine, $matches);

                return [
                    'number' => $matches[1],
                    'title' => str_replace($matches[0], '', $commitLine),
                ];
            },
            $mergedPullRequests
        );
    }

    private function executeGitCommand(string $command): array
    {
        $command = 'git ' . $command;
        exec($command, $result, $exitCode);
        if ($exitCode !== 0) {
            throw new Exception(
                'Error while executing git command: ' . $command . "\n" . implode("\n", $result)
            );
        }

        return $result;
    }

    private function getVersionCompareUrl(string $version, string $oldTag): string
    {
        $compareUrl = sprintf($this->compareUrl, $oldTag, $this->versionPersister->getTagFromVersion($version));

        return $compareUrl;
    }

    private function getCurrentBody(array $lines): array
    {
        $releaseHeaders = preg_grep('/^## \[/', $lines);
        $releaseHeaderLineNumbers = array_keys($releaseHeaders);
        $offset = reset($releaseHeaderLineNumbers);

        if (!$offset) {
            return [];
        }

        return array_slice($lines, $offset);
    }
}
