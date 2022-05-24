<?php

declare(strict_types=1);

use Symplify\MonorepoSplit\Config;
use Symplify\MonorepoSplit\ConfigFactory;
use Symplify\MonorepoSplit\Exception\ConfigurationException;
use Symplify\MonorepoSplit\Tag;

require_once __DIR__ . '/src/autoload.php';

note('Resolving configuration...');

$configFactory = new ConfigFactory();
try {
    $config = $configFactory->create(getenv());
} catch (ConfigurationException $configurationException) {
    error($configurationException->getMessage());
    exit(0);
}

setupGitCredentials($config);


$cloneDirectory = sys_get_temp_dir() . '/monorepo_split/clone_directory';
$buildDirectory = sys_get_temp_dir() . '/monorepo_split/build_directory';

$hostRepositoryOrganizationName = $config->getGitRepository();

// info
$clonedRepository='https://' . $hostRepositoryOrganizationName;
$cloningMessage = sprintf('Cloning "%s" repository to "%s" directory', $clonedRepository, $cloneDirectory);
note($cloningMessage);

$commandLine = 'git clone -- https://' . $config->getAccessToken() . '@' . $hostRepositoryOrganizationName . ' ' . $cloneDirectory;
exec_with_note($commandLine);

note('Cleaning destination repository of old files');
// We're only interested in the .git directory, move it to $TARGET_DIR and use it from now on
mkdir($buildDirectory . '/.git', 0777, true);

$copyGitDirectoryCommandLine = sprintf('cp -r %s %s', $cloneDirectory . '/.git', $buildDirectory);
exec($copyGitDirectoryCommandLine, $outputLines, $exitCode);

if ($exitCode === 1) {
    die('Command failed');
}

// cleanup old unused data to avoid pushing them
exec('rm -rf ' . $cloneDirectory);
// exec('rm -rf .git');

// WARNING! this function happen before we change directory
// if we do this in split repository, the original hash is missing there and it will fail
$commitMessage = createCommitMessage($config->getCommitHash());

$formerWorkingDirectory = getcwd();
chdir($buildDirectory);
exec_with_output_print('git remote -v');
exec_with_output_print('git branch');
// changing branch
exec('git branch', $branches);
$branches = \array_map(static fn (string $branch) => trim(str_replace('*', '', $branch)), $branches);
note('Founded branches:');

note((string) count($branches));

print_array($branches);
$branchExist = \in_array($config->getBranch()->getName(), $branches, true);
$branchExist ?
    note(sprintf('Branch %s already exist.', $config->getBranch()->getName())) :
    note(sprintf('Branch %s is not exist.', $config->getBranch()->getName()));

exec('git tag', $tags);
note('Founded tags:');
print_array($tags);

if (!$branchExist) {
    if ($config->getTag()) {
        note('Founding branch by latest tag.');

        $recentTag = $config->getBranch()->findMostRecentTag(\array_map(static fn(string $tag) => \trim($tag), $tags));
    } else {
        note('Founding last minor branch.');

        $parts = \explode(Tag::DELIMITER, $config->getBranch()->getName());

        if (isset($parts[0]) && isset($parts[1])) {
            for ($i = (int) $parts[1]; $i > 0; $i--) {
                if (\in_array($parts[0] . Tag::DELIMITER . $i, $branches, true)) {
                    $latestMinorBranch = $parts[0] . Tag::DELIMITER . $i;
                    break;
                }
            }
        }
    }
}

