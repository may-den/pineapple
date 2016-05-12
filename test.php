<?php
use Doctrine\DBAL\Configuration as DoctrineConfiguration;
use Doctrine\DBAL\DriverManager as DoctrineDriverManager;

require_once 'vendor/autoload.php';

$config = new DoctrineConfiguration();

$connectionParams = array(
    'dbname' => 'testdb',
    'user' => 'root',
    'password' => '',
    'host' => '192.168.20.56',
    'driver' => 'pdo_mysql',
);
$conn = DoctrineDriverManager::getConnection($connectionParams, $config);

$sth = $conn->query('SELECT USER()');
var_dump($sth);
//$sth->execute();

//$result = $sth->fetch();
//var_dump($result);
