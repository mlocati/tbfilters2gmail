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

    /**
     * @var \TBF2GM\Gmail\LabelManager[]
     */
    private array $labelManagers = [];

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
    public function ensureFilters(?FilterList $filters, bool $dryRun = false): array
    {
        $exceptions = [];
        if ($filters !== null) {
            foreach ($filters->getIterator() as $filter) {
                /** @var \TBF2GM\Filter $filter */
                if ($filter->isEnabled()) {
                    try {
                        $this->ensureFilter($filter, $dryRun);
                    } catch (Exception\FilterNotCreableException $x) {
                        $exceptions[] = $x;
                    }
                }
            }
        }

        return $exceptions;
    }

    protected function getLabelManager(bool $dryRun): LabelManager
    {
        $key = $dryRun ? 1 : 0;
        if (!isset($this->labelManagers[$key])) {
            $this->labelManagers[$key] = new LabelManager($this->getClient(), $dryRun);
        }

        return $this->labelManagers[$key];
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
    protected function ensureFilter(Filter $filter, bool $dryRun): void
    {
        $gmailFilter = $this->createGmailFilter($filter, $dryRun);
        if ($dryRun === false) {
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
    }

    /**
     * @throws \TBF2GM\Exception\FilterNotCreableException
     */
    protected function createGmailFilter(Filter $filter, bool $dryRun): Google_Service_Gmail_Filter
    {
        $result = new Google_Service_Gmail_Filter();
        try {
            $result->setCriteria($filter->getConditions()->toGmailCriteria());
            $result->setAction($filter->toGmailAction($this->getLabelManager($dryRun)));
        } catch (Exception\FilterNotCreableException $x) {
            throw $x->setFilter($filter);
        }

        return $result;
    }
}
