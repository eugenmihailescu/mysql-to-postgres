# MySql to Postgres database migrator
This application allows you to migrate a MySql databse to a Postgres server. It has the following functionalities:
- generate a Postgres SQL script of a MySql database
- migrate the MySql database to a Postgres SQL server
- web user interface (WUI) and console user interface (CUI)

This tool converts the following MySQL objects:
- the table along with its primary key, auto-increment columns and its columns constraints
- the table indexes
- the table foreign keys

> Note:
> the MySQL autoincrement columns will be bounded to their PostgreSQL coresponding sequences.

If the target PostgreSQL database does not exist it will be created automatically using the current connection credentials.
 
### Requirements

This application is built on top of Symfony Framework 3.0 so many of its requirements derives from it:

* PHP 5.5.9 :: [read more](http://symfony.com/doc/current/reference/requirements.html)
* [composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)
* 60+ MB space on disk (99% is Symfony alone)

### How to install

Create a directory `DIR` on the system where you want to install this application. Type the following command at the terminal:
```bash
cd  DIR # the newly created directory
git clone https://github.com/eugenmihailescu/mysql-to-postgres.git # close the project at DIR
cd mysql-to-postgres # change into the newly cloned app directory
composer install --no-dev --optimize-autoloader # this will install the project dependencies
```
At some point the install script may ask you to enter some parameters like `database_host`, `mailer_host`, etc. Just respond with the default values (ie. press Enter).

So far you installed the application. If you want to test it locally then just run the following commands at your terminal:
```bash
cd mysql-to-postgres # the app directory
bin/console server:run # now test the app at: http://localhost:8000
```
In case you want to install this application on a web server then [follow these instruction](http://symfony.com/doc/current/cookbook/configuration/web_server_configuration.html).

### Configuration

1. [Localization](#localization)
2. [Temporary data and download path](#temp-path)
3. [Temporary SQL files retention period](#retention)
4. [SQL script limits](#script-limit)
5. [Security](#security)

<a id="localization">Localization</a>

Right now the application is **localized** only in English. If you want to translate it to some other language then clone `src/PgMigratorBundle/Resources/translations/messages.en.xlf` to `messages.XX.xlf` where `XX` is your target language. In the newly created file change the English content of each `target` tag. To activate the newly created language change the parameter `locale: en` to `locale: XX` in `app/config/config.yml` file.

<a id="temp-path">Temporary data and download path</a>

Change the (**relative|absolute**) **path** of the parameters `data_path` respectively `download_path` from `app/config/config.yml` file.

<a id="retention">Temporary SQL files retention period</a>

Once the **SQL script** file is generated it will last at the `download_path` until it is downloaded (via WUI) or a certain **retention period** is reached. By default this period is 3600 seconds (ie. 1 hour). However, you may change this value to fit your need. Just change the `file_retention_time` parameter from `app/config/config.yml` file.

<a id="script-limit">SQL script limits</a>

If you intend to install this application on a public server (eg. for demo purpose) and to limit the usage of your server resources you might want to limit the number of lines the MySql script generator is going to create (thus saving cpu, memory, disk space and bandwidth). This can be done by changing the value of the `mysql_script_limit` parameter in the `app/config/config.yml` file. This value represents the **maximum number of line within the SQL script file** that will be created and thus executed at migration time. If you don't want to set any limit then set this value to 0 (zero means no limit).
 
<a id="security">Security</a>

If you intend to install this application on a public server and you want to limit the database host/ip the application may use then you will want to change the `restricted_hosts` parameter in the `app/config/config.yml` file. This is a comma-delimited list of dns host names and/or ip addresses of those database servers that the application won't be able to access.  
 
### Usage
##### Generating the database SQL script

The following information are required to create the PostgreSQL script of a MySQL database:
- Host name/IP address and the port number of the MySQL server
- MySQL user name and password
- optionally you may specify the SQL charset to use while encoding/writting the resulted SQL script
- optionally you may choose to add a `DROP` statement before each `CREATE TABLE` which will attempt to drop and recreate the tables on the target server if they already exist
 
You can generate the database SQL script using any of the following methods: 
- If you are using the WUI then just fill out the MySql connection parameters shown on the home page then click the `Generate SQL` button.
- If you are using the CUI then run the following command at your terminal:
```bash
cd mysql-to-postgres # the app directory
bin/console help db:mysql-script # will print-out the help for this command
```
and run the `bin/console db:mysql-script` command using the syntax shown by help.

##### Migrating the MySql database to Postgres SQL server

The following information are required to migrate the MySQL database to a PostgreSQL target server:
- the MySQL connection parameters described above
- Host name/IP address and the port number of the target PostgreSQL server
- PostgreSQL user name and password
- optionally you may specify to attempt to `DROP` the target tables if they already exists, regardless of the option you set at MySQL source options
- optionally you may choose to `TRUNCATE` the existent data within the PostgreSQL database before `INSERT`
- optionally you may choose to ignore the errors that may occur while running the migration script. This is desirable in case you are very-very disperate although this is not recommended
- optionally you may choose to run the whole migration enclosed within a SQL transaction. This will guarantee that either all statements are successfully executed otherwise the whole batch will be rolled-back

You can migrate the MySQL source database to a PostgreSQL target server using any of the following methods:
- If you are using the WUI then fill out the MySql and Postgres SQL connection parameters then click the `Migrate data` button.
- If you are using the CUI then run the following command at your terminal:
```bash
cd mysql-to-postgres # the app directory
bin/console help db:pg-migrate # will print-out the help for this command
```
and run the `bin/console db:pg-migrate` command using the syntax shown by help.
