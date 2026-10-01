<?php

declare(strict_types=1);

namespace Cobweb\SvconnectorSuccessfactors\Service;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Cobweb\Svconnector\Attribute\AsConnectorService;
use Cobweb\Svconnector\Event\ProcessArrayDataEvent;
use Cobweb\Svconnector\Event\ProcessRawDataEvent;
use Cobweb\Svconnector\Event\ProcessResponseEvent;
use Cobweb\Svconnector\Event\ProcessXmlDataEvent;
use Cobweb\Svconnector\Exception\SourceErrorException;
use Cobweb\Svconnector\Service\ConnectorBase;
use Cobweb\Svconnector\Utility\FileUtility;
use Cobweb\SvconnectorSuccessfactors\Paginator\AbstractPaginator;
use Cobweb\SvconnectorSuccessfactors\Paginator\HydraPaginator;
use GuzzleHttp\Client;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service that reads JSON data for the "svconnector_successfactors" extension.
 */
#[AsConnectorService(type: 'successfactors', name: 'SAP SuccessFactors (JSON) connector')]
class ConnectorJson extends ConnectorBase
{
    protected string $extensionKey = 'svconnector_successfactors';

    /**
     * Verifies that the connection is functional
     * In the case of this service, it is always the case
     * It might fail for a specific file, but it is always available in general
     *
     * @return bool TRUE if the service is available
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Checks the connector configuration and returns notices, warnings or errors, if any.
     *
     * @return array
     */
    public function checkConfiguration(): array
    {
        $result = parent::checkConfiguration();
        // The "uri" parameter is mandatory
        if (empty($this->parameters['uri'])) {
            $result[ContextualFeedbackSeverity::ERROR->value][] = $this->sL(
                'LLL:EXT:svconnector_successfactors/Resources/Private/Language/locallang.xlf:no_json_defined'
            );
        }
        // The "headers" parameter is expected to be an array (and is deprecated)
        if (isset($this->parameters['headers'])) {
            $result[ContextualFeedbackSeverity::WARNING->value][] = $this->sL(
                'LLL:EXT:svconnector_successfactors/Resources/Private/Language/locallang.xlf:headers_deprecated'
            );
            if (!is_array($this->parameters['headers'])) {
                $result[ContextualFeedbackSeverity::WARNING->value][] = $this->sL(
                    'LLL:EXT:svconnector_successfactors/Resources/Private/Language/locallang.xlf:headers_must_be_array'
                );
            }
        }
        // The "requestOptions" parameter is expected to be an array
        if (isset($this->parameters['requestOptions']) && !is_array($this->parameters['requestOptions'])) {
            $result[ContextualFeedbackSeverity::WARNING->value][] = $this->sL(
                'LLL:EXT:svconnector_successfactors/Resources/Private/Language/locallang.xlf:request_options_must_be_array'
            );
        }
        // The "queryParameters" parameter is expected to be an array
        if (isset($this->parameters['queryParameters']) && !is_array($this->parameters['queryParameters'])) {
            $result[ContextualFeedbackSeverity::WARNING->value][] = $this->sL(
                'LLL:EXT:svconnector_successfactors/Resources/Private/Language/locallang.xlf:query_parameters_must_be_array'
            );
        }
        return $result;
    }

    /**
     * This method calls the query method and returns the result as is,
     * i.e. the json data, but without any additional work performed on it
     *
     * @return mixed Server response
     * @throws \Exception
     */
    public function fetchRaw(): mixed
    {
        $result = $this->query();
        $this->logger->info(
            'RAW JSON data',
            [$result]
        );
        $event = $this->eventDispatcher->dispatch(
            new ProcessRawDataEvent($result, $this)
        );
        return $event->getData();
    }

    /**
     * This method calls the query and returns the results from the response as an XML structure
     *
     * @return string XML structure
     * @throws \Exception
     */
    public function fetchXML(): string
    {
        $xml = $this->fetchArray();
        $xml = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>' . "\n" . GeneralUtility::array2xml($xml);
        $event = $this->eventDispatcher->dispatch(
            new ProcessXmlDataEvent($xml, $this)
        );

        return $event->getData();
    }

