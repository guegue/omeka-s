<?php
namespace Omeka\Db\Migrations;

use Doctrine\DBAL\Connection;
use Omeka\Db\Migration\MigrationInterface;

class AddResourceText implements MigrationInterface
{
    public function up(Connection $conn)
    {
        $conn->exec('ALTER TABLE resource ADD text LONGTEXT DEFAULT NULL;');
        $conn->exec('CREATE FULLTEXT INDEX IDX_BC91F4163B8BA7C7 ON resource (text);');
    }
}