switch (true) {
    // branch already exist
    case $branchExist:
        $branch = $config->getBranch()->getName();
        note(\sprintf('Branch %s founded.', $branch));
        exec_with_note(\sprintf('git checkout %s', $config->getBranch()->getName()));
        break;
    // empty repository. Don't have any branches or tags
    case $branches === [] && empty($recentTag):
        note('New repository, do nothing with branches.');
        break;
    // tag exists. Creating branch from the latest tag (e.g. branch 3.0 from the last tag 2.9.15 and new tag 3.0.0)
    case !empty($recentTag):
        note(\sprintf('The latest tag is %s.', $recentTag));
        exec_with_note(\sprintf('git branch %s %s', $config->getBranch()->getName(), $recentTag));
        exec_with_note(\sprintf('git checkout %s', $config->getBranch()->getName()));
        break;
    // from latest minor branch
    default:
        if (!empty($latestMinorBranch)) {
            note(\sprintf('Latest minor branch is %s.', $latestMinorBranch));
            exec_with_note(\sprintf('git branch %s %s', $config->getBranch()->getName(), $latestMinorBranch));
            exec_with_note(\sprintf('git checkout %s', $config->getBranch()->getName()));
        } else {
            note('The latest minor branch is not found, creating from the main branch.');
            exec_with_note(\sprintf('git branch %s', $config->getBranch()->getName()));
            exec_with_note(\sprintf('git checkout %s', $config->getBranch()->getName()));
        }
}

chdir($formerWorkingDirectory);

// copy the package directory including all hidden files to the clone dir
// make sure the source dir ends with `/.` so that all contents are copied (including .github etc)
$copyMessage = sprintf('Copying contents to git repo of "%s" branch', $config->getCommitHash());
note($copyMessage);
$commandLine = sprintf('cp -ra %s %s', $config->getPackageDirectory() . '/.', $buildDirectory);
exec($commandLine);

note('Files that will be pushed');
list_directory_files($buildDirectory);

$restoreChdirMessage = sprintf('Changing directory from "%s" to "%s"', $formerWorkingDirectory, $buildDirectory);
note($restoreChdirMessage);

chdir($buildDirectory);

// avoids doing the git commit failing if there are no changes to be commit, see https://stackoverflow.com/a/8123841/1348344
exec_with_output_print('git status');

// "status --porcelain" retrieves all modified files, no matter if they are newly created or not,
// when "diff-index --quiet HEAD" only checks files that were already present in the project.
exec('git status --porcelain', $changedFiles);

// $changedFiles is an array that contains the list of modified files, and is empty if there are no changes.

if ($changedFiles) {
    note('Adding git commit');
    exec_with_output_print('git add .');

    $message = sprintf('Pushing git commit with "%s" message to "%s"', $commitMessage, $config->getBranch()->getName());
    note($message);

    exec("git commit --message '$commitMessage'");
    exec('git push --quiet origin ' . $config->getBranch()->getName());
} else {
    note('No files to change');
}


// push tag if present
if ($config->getTag()) {
    $message = sprintf('Publishing "%s"', (string) $config->getTag());
    note($message);

    $commandLine = sprintf('git tag %s -m "%s"', (string) $config->getTag(), $message);
    exec_with_note($commandLine);

    exec_with_note('git push --quiet origin ' . (string) $config->getTag());
}

// restore original directory to avoid nesting WTFs
chdir($formerWorkingDirectory);
$chdirMessage = sprintf('Changing directory from "%s" to "%s"', $buildDirectory, $formerWorkingDirectory);
note($chdirMessage);

function createCommitMessage(string $commitSha): string
{
    exec("git show -s --format=%B $commitSha", $outputLines);
    return $outputLines[0] ?? '';
}


function note(string $message): void
{
    echo PHP_EOL . PHP_EOL . "\033[0;33m[NOTE] " . $message . "\033[0m" . PHP_EOL . PHP_EOL;
}

function error(string $message): void
{
    echo PHP_EOL . PHP_EOL . "\033[0;31m[ERROR] " . $message . "\033[0m" . PHP_EOL . PHP_EOL;
}

function list_directory_files(string $directory): void {
    exec_with_output_print('ls -la ' . $directory);
}

/********************* helper functions *********************/

function exec_with_note(string $commandLine): void
{
    note('Running: ' . $commandLine);
    exec($commandLine);
}

function exec_with_output_print(string $commandLine): void
{
    exec($commandLine, $outputLines);
    echo implode(PHP_EOL, $outputLines);
}

function setupGitCredentials(Config $config): void
{
    if ($config->getUserName()) {
        exec('git config --global user.name ' . $config->getUserName());
    }

    if ($config->getUserEmail()) {
        exec('git config --global user.email ' . $config->getUserEmail());
    }
}

function print_array(array $data): void
{
    foreach ($data as $element) {
        note((string) $element);
    }
}
