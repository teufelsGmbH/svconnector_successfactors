![version](https://img.shields.io/badge/version-6.0.*-yellow.svg?style=flat-square)
![TYPO3 13 LTS](https://img.shields.io/badge/13_LTS-%23A6C694.svg?style=flat-square)
![TYPO3 14 LTS](https://img.shields.io/badge/14_LTS-%23A6C694.svg?style=flat-square)

# Connector service - SAP SuccessFactors (JSON)

This service is a copy of [cobweb/svconnector_json](https://github.com/cobwebch/svconnector_json) (6.0.0) and provides a simple API for reading
SAP SuccessFactors API (JSON) sources and returning their content as a PHP array or a XML structure.

It differs from `svconnector_json` only where required for SAP SuccessFactors:
- connector type `successfactors`
- OAuth2 access token (`company_id`, `client_id`, `assertion`, SAML2 bearer grant) which is sent as `Authorization: Bearer` header
- the records are read from the `d` => `results` property of the OData response
- optional parameter `limitByJobId`: limits the records to those whose given field value exists as `external_id` in `tx_tt3career_domain_model_joboffer`

All other parameters (`uri`, `method`, `queryParameters`, `requestOptions`, `encoding`, `paginator`, ...) behave like in `svconnector_json`,
see https://docs.typo3.org/typo3cms/extensions/svconnector_json/

## This version supports TYPO3
13 LTS and 14 LTS (PHP 8.2 - 8.5)

## Requirements
- `"typo3/cms-core": "^13.4 || ^14.3"`
- `"cobweb/svconnector": "^7.0"`

## Composer support
`composer req teufels/svconnector_successfactors`

## Changelog

#### [6.0.0]
- [BREAKING] adapt to `cobweb/svconnector` 7 and `cobweb/svconnector_json` 6.0.0 (connector API, request options, paginator constructor, events instead of hooks)
- [BREAKING] drop TYPO3 12.4 support and PHP 8.1, add TYPO3 14.3 and PHP 8.5
- [BREAKING] `headers` parameter is deprecated, use `requestOptions` (`['headers' => [...]]`) instead
- [BREAKING] the SC_OPTIONS hooks (`processRaw`, `processXML`, `processArray`, `processResponse`) are removed, use the events instead
- [FIXED] an invalid response (no `d` => `results` property) raises a `SourceErrorException`
- [FIXED] `limitByJobId` returns a list again (instead of an object with gaps in the keys)

#### [5.0.0]
- TYPO3 12/13, based on `svconnector_json` 5.0.0