    /**
     * Fetch the JSON data and return it as an array
     *
     * @return array PHP array
     * @throws \Exception
     */
    public function fetchArray(): array
    {
        // Get the data from the source
        $result = $this->query();
        $result = json_decode((string)$result, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($result)) {
            throw new \InvalidArgumentException(
                'JSON structure could not be decoded',
                1671383061
            );
        }

        $paginator = $this->getPaginator();
        if ($paginator === null) {
            $data = $result;
        } else {
            $currentPage = $paginator->getStartPage();
            $hasNextPage = true;
            // Assemble a list of all results, including the first one
            $allResults = [$result];
            $originalParameters = $this->parameters;
            do {
                $paginator->setData($result);
                $nextPage = $paginator->getNextPage();
                if ($nextPage > $currentPage) {
                    $result = $this->query();
                    $result = json_decode((string)$result, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($result)) {
                        throw new \InvalidArgumentException(
                            sprintf(
                                'JSON structure could not be decoded, page %d',
                                $nextPage
                            ),
                            1709283781
                        );
                    }
                    $allResults[] = $result;
                    $currentPage = $nextPage;
                } else {
                    $hasNextPage = false;
                }
            } while ($hasNextPage);
            // Restore original parameters as they were modified by the paginator (at least with paging information)
            $this->parameters = $originalParameters;
            // Aggregate the results, if the query was paginated
            $data = $paginator->aggregate($allResults);
        }

        // Log the data
        $this->logger->info(
            'Structured data',
            $data
        );
        $event = $this->eventDispatcher->dispatch(
            new ProcessArrayDataEvent($data, $this)
        );
        return $event->getData();
    }

    /**
     * Return the JSON data (as a string) fetched with the given parameters
     *
     * @return mixed JSON content (string)
     * @throws \Exception
     */
    protected function query(): mixed
    {
        // Check the configuration
        $problems = $this->checkConfiguration();
        // Log all issues and raise error if any
        $this->logConfigurationCheck($problems);
        if (count($problems[ContextualFeedbackSeverity::ERROR->value]) > 0) {
            $message = '';
            foreach ($problems[ContextualFeedbackSeverity::ERROR->value] as $problem) {
                if ($message !== '') {
                    $message .= "\n";
                }
                $message .= $problem;
            }
            $this->raiseError(
                $message,
                1299257883,
                [],
                SourceErrorException::class
            );
        }

        // Define the request options
        $requestOptions = $this->parameters['requestOptions'] ?? [];
        // Include deprecated headers property
        // TODO: remove in next major version
        if (is_array($this->parameters['headers'] ?? null) && count($this->parameters['headers']) > 0) {
            $requestOptions = array_merge_recursive($requestOptions, ['headers' => $this->parameters['headers']]);
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = end($backtrace);
            $callerLocation = sprintf('file %s, line %d', $caller['file'], $caller['line']);

            trigger_error(sprintf(
                'Property "headers" is deprecated. Pass headers as part of the "requestOptions" property instead. Location: %s',
                $callerLocation,
            ), E_USER_DEPRECATED);
        }

        $this->logger->info(
            'Call parameters',
            $this->parameters
        );

        // Add the access token to the request headers (SuccessFactors specific)
        $accessToken = $this->getAccessToken($this->parameters);
        if ($accessToken !== null) {
            $requestOptions['headers']['Authorization'] = 'Bearer ' . $accessToken;
        }

        $fileUtility = GeneralUtility::makeInstance(FileUtility::class);
        $uri = $this->parameters['uri'];
        if (isset($this->parameters['queryParameters'])) {
            $uri = sprintf('%s?%s', $uri, http_build_query($this->parameters['queryParameters']));
        }
        $data = $fileUtility->getFileContent(
            $uri,
            $this->parameters['method'] ?? 'GET',
            $requestOptions,
        );
        if ($data === false) {
            $message = sprintf(
                $this->sL('LLL:EXT:svconnector_successfactors/Resources/Private/Language/locallang.xlf:json_not_fetched'),
                $uri,
                $fileUtility->getError()
            );
            $this->raiseError(
                $message,
                1299257894,
                [],
                SourceErrorException::class
            );
        }

        // Check if the current charset is the same as the file encoding
        // Don't do the check if no encoding was defined
        // (the conversion must happen before the JSON data is decoded)
        if (empty($this->parameters['encoding'])) {
            $encoding = null;
            $isSameCharset = true;
        } else {
            $encoding = $this->parameters['encoding'];
            $isSameCharset = $this->getCharset() === $encoding;
        }
        // If the charset is not the same, convert data
        if (!$isSameCharset) {
            $data = mb_convert_encoding($data, $this->getCharset(), $encoding);
        }

        // SAP SuccessFactors (OData v2) wraps the actual records in "d" => "results"
        $data = $this->extractRecords($data, $uri);

        // Limit to existing records
        if (!empty($this->parameters['limitByJobId'])) {
            $data = $this->filterDataByJobId($data, $this->parameters['limitByJobId']);
        }

        // Fire event for response processing
        $event = $this->eventDispatcher->dispatch(
            new ProcessResponseEvent($data, $this)
        );

        // Return the result
        return $event->getResponse();
    }

