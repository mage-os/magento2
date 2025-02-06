<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Framework\Module\Setup;
use Magento\TestFramework\Helper\Bootstrap;

$setup = Bootstrap::getObjectManager()->get(
    Setup::class
);

$table = $setup->getConnection()->dropTable(
    $setup->getTable('test_table_with_encrypted_data')
);
