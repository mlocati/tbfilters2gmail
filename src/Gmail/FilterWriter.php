<?php

declare(strict_types=1);

namespace TBF2GM\Gmail;

use Google\Client;
use Google\Service\Exception as GoogleServiceException;
use Google_Service_Gmail;
use Google_Service_Gmail_Filter;
use Google_Service_Gmail_Resource_UsersSettingsFilters;
use TBF2GM\DefaultTags;
use TBF2GM\Exception;
use TBF2GM\Filter;
use TBF2GM\Filter\Action;
use TBF2GM\FilterList;

class FilterWriter
{
    private Client $client;

    private ?Google_Service_Gmail $service = null;

    private ?Google_Service_Gmail_Resource_UsersSettingsFilters $resource = null;

    private ?LabelManager $labelManager = null;

    private array $defaultLabelNames;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->defaultLabelNames = DefaultTags::getEnglishNames();
    }

    /**
     * @return $this
     */
    public function setDefaultLabelNames(array $names): self
    {
        $this->defaultLabelNames = $names;

        return $this;
    }

    public function getDefaultLabelNames(): array
    {
        return $this->defaultLabelNames;
    }

    /**
     * @return \TBF2GM\Exception\FilterNotCreableException[]
     */
    public function ensureFilters(?FilterList $filters): array
    {
        $exceptions = [];
        if ($filters !== null) {
            foreach ($filters->getIterator() as $filter) {
                /** @var \TBF2GM\Filter $filter */
                if ($filter->isEnabled()) {
                    try {
                        $this->ensureFilter($filter);
                    } catch (Exception\FilterNotCreableException $x) {
                        $exceptions[] = $x;
                    }
                }
            }
        }

        return $exceptions;
    }

    protected function getLabelManager(): LabelManager
    {
        if ($this->labelManager === null) {
            $this->labelManager = new LabelManager($this->getClient());
        }

        return $this->labelManager;
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

    protected function getResource(): Google_Service_Gmail_Resource_UsersSettingsFilters
    {
        if ($this->resource === null) {
            $this->resource = $this->getService()->users_settings_filters;
        }

        return $this->resource;
    }

    /**
     * @throws \TBF2GM\Exception\FilterNotCreableException
     */
    protected function ensureFilter(Filter $filter): void
    {
        $gmailFilter = $this->createGmailFilter($filter);
        try {
            $this->getResource()->create('me', $gmailFilter);
        } catch (GoogleServiceException $x) {
            $errors = $x->getErrors();
            switch ($errors[0]['message'] ?? null) {
                case 'Unrecognized forwarding address':
                    $x2 = new Exception\UnrecognizedForwardingAddressException($errors[0]['message']);
                    $x2->setFilter($filter);
                    foreach ($filter->getActions() as $action) {
                        if ($action instanceof Action\Forward) {
                            throw $x2->setForwardingAddress($action->getRecipient());
                        }
                    }
                    throw $x2;
                case 'Filter already exists':
                    $x2 = new Exception\FilterAlreadyExistsException($errors[0]['message']);
                    throw $x2->setFilter($filter);
            }
            throw $x;
        }
    }

    /**
     * @throws \TBF2GM\Exception\FilterNotCreableException
     */
    protected function createGmailFilter(Filter $filter): Google_Service_Gmail_Filter
    {
        $result = new Google_Service_Gmail_Filter();
        try {
            $result->setCriteria($filter->getConditions()->toGmailCriteria());
            $result->setAction($filter->toGmailAction($this->getLabelManager()));
        } catch (Exception\FilterNotCreableException $x) {
            throw $x->setFilter($filter);
        }

        return $result;
    }
}
