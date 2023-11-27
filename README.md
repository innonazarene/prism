# Prism
**Prism** is a (FREE) command-line tool for initializing all tasks required for building a Laravel-based backend application. The tool performs various tasks, including database migration, seeding, creating models, routes, and controllers based on the database defined in the **.env** file.

## Installation

To install **Prism**, follow these steps:

1.  Clone the repository or download the source code.
2.  Run `composer install` to install the required dependencies.
3.  Copy the `.env.example` file and rename it to `.env`. Then, update the file with your database credentials.
4.  Run the `php artisan prism:init` command to initialize the backend.

## Usage

To use **Prism**, run the following command:

Copy code

`php artisan prism:init`

This will initialize all the required tasks for building a Laravel-based backend, including database migration, seeding, creating models, routes, and controllers.

## Configuration

**Prism** uses the following configuration options:

-   `$packages`: An array of packages that the tool will check for and install if they are not already installed.


## Contributing

If you want to contribute to this project, feel free to submit a pull request or open an issue.


## Packages

|Package List| Github|
|--|--|
| Krlove Eloquent-Model-Generator|  **https://github.com/krlove/eloquent-model-generator**|
| kitloong Laravel-Migration-Generator|  **https://github.com/kitloong/laravel-migrations-generator**|
| orangehill/iseed|  **https://github.com/orangehill/iseed**|


## License

This Prism is open-sourced software licensed under  [MIT license](https://opensource.org/licenses/MIT).
