<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| SYSTEM DIRECTORY NAME
| -------------------------------------------------------------------
|
| This variable must contain the name of your "system" directory.
| Set the path if it is not in the same directory as this file.
|
*/
$system_path = '../system';

/*
| -------------------------------------------------------------------
| APPLICATION DIRECTORY NAME
| -------------------------------------------------------------------
|
| If you want this front controller to use a different "application"
| directory than the default one you can set its name here. The directory
| can also be renamed or relocated anywhere on your server. If you do,
| use an absolute path.
|
*/
$application_folder = '../application';

/*
| -------------------------------------------------------------------
| VIEW DIRECTORY NAME
| -------------------------------------------------------------------
|
| If you want to move the view directory out of the application
| directory, set the path to it here. The directory can be renamed
| and relocated anywhere on your server. If blank, it will default
| to the standard location inside your application directory.
|
*/
$view_folder = '';

/*
| -------------------------------------------------------------------
| WRITABLE DIRECTORY NAME
| -------------------------------------------------------------------
|
| This variable must contain the name of your "writable" directory.
| The writable directory allows you to group all directories that
| need write permission to a single place.
|
*/
$writable_path = '../writable';

/*
| -------------------------------------------------------------------
| DEFAULT CONTROLLER
| -------------------------------------------------------------------
|
| Normally you will set your default controller in the routes.php file.
| You can, however, force a custom routing by hard-coding a
| specific controller class/function here. For most applications, you
| WILL NOT set your routing here, but it's an option for those
| special instances where you might want to override the standard
| routing in a specific front controller that shares a common CI installation.
|
*/

// The directory name, relative to the "controllers" directory.  Leave blank
// if your controller is not in a sub-directory within the "controllers" directory
$routing['directory'] = '';

// The controller class file name.  Example:  mycontroller
$routing['controller'] = '';

// The controller function you wish to be called.
$routing['function']	= ''; 