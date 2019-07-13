# mysql-to-laravel

### Introduction

Script that reverse engineer a database schema by interpreting the standard ANSI information_schema table and writes a set of Laravel migrations out of it.

The project is called mysql-to-laravel but could theorically be used with any kind of RDBMS that supports the ANSI information_schema table:

- Microsoft SQL Server
- MySQL
- PostgreSQL
- H2 Database
- HSQLDB
- InterSystems Caché
- MariaDB
- Presto
- MemSQL

I wrote this back in 2014 because I prefer to design my database using a specialized tool such as Enterprise Architect. That kind of tools comes with an execution engine that is capable of creating the database structure out of your design. I took this approach instead of writing a plugin because it applies to more use cases.

### TO DO / NOT SUPPORTED YET

- Delta
