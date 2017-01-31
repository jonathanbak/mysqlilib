<?php
/**
 * MySQLiLib autoload for phpunit test
 *
 * Date : 2017. 2. 1.
 * File : autoload.php
 *
 * @author jonathanbak <jonathanbak@gmail.com>
 */

$loader = require __DIR__ . "/../../vendor/autoload.php";
$loader->addPsr4('MySQLiLibTests\\', __DIR__.'');

date_default_timezone_set('Asia/Seoul');