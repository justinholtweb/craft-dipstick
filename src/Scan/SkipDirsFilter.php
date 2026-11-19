<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Scan;

use RecursiveDirectoryIterator;
use RecursiveFilterIterator;

/**
 * Leaves out dependency, test and build directories, and doesn't follow
 * symlinked directories (they could lead outside the project).
 *
 * @extends RecursiveFilterIterator<string, \SplFileInfo, RecursiveDirectoryIterator>
 */
final class SkipDirsFilter extends RecursiveFilterIterator
{
    /** @param  list<string>  $skip */
    public function __construct(RecursiveDirectoryIterator $iterator, private readonly array $skip)
    {
        parent::__construct($iterator);
    }

    public function accept(): bool
    {
        $current = $this->current();

        if ($current->isDir()) {
            return ! $current->isLink() && ! in_array($current->getFilename(), $this->skip, true);
        }

        return true;
    }

    public function getChildren(): self
    {
        /** @var RecursiveDirectoryIterator $inner */
        $inner = $this->getInnerIterator();
        /** @var RecursiveDirectoryIterator $children */
        $children = $inner->getChildren();

        return new self($children, $this->skip);
    }
}
