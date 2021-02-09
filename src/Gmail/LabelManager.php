<?php

declare(strict_types=1);

namespace TBF2GM\Gmail;

use Google\Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Label;
use Google_Service_Gmail_Resource_UsersLabels;
use TBF2GM\Folder;

class LabelManager
{
    private Client $client;

    private ?Google_Service_Gmail $service = null;

    private ?Google_Service_Gmail_Resource_UsersLabels $resource = null;

    /**
     * @var Google_Service_Gmail_Label[]|null
     */
    private ?array $existingLabels = null;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function findLabelByFolder(Folder $folder, bool $caseSensitive = false): ?Google_Service_Gmail_Label
    {
        $path = implode('/', $folder->getNames());

        return $this->findLabelByPath($path, $caseSensitive);
    }

    public function findLabelByPath(string $path, bool $caseSensitive = false): ?Google_Service_Gmail_Label
    {
        $search = $caseSensitive ? $path : mb_strtolower($path);
        foreach ($this->getExistingLabels() as $label) {
            if ($caseSensitive) {
                if ($label->getName() === $search) {
                    return $label;
                }
            } else {
                if (mb_strtolower($label->getName()) === $search) {
                    return $label;
                }
            }
        }
        return null;
    }

    /**
     * @throws \Google\Service\Exception
     */
    public function getOrCreateLabelFromFolder(Folder $folder, bool $caseSensitive = false): Google_Service_Gmail_Label
    {
        $path = implode('/', $folder->getNames());

        return $this->getOrCreateLabelFromPath($path, $caseSensitive);
    }

    /**
     * @throws \Google\Service\Exception
     */
    public function getOrCreateLabelFromPath(string $path, bool $caseSensitive = false): Google_Service_Gmail_Label
    {
        $label = $this->findLabelByPath($path, $caseSensitive);
        if ($label !== null) {
            return $label;
        }

        $names = explode('/', $path);
        $prefix = '';
        $label = null;
        while ($names !== []) {
            $fullName = $prefix . array_shift($names);
            $label = $this->findLabelByPath($fullName);
            if ($label === null) {
                $label = $this->createLabel($fullName);
            }
            $prefix = $fullName . '/';
        }

        return $label;
    }

    protected function getClient(): Client
    {
        return $this->client;
    }

    protected function getService(): Google_Service_Gmail
    {
        if ($this->service === null) {
            $this->service = new Google_Service_Gmail($this->getClient());
        }

        return $this->service;
    }

    protected function getResource(): Google_Service_Gmail_Resource_UsersLabels
    {
        if ($this->resource === null) {
            $this->resource = $this->getService()->users_labels;
        }

        return $this->resource;
    }

    /**
     * @return Google_Service_Gmail_Label[]
     */
    protected function getExistingLabels(): array
    {
        if ($this->existingLabels === null) {
            $existingLabels = [];
            foreach ($this->getResource()->listUsersLabels('me')->getLabels() as $label) {
                $existingLabels[] = $label;
            }

            $this->existingLabels = $existingLabels;
        }

        return $this->existingLabels;
    }

    /**
     * @throws \Google\Service\Exception
     */
    protected function createLabel(string $path): Google_Service_Gmail_Label
    {
        $body = new Google_Service_Gmail_Label();
        $body->setName($path);
        $label = $this->getService()->users_labels->create('me', $body);
        $this->existingLabels[] = $label;

        return $label;
    }
}
