<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Framework\Module\Setup;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Encryption\EncryptorInterface;

$setup = Bootstrap::getObjectManager()->get(
    Setup::class
);

$encryptor = Bootstrap::getObjectManager()->get(
    EncryptorInterface::class
);

$connection = $setup->getConnection();

$tableName = $setup->getTable('test_table_with_encrypted_data');

$table = $connection->newTable($tableName);

$table->addColumn(
    'id',
    \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
    null,
    ['identity' => true, 'nullable' => false, 'primary' => true],
    'Id'
)->addColumn(
    'not_enc_column_1',
    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
    null,
    ['nullable' => true],
    'Not Encrypted Column'
)->addColumn(
    'enc_column_1',
    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
    null,
    ['nullable' => true],
    'Encrypted Column One'
)->addColumn(
    'enc_column_2',
    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
    null,
    ['nullable' => true],
    'Encrypted Column Two'
)->setComment(
    'Test table with encrypted data.'
);

$connection->createTable($table);

$connection->insertArray(
    $tableName,
    ["not_enc_column_1", "enc_column_1", "enc_column_2"],
    [
        [
            "Not Encrypted Column Value",
            "",
            null
        ],
        [
            "Not Encrypted Column Value",
            $encryptor->encrypt("Encrypted Column Value"),
            ""
        ],
        [
            "Not Encrypted Column Value",
            $encryptor->encrypt("Encrypted Column Value"),
            $encryptor->encrypt("Encrypted Column Value")
        ],
        [
            "Not Encrypted Column Value",
            substr_replace(
                $encryptor->encrypt("Encrypted Column Value"),
                "9",
                2,
                1
            ),
            ""
        ]
    ]
);
