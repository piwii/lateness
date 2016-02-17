# Lateness

Manage lateness at work with forfeit

Made in PHP with silex framwork

## Documentation

### Deploy application
This application is made to be host on heroku server but you can deploy it on your own server.

Follow the followings step to deploy the apps
* Deploy the code on your webserver
* Configure database and launch init script (@todo create init script:-D )
* Set environment variables DATABASE_URL, SPRINT_NUMBER, TIMES

### Configure slack
* Go on https://ma-residence.slack.com/apps/manage/custom-integrations
* Then click on **Slash Commands**
* Then click on **Add configuration**
* Enter the name of command (ex: /late)
* Enter the url of your web server
* Fill other field like ou want

## Utils links

Manage slack custom integration : https://ma-residence.slack.com/apps/manage/custom-integrations

Help to format slackbot message : https://api.slack.com/docs/formatting

Free host : https://www.heroku.com/

## Todo

* Manage errors with exception
* Manage no result
* Secure application with slack token
* Script to init database

