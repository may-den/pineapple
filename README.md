# Doctrine DBAL for PEAR DB

## Why?

PEAR DB is very old, and was obsoleted by MDB2, which has in turn become obsolete. People have code based on these modules, but need an upgrade path that means new code can be developed using modern DB access methods, whilst retaining access for legacy code without opening a second database connection.

This class adds a database driver to DB that can be constructed using a Doctrine DBAL connection, and passes all queries through to DBAL. A method for extracting the DBAL connection handle is provided.

## How does it work?

We haven't finished writing it yet.

## Who is responsible for this?

Rob Andrews [rob.andrews@mayden.co.uk](mailto:rob.andrews@mayden.co.uk)

Aaron Lang [aaron.lang@mayden.co.uk](mailto:aaron.lang@mayden.co.uk)
