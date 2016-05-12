<?php
use Doctrine\DBAL\Configuration as DoctrineConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager as DoctrineDriverManager;

require_once 'vendor/autoload.php';
require_once __DIR__ . '/../src/PEAR.php';

function getConnection() {
    $configObject = new DoctrineConfiguration();
    $connectionParams = require __DIR__ . '/../connectionParams.php';
    $conn = DoctrineDriverManager::getConnection($connectionParams, $configObject);
    return $conn;
}

function selectUser() {
    $sth = getConnection()->query('SELECT USER()');
    $result = $sth->fetch();
    return $result;
}

describe('pineapple', function() {
    it('can connect', function() {
        $connection = getConnection();
        expect($connection)->toBeAnInstanceOf(Connection::class);
    });

    it('can select user', function() {
        $user = selectUser();
        expect($user)->toBe(array("USER()" => "root@192.168.20.56"));
    });

    it('can oops', function() {
        $error = PEAR::raiseError('oops!');
        expect($error)->toBeAnInstanceOf(PEAR_Error::class);
    });
});
