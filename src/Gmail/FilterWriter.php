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
        $enabledFilters = [];
        if ($filters !== null) {
            foreach ($filters->getIterator() as $filter) {
                /** @var \TBF2GM\Filter $filter */
                if ($filter->isEnabled()) {
                    $enabledFilters[] = $filter;
                }
            }
        }
        $exceptions = [];
        if ($enabledFilters !== []) {
            $existingFilters = $this->getResource()->listUsersSettingsFilters('me')->getFilter();
            foreach ($enabledFilters as $filter) {
                try {
                    $this->ensureFilter($existingFilters, $filter);
                } catch (Exception\FilterNotCreableException $x) {
                    $exceptions[] = $x;
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
     * @param Google_Service_Gmail_Filter[] $existingFilters
     *
     * @throws \TBF2GM\Exception\FilterNotCreableException
     */
    protected function ensureFilter(array &$existingFilters, Filter $filter)
    {
        $gmailFilter = $this->createGmailFilter($filter);
        if (!$this->gmailFilterAlreadyExisting($existingFilters, $gmailFilter)) {
            try {
                $existingFilters[] = $this->getResource()->create('me', $gmailFilter);
            } catch (GoogleServiceException $x) {
                $errors = $x->getErrors();
                if ($errors[0]['message'] ?? null === 'Unrecognized forwarding address') {
                    $x2 = new Exception\UnrecognizedForwardingAddressException($errors[0]['message']);
                    $x2->setFilter($filter);
                    foreach ($filter->getActions() as $action) {
                        if ($action instanceof Action\Forward) {
                            throw $x2->setForwardingAddress($action->getRecipient());
                        }
                    }
                    throw $x2;
                }
                throw $x;
            }
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

    protected function gmailFilterAlreadyExisting(array $existingFilters, Google_Service_Gmail_Filter $filter): bool
    {
        return false;
    }
}
