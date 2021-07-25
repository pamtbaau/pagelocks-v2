## Requirements:
- Run `$ composer update` to install [Codeception](https://codeception.com/) and others
- Standard Grav website with pages 'Home' and 'Typography'
- For acceptance tests:
  - Google Chrome
  - [ChromeDriver](https://chromedriver.chromium.org/downloads) with same version number as Chrome
  - User account with the following properties:
    ```
    email: author1@domain.com
    fullname: 'McAuthor 1'
    title: 'Author 1'
    access:
    site:
      login: true
    admin:
      login: true
      locks: true     # Required to access the Lock admin page
      pages:          # Required to edit pages
        create: true
        read: true
        update: true
        delete: true
        list: true
  ```
## Running tests:
In `composer.json` several scripts have been defined to run the tests:
- `$ cd user/plugins/pagelocks`
- `$ composer test-all`