<?php

declare(strict_types=1);

namespace TBF2GM\Filter;

use TBF2GM\Folder;

interface FolderAction extends Action
{
    public function getFolder(): Folder;
}
