<?php
/**
 * Main Propel configuration file
 * @package Dszczer/ListerBundle
 * @author Damian Szczerbiński <dszczer@gmail.com>
 */

/** @var string $rootDir */
$rootDir = __DIR__;
/** @var string $DS */
$DS = DIRECTORY_SEPARATOR;

return [
    'propel' => [
        'database' => [
            'connections' => [
                'lister_test' => [
                    'adapter' => 'sqlite',
                    'dsn' => "sqlite:$rootDir{$DS}test.sq3",
                    'user' => '',
                    'password' => '',
                    'settings' => [
                        'charset' => 'utf8',
                    ]
                ]
            ]
        ],
        'paths' => [
            'projectDir' => $rootDir,
            'schemaDir' => $rootDir,
            'outputDir' => "$rootDir{$DS}Propel",
            'phpDir' => "$rootDir{$DS}Propel",
            'phpConfDir' => "$rootDir{$DS}Propel{$DS}Runtime",
            'migrationDir' => "$rootDir{$DS}Propel{$DS}Migration",
            'sqlDir' => "$rootDir{$DS}Propel{$DS}Sql"
        ],
        'runtime' => [
            'defaultConnection' => 'lister_test',
            'connections' => ['lister_test'],
        ],
        'generator' => [
            'defaultConnection' => 'lister_test',
            'connections' => ['lister_test'],
            'objectModel' => [
                'addHooks' => false // slightly performance improvement
            ]
        ],
        'reverse' => [
            'connection' => 'lister_test',
        ]
    ]
];