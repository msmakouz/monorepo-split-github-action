<?php

declare(strict_types=1);

namespace Symplify\MonorepoSplit;

final class Branch
{
    public function __construct(
        private string $name,
        private ?Tag $tag = null
    ) {
        $this->name = $this->resolveBranch($name, $tag);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function findMostRecentTag(array $availableTags): ?string
    {
        if ($this->tag === null) {
            return null;
        }

        $availableTags = \array_map(
            static fn (string $tag) => new Tag(\strtolower($tag)),
            $availableTags
        );

        $newTag = $this->tag;
        $tags = \array_filter($availableTags, static function (Tag $tag) use ($newTag) {

            // all previous major versions
            if ($newTag->getMajor() > $tag->getMajor()) {
                return true;
            }

            // all minor versions up to the requested in the requested major version
            if ($newTag->getMajor() === $tag->getMajor()) {
                return $newTag->getMinor() >= $tag->getMinor();
            }

            return false;
        });

        if ($tags === []) {
            return null;
        }

        usort($tags, static fn (Tag $a, Tag $b) => $a->isGreaterThan($b) ? -1 : 1);

        return (string) $tags[0];
    }

    private function resolveBranch(string $branch, ?Tag $tag = null): string
    {
        if ($tag !== null && \count(\explode(Tag::DELIMITER, $branch)) === 3) {
            // we need branches only for minor and major versions
            return $tag->getMajor() . Tag::DELIMITER . $tag->getMinor();
        }

        return $branch;
    }
}
