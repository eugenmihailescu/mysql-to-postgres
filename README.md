# MySql to Postgres database migrator
This application allows you to migrate a MySql databse to a Postgres server. It has the following functionalities:
- generate a Postgres SQL script of a MySql database
- migrate the MySql database to a Postgres SQL server
- web user interface (WUI) and console user interface (CUI)


It is built on top of Symfony Framework 3.0.

### Requirements
* PHP 5.5.9 :: [read more](http://symfony.com/doc/current/reference/requirements.html)
* [composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)
* 60+ MB space on disk
### How to install

Create a directory `DIR` on the system where you want to install this application. Type the following command at the terminal:
```bash
cd  DIR # the newly created directory
git clone https://github.com/eugenmihailescu/mysql-to-postgres.git # close the project at DIR
cd mysql-to-postgres # change into the newly cloned app directory
composer install # this will install the project dependencies
```
At some point the install script may ask you to enter some parameters like `database_host`, `mailer_host`, etc. Just respond with the default values (ie. press Enter).

So far you installed the application. If you want to test it locally then just run the followin commands at your terminal:
```bash
cd mysql-to-postgres # the app directory
bin/console server:run # now test the app at: http://localhost:8000
```
In case you want to install this application at a web server then [follow these instruction](http://symfony.com/doc/current/cookbook/configuration/web_server_configuration.html).

### Usage
##### Generating the database SQL script

- If you are using the WUI then just fill out the MySql connection parameters then click the `Generate SQL` button.
- If you are using the CUI then run the following command at your terminal:
```bash
cd mysql-to-postgres # the app directory
bin/console help db:mysql-script # will print-out the help for this command
```
and run the `bin/console db:mysql-script` command using the syntax shown by help.

##### Migrating the MySql database to Postgres SQL server
- If you are using the WUI then fill out the MySql and Postgres SQL connection parameters then click the `Migrate data` button.
- If you are using the CUI then run the following command at your terminal:
```bash
cd mysql-to-postgres # the app directory
bin/console help db:pg-migrate # will print-out the help for this command
```
and run the `bin/console db:pg-migrate` command using the syntax shown by help.