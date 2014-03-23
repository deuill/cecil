## Cecil, the de facto CMS for the Sleepy web framework

### Introduction

Cecil is a content management system, designed to tightly integrate with conventions
used by the Sleepy web framework. It is built as a canonical web application upon
Sleepy, and as such uses facilities available to all applications. Special care
has been given in maintaining seperation between Sleepy and Cecil, and as such
Cecil isn't *necessarily* required for building a web application upon Sleepy.

Cecil is largely modular, and allows for user-generated modules, created according
to specific needs. Each user in Cecil is assigned a seperate database, which contains
tables (dynamically created according to assigned modules) specific to the user.

Be aware that this is **alpha software**, and as such is functionally incomplete and
may contain bugs. However, Cecil is careful with destructive changes to databases
and files, and I have not encountered any major issues during use.

### Installation/Configuration

Cecil is designed to be installed as any other PHP-based webpage. Assuming you've
already set up your web-server to serve PHP files properly, simply copy Cecil into
the appropriate directory and set the root to the *"webroot"* subdirectory.

Cecil, as any other web application based on Sleepy, requires an **authorization
key** in order to make requests against the server. Since Cecil is usually the
first application installed to make use of Sleepy, you need to request a key
directly from the "sleepyd" binary on the server. To do so, run ```sleepyd user -a```
on the server, and Sleepy will return a valid authkey. Set this key in the *"authkey"*
field of the *"config.php"* configuration file, and you're all set (almost)!

Cecil provides a simple installation script for setting up the initial user and
database structure. Simply navigate to the *"install.php"* file on your web browser
and follow the steps as displayed.

If you've managed to make it this far, congratulations, you have a working instance
of Sleepy and Cecil going! Simply login as your administrator user and start making
users and modules.

### Anything else?

In order to start developing on your own, there exists a [template application](https://github.com/thoughtmonster/sleepy-app/) you
can use as a launching pad.

You can find a demo of Cecil [here](http://cecil-demo.thoughtmonster.org). The
credentials for the demo user are **demo** and **demo123**. File uploading is
disabled for the demo and the data is reset every now and then.

### License

Cecil is licensed under the terms of AGPL, version 3.
