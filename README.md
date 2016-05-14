# objsql
* App:       objSQL
* Version:   3.9.0
* Author:    MT Jordan <mtjo62@gmail.com>
* Copyright: 2016
* License:   zlib/libpng License

*********************************************************************************

objSQL: Database Access Class

objSQL is a PHP based database access layer for web centric database servers.
Primarily designed for small to medium projects, objSQL utilizes an "Object Based"
approach for handling database queries with built in helper methods for a common
API.

*********************************************************************************

objSQL Features:

    * Object based code simplifies hooking into projects and allows for better
      encapsulation and exception handling
    * Reusable prepared queries with parameter binding
    * Transaction support including rollbacks and savepoints
    * Helper methods simplify executing queries without writing SQL statements
    * Batch operations (delete/insert/update) for all supported databases
    * Very small footprint with the entire library approximately 250KB in size
      unpacked
    * Requires no third party libraries other than enabled database PHP extensions
    * Supports both x86 and x64 builds of PHP 5.6+ - See supported databases below
      for exceptions

Supported Databases:

    * CUBRID 9.1+
    * Firebird 2.5
    * MariaDB 5.1+
    * MySQL 5.1+
    * Oracle 11+
    * PostgreSQL 7.4+
    * SQL Server 2005+
    * SQLite3

Supported Databases PHP 5.6 - x86:

    * CUBRID 9.1+ 
    * Firebird 2.5
    * MariaDB 5.1+
    * MySQL 5.1+
    * Oracle 11 (PDO only)
    * Oracle 12
    * PostgreSQL 9+
    * SQL Server 2005+
    * SQLite3

Supported Databases PHP 5.6 - x64:

    * CUBRID 9.1+ 
    * Firebird 2.5 (PHP 5.6.4+)
    * MariaDB 5.1+
    * MySQL 5.1+
    * Oracle 12
    * PostgreSQL 9+
    * SQL Server 2012+
    * SQLite3
    
Supported Databases PHP 7:

    * Firebird 2.5 
    * MariaDB 5.1+
    * MySQL 5.1+
    * Oracle 12
    * PostgreSQL 9+
    * SQL Server 2012+ (Beta drivers - https://github.com/Azure/msphpsql/releases)
    * SQLite3    
    
objSQL Requirements:

    * PHP 5.4+ (5.6+ recommended)
    * Enabled PHP extensions
    * Enabled PDO extensions

*********************************************************************************
