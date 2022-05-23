<?php

// PHP 8.0

declare(strict_types=1);

namespace Symplify\MonorepoSplit;

final class Config
{
    private Branch $branch;
    private ?Tag $tag = null;

    public function __construct(
        private string $packageDirectory,
        private string $repositoryHost,
        private string $repositoryOrganization,
        private string $repositoryName,
        private string $commitHash,
        string $branch,
        ?string $tag,
        private ?string $userName,
        private ?string $userEmail,
        private string $accessToken
    ) {
        if (!empty($tag)) {
            $this->tag = new Tag($tag);
        }

        $this->branch = new Branch($branch, $this->tag);
    }

    public function getPackageDirectory(): string
    {
        return $this->packageDirectory;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function getBranch(): Branch
    {
        return $this->branch;
    }

    public function getTag(): ?Tag
    {
        return $this->tag;
    }

    public function getCommitHash(): string
    {
        return $this->commitHash;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getGitRepository(): string
    {
        return $this->repositoryHost . '/' . $this->repositoryOrganization . '/' . $this->repositoryName . '.git';
    }
}
