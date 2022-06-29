# MeWe Name History

This is a website that displays the name history of a user on MeWe. It does this by searching for the user ID and grabbing the names from all the mentions that it finds. Since many posts are accessible to the author's contacts only, running the script on a user with many contacts will yield better results.

It is currently being hosted on [reimarpb.com/mewe-tools/name-history](https://reimarpb.com/mewe-tools/name-history).

### How to run

The script requires you to create an environment variable called `PHP_PRIVATE` which will be the path to a directory not publicly accessible to the internet.

Inside the directory should be a directory called `mewe-cookies` and inside that a file called `name-history.txt`. The exact path can be changed in the script.

The txt file must contain the cookies of a logged-in MeWe account in [curl's cookies.txt format](https://curl.se/docs/http-cookies.html#cookie-file-format). There are several extensions that let you grab this file directly from within the browser (search "cookies.txt extension")

When that is setup, run `php index.php`