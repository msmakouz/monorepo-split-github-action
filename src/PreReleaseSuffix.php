<?php

declare(strict_types=1);

namespace Symplify\MonorepoSplit;

use Symplify\MonorepoSplit\Exception\InvalidTagException;

// code from phar-io/version
final class PreReleaseSuffix implements \Stringable
{
    private const valueScoreMap = [
        'dev'   => 0,
        'a'     => 1,
        'alpha' => 1,
        'b'     => 2,
        'beta'  => 2,
        'rc'    => 3,
        'p'     => 4,
        'pl'    => 4,
        'patch' => 4,
    ];

    private string $value;
    private int $valueScore;
    private int $number = 0;
    private string $full;

    /**
     * @throws InvalidTagException
     */
    public function __construct(string $value)
    {
        $this->parseValue($value);
    }

    public function __toString(): string
    {
        return $this->full;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function isGreaterThan(PreReleaseSuffix $suffix): bool
    {
        if ($this->valueScore > $suffix->valueScore) {
            return true;
        }

        if ($this->valueScore < $suffix->valueScore) {
            return false;
        }

        return $this->getNumber() > $suffix->getNumber();
    }

    private function mapValueToScore(string $value): int
    {
        $value = \strtolower($value);

        return self::valueScoreMap[$value];
    }

    private function parseValue(string $value): void
    {
        $regex = '/-?((dev|beta|b|rc|alpha|a|patch|p|pl)\.?(\d*)).*$/i';

        if (\preg_match($regex, $value, $matches) !== 1) {
            throw new InvalidTagException(\sprintf('Invalid label %s', $value));
        }

        $this->full  = $matches[1];
        $this->value = $matches[2];

        if ($matches[3] !== '') {
            $this->number = (int) $matches[3];
        }

        $this->valueScore = $this->mapValueToScore($matches[2]);
    }
}
