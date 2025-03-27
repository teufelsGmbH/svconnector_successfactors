<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "svconnector_successfactors".
 *
 * Auto generated 05-04-2017 18:01
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Connector service - JSON (SAP SuccessFactors)',
    'description' => 'Connector service for SAP SuccessFactors API (JSON)',
    'category' => 'services',
    'version' => '5.0.0-successfactors',
    'state' => 'stable',
    'clearcacheonload' => 0,
    'author' => 'Francois Suter (Idéative), Bastian Holzem (teufels GmbH)',
    'author_email' => 'typo3@ideative.ch, digital@teufels.com',
    'author_company' => '',
    'constraints' =>
        [
            'depends' =>
                [
                    'typo3' => '12.4.0-13.4.99',
                    'svconnector' => '6.0.0-0.0.0',
                ],
            'conflicts' =>
                [
                ],
            'suggests' =>
                [
                ],
        ],
];