    /**
     * Extracts the records from the "d" => "results" property of the SAP SuccessFactors (OData v2) response.
     *
     * @param string $response Raw JSON response
     * @param string $uri URI that was called (for error reporting)
     * @return string JSON string with the records
     */
    protected function extractRecords(string $response, string $uri): string
    {
        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !isset($decoded['d']['results'])) {
            $this->raiseError(
                sprintf(
                    $this->sL('LLL:EXT:svconnector_successfactors/Resources/Private/Language/locallang.xlf:json_invalid_response'),
                    $uri
                ),
                1299257895,
                [],
                SourceErrorException::class
            );
        }
        return json_encode($decoded['d']['results'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Filters the data based on existing `external_id` values in the database.
     *
     * @param string $data JSON string with the original data
     * @param string $jobIdField The field name for job requisition ID to filter by
     * @return string Filtered JSON string
     */
    protected function filterDataByJobId(string $data, string $jobIdField): string
    {
        // Decode JSON into an array
        $dataArray = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        // Get existing external_id values from the database
        $existingIds = $this->getJobOfferExternalIds();

        // Filter data based on existing external_id values
        $filteredDataArray = array_values(
            array_filter(
                $dataArray,
                static fn($item): bool => in_array($item[$jobIdField] ?? null, $existingIds)
            )
        );

        // Return the filtered array as JSON
        return json_encode($filteredDataArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Get external_ids from tx_tt3career_domain_model_joboffer table
     *
     * @return array
     */
    private function getJobOfferExternalIds(): array
    {
        // Query the database to get the external_ids
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_tt3career_domain_model_joboffer');

        return $queryBuilder
            ->select('external_id')
            ->from('tx_tt3career_domain_model_joboffer')
            ->executeQuery()
            ->fetchFirstColumn();
    }

    /**
     * Return a paginator object, if defined
     */
    protected function getPaginator(): ?AbstractPaginator
    {
        $paginatorSetting = $this->parameters['paginator'] ?? '';
        if ($paginatorSetting === '') {
            return null;
        }
        // Consider predefined paginators
        if ($paginatorSetting === 'hydra') {
            $paginatorClass = HydraPaginator::class;
        } else {
            $paginatorClass = $paginatorSetting;
        }
        $paginator = GeneralUtility::makeInstance($paginatorClass, $this);
        if (!$paginator instanceof AbstractPaginator) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Class %s does not extend class %s',
                    $paginator,
                    AbstractPaginator::class
                ),
                1709280188
            );
        }
        return $paginator;
    }

    /**
     * Return the SAP SuccessFactors accessToken
     * for SAP SuccessFactors API
     *
     * @param array $parameters
     * @return string|null
     */
    protected function getAccessToken(array $parameters): ?string
    {
        if ((array_key_exists('client_id', $parameters)) && (array_key_exists('assertion', $parameters))) {
            $parsed_url = parse_url($parameters['uri']);
            $domain = $parsed_url['scheme'] . '://' . $parsed_url['host'];
            $tokenUrl = $domain . '/oauth/token';

            try {
                $client = new Client();
                $response = $client->post($tokenUrl, [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Cache-Control' => 'no-cache',
                    ],
                    'form_params' => [
                        'company_id' => $parameters['company_id'] ?? '',
                        'client_id' => $parameters['client_id'],
                        'assertion' => $parameters['assertion'],
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:saml2-bearer',
                    ],
                ]);

                if ($response->getStatusCode() === 200) {
                    $body = $response->getBody()->getContents();
                    $data = json_decode($body, true);
                    return $data['access_token'] ?? null;
                }
            } catch (\Exception $e) {
                // PSR-3 logger (LoggerAwareTrait), not the removed TypoScript parser method -> false positive of the extension scanner
                // @extensionScannerIgnoreLine
                $this->logger->error('Could not retrieve access token: ' . $e->getMessage());
                return null;
            }
        }

        return null;
    }
}

