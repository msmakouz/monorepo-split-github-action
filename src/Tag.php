<?php

declare(strict_types=1);

namespace Symplify\MonorepoSplit;

use Symplify\MonorepoSplit\Exception\InvalidTagException;

// code from phar-io/version
final class Tag implements \Stringable
{
    public const DELIMITER = '.';

    private int $major;
    private int $minor;
    private int $patch;
    private ?PreReleaseSuffix $preReleaseSuffix = null;

    public function __construct(
        string $tag
    ) {
        $this->ensureVersionStringIsValid($tag);
    }

    public function getMajor(): int
    {
        return $this->major;
    }

    public function getMinor(): int
    {
        return $this->minor;
    }

    public function getPatch(): int
    {
        return $this->patch;
    }

    public function getPreReleaseSuffix(): ?PreReleaseSuffix
    {
        return $this->preReleaseSuffix;
    }

    public function isGreaterThan(Tag $tag): bool
    {
        if ($tag->getMajor() > $this->getMajor()) {
            return false;
        }

        if ($tag->getMajor() < $this->getMajor()) {
            return true;
        }

        if ($tag->getMinor() > $this->getMinor()) {
            return false;
        }

        if ($tag->getMinor() < $this->getMinor()) {
            return true;
        }

        if ($tag->getPatch() > $this->getPatch()) {
            return false;
        }

        if ($tag->getPatch() < $this->getPatch()) {
            return true;
        }

        if (!$tag->hasPreReleaseSuffix() && !$this->hasPreReleaseSuffix()) {
            return false;
        }

        if ($tag->hasPreReleaseSuffix() && !$tag->hasPreReleaseSuffix()) {
            return true;
        }

        if (!$tag->hasPreReleaseSuffix() && $this->hasPreReleaseSuffix()) {
            return false;
        }

        return $this->getPreReleaseSuffix()->isGreaterThan($tag->getPreReleaseSuffix());
    }

    public function hasPreReleaseSuffix(): bool
    {
        return $this->preReleaseSuffix !== null;
    }

    public function __toString(): string
    {
        $str = $this->getMajor() . self::DELIMITER . $this->getMinor() . self::DELIMITER . $this->getPatch();

        if (!$this->hasPreReleaseSuffix()) {
            return $str;
        }

        return $str . '-' . (string) $this->getPreReleaseSuffix();
    }

    private function parseVersion(array $matches): void
    {
        $this->major = (int) $matches['Major'];
        $this->minor = (int) $matches['Minor'];
        $this->patch = isset($matches['Patch']) ? (int) $matches['Patch'] : 0;

        if (isset($matches['PreReleaseSuffix']) && $matches['PreReleaseSuffix'] !== '') {
            $this->preReleaseSuffix = new PreReleaseSuffix($matches['PreReleaseSuffix']);
        }
    }

    private function ensureVersionStringIsValid(string $version): void
    {
        $regex = '/^v?
            (?P<Major>0|[1-9]\d*)
            \\.
            (?P<Minor>0|[1-9]\d*)
            (\\.
                (?P<Patch>0|[1-9]\d*)
            )?
            (?:
                -
                (?<PreReleaseSuffix>(?:(dev|beta|b|rc|alpha|a|patch|p|pl)\.?\d*))
            )?
            (?:
                \\+
                (?P<BuildMetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-@]+)*)
            )?
        $/xi';

        if (\preg_match($regex, $version, $matches) !== 1) {
            throw new InvalidTagException(
                \sprintf("Version string '%s' does not follow SemVer semantics", $version)
            );
        }

        $this->parseVersion($matches);
    }
}
