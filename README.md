# websocket_testing

[Video demonstration](https://sample.asuscomm.com/websockets/index.php)

This is a fully working, but very basic websocket-based chat program. This was created as part of a project to learn about sockets.

To use this, download the files to your local file system, then run the following from the appropriate working folder in your command line:

`php -q chat_server.php`

That will start the chat server.

To chat as a user, you'll need to load `client.php` in your web browser. This means configuring your webserver so that you can load this page via a host and port (e.g. `http://10.10.10.5:9002/client.php`).

You can chat using as many users as you like, just open a new browser tab for each user.