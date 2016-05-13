# Pineapple: Refactored PEAR and DB

## Why?

PEAR DB is very old (the copyright range ends 9 years prior to Pineapple's inception), and was obsoleted by MDB2, which has in turn become obsolete. People have code based on these modules, but need an upgrade path that means new code can be developed using modern DB access methods, whilst retaining access for legacy code without opening a second database connection.

This package is a fork of PEAR and DB, heavily refactored. The purpose of it is to provide a method-compatible drop-in replacement, reproducing `PEAR::raiseError` and `PEAR::isError`, and all methods under the `DB` class. Connection-specific drivers are dropped and only a `DoctrineDbal` driver remains, and that must be constructed without a connection-specific DSN and dependency injected with a constructed DBAL connection. It is intended _only_ as a path to Doctrine DBAL migration, to keep legacy systems working whilst retaining a single database connection per application.

The intention is to strip unused methods, clean (remove all warnings and notices), make PSR-2 clean and provide full test coverage of the DB compatibility layer. Global constants will be replaced with class constants, though the compatibility module ([borb/pineapple-compat](https://github.com/borb/pineapple-compat)) will provide global constants that map to class constants for backward compatibility.

It is up to your application to cache the constructed DBAL connection. Please do not use Pineapple DB to retrieve your connection handle.

## How does it work?

In order to facilitate a deep rework without breaking compatibility with applications that use the `DB` and `DB_*` classnames, the code has been refactored to reside within the `Mayden\Pineapple` namespace. Here is a handy table of the mapping:

| Old class    | New class                           |
|--------------|-------------------------------------|
| `DB`         | `Mayden\Pineapple\DB`               |
| `DB_Error`   | `Mayden\Pineapple\DB\Error`         |
| `DB_result`  | `Mayden\Pineapple\DB\Result`        |
| `DB_row`     | `Mayden\Pineapple\DB\Row`           |
| `DB_common`  | `Mayden\Pineapple\DB\Driver\Common` |
| `PEAR`       | `Mayden\Pineapple\Util`             |
| `PEAR_Error` | `Mayden\Pineapple\Error`            |

If possible, it would be beneficial for you to refactor your code to use the new class names. However, if refactoring isn't an option, you can use the counterpart module, which provides root namespace class names in the left column of the above table. See [borb/pineapple-compat](https://github.com/borb/pineapple-compat) and load that into your composer configuration to add compatible classes.

## Usage

```php
<?php

use Mayden\Pineapple\DB as PineappleDB;

$db = PineappleDB::connect('DoctrineDbal://');
$db->setConnectionHandle($dbalConn, PineappleDB::parseDSN('mysql://foo:bar@dbhost/dbname');
$result = $db->query('SELECT USER(), DATABASE()');
```

## Credits

The DB authors should take the most credit; the source was forked in its entirety for the refactor.

The following credits were taken from DB:

* Stig Bakken [ssb@php.net](mailto:ssb@php.net)
* Tomas V.V.Cox [cox@idecnet.com](mailto:cox@idecnet.com)
* Daniel Convissor [danielc@php.net](mailto:danielc@php.net)

And these from PEAR:

* Sterling Hughes [sterling@php.net](mailto:sterling@php.net)
* Stig Bakken [ssb@php.net](mailto:ssb@php.net)
* Tomas V.V.Cox [cox@idecnet.com](mailto:cox@idecnet.com)
* Greg Beaver [cellog@php.net](mailto:cellog@php.net)

## Who is responsible for this?

* Rob Andrews [rob.andrews@mayden.co.uk](mailto:rob.andrews@mayden.co.uk)
* Aaron Lang [aaron.lang@mayden.co.uk](mailto:aaron.lang@mayden.co.uk)
