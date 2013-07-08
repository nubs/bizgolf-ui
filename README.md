# Bizgolf-ui
A codegolf web interface using the [Bizgolf](https://github.com/nubs/bizgolf) library.  See that library for more about what code golf capabilities are available.

## Features
This web interface currently supports PHP 5.5 submissions only for any of the holes provided by Bizgolf.  User registration and login is provided for user's submitting scores.

### Homepage
The homepage includes a list of all the holes included for the hosted event and an overall scoreboard for the event.

![Homepage](docs/screenshots/homepage.png)

### Holes
Each hole has its own page where the hole's requirements are specified, including:
* a description of what is expected from the hole,
* constraints on the hole including the name of the constant where input is optionally given and the trim that is applied to the result before validation,
* and a sample program that passes the hole's validation and could be used as a basis for a solution or for local validation.

Also on the hole details page is a scoreboard including all of the users with passing submissions ordered by score with the shortest submissions first and a list of recent submissions, both passing and failing, including the lengths of the submissions.

![Hole Details](docs/screenshots/hole.png)

### Users
Each user has their own page with a list of their top submissions and a total score for them across all of the holes.

![User Details](docs/screenshots/user.png)

### Submitting Files
When logged in, there is a form for uploading submissions on each hole's page.

![Submitting Files](docs/screenshots/submit.png)

### Submissions
When logged in, any references to the authenticated user's submissions becomes a link to view the submission details.  This is particularly useful for failing submissions in order to track down what is not working.

![Authenticated Hole Details](docs/screenshots/hole-authenticated.png)

Here is an example failure, where the submitted file used a lowercase `w` where it should have used an uppercase `W`.  Included on this page is:
* the source code (including a raw form where it is NOT utf-8 encoded);
* the output of the submission, the expected output, and the difference of the two;
* the stderr output;
* the value of the constant for the submission;
* the exit status from executing the submission;
* the length of the submission in bytes.

![Submission Details](docs/screenshots/submission.png)

## Requirements
The web interface is written in PHP and uses some features from PHP 5.4, so older versions of PHP will not work.  Dependencies are managed using [Composer](http://getcomposer.org).  The [Bizgolf](https://github.com/nubs/bizgolf) library is the primary dependency and it depends on [Docker](http://www.docker.io).

For storing configuration and user data, [Mongo](http://www.mongodb.org) is used.  You will need to install the mongo pecl extension and configure Bizgolf-ui to point at a mongo database as described below.

The mcrypt PHP extension is also needed for the encrypted cookies.

## Installation
Using composer the project can be installed, along with its php dependencies, using the below command:

```bash
composer create-project --stability=dev nubs/bizgolf-ui
```

## Configuration
Two environment variables need to be set to configure the application.  `COOKIE_SECRET_KEY` needs to be set to a private and secure string that is used to encrypt the cookies used to store user authentication.  `MONGOHQ_URL` needs to be a url pointing to a mongo database, e.g. `mongodb://user:pass@example.com:10000/dbname`.

## Hosting
For testing, php's builtin webserver can work:
```bash
COOKIE_SECRET_KEY='your secret here' MONGOHQ_URL='mongodb://localhost:27017' php --server localhost:8000 --docroot public
```

A better setup for production hosting would be apache or nginx.  For example, here is an apache virtual host config as an example:
```apache
<VirtualHost *:80>
    ServerName bizgolf
    DocumentRoot "/srv/http/bizgolf-ui/public"
    SetEnv COOKIE_SECRET_KEY "your secret here"
    SetEnv MONGOHQ_URL "mongodb://localhost:27017"
    <Directory "/srv/http/bizgolf-ui/public">
        Order Allow,Deny
        Allow from all
        RewriteEngine On
        RewriteRule ^ index.php [L]
    </Directory>
</VirtualHost>
```

## Contributing
Any changes, suggestions, or bug reports are welcome to be submitted on github. Pull requests are welcome!

## License
This project is licensed under the MIT License.
