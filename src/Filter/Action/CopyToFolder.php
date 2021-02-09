<?php

declare(strict_types=1);

namespace TBF2GM\Filter\Action;

use TBF2GM\Filter\Action;
use TBF2GM\Filter\FolderAction;
use TBF2GM\Folder;

class CopyToFolder implements Action, FolderAction
{
    private Folder $folder;

    public function __construct(Folder $folder)
    {
        $this->folder = $folder;
    }

    public function __toString(): string
    {
        return "Copy to folder \"{$this->getFolder()}\"";
    }

    public function getFolder(): Folder
    {
        return $this->folder;
    }

    /**
     * @return $this
     */
    public function setFolder(Folder $value): self
    {
        $this->folder = $value;

        return $this;
    }

    public static function create(?string $value): Action
    {
        return new static(Folder::parse($value));
    }
}
