<?php
/**
 * bootstrap.php
 *
 * Bootstraps the application - loads the datasource config and checks for an authenticated user session
 *
 * Author: Mark Troyer <disco@box.com>
 * Date Created: 20 May 2013
 */

// Load the PEAR::Auth module for authenticated sessions
require_once "Auth.php";

// Load app config, datasource config and authentication config
SWConfig::load_config('statuswolf.conf');
SWConfig::load_config('datasource.conf');
SWConfig::load_config('auth.conf');

// App config, general config for StatusWolf
$app_config = SWConfig::read_values('statuswolf');

// Auth method - which backend are we using for authentication?
$auth_method = SWConfig::read_values('auth.method');

// Load the options for the auth backend
$auth_options = SWConfig::read_values('auth.' . $auth_method);

// Set the name used for session management, defaults to '_sw_authsession' to avoid conflicts
// with other apps on the server that may be using the default Auth session naming
if (! $auth_options['sessionName'] = SWConfig::read_values('auth.sessionName'))
{
  $auth_options['sessionName'] = '_sw_authsession';
}

if (array_key_exists('debug', $app_config) && $app_config['debug'])
{
  $auth_options['enableLogging'] = true;
}

if (array_key_exists('session_handler', $app_config))
{
  $session_config = SWConfig::read_values('statuswolf.session_handler');
  $handler_type = ucfirst($session_config['handler']) . 'SessionHandler';
  require_once APPLIB . 'Util' . DS . 'Session' . DS . $handler_type . '.php';
  if ($session_handler = new $handler_type($session_config))
  {
    session_set_save_handler(array($session_handler, 'open')
                             ,array($session_handler, 'close')
                             ,array($session_handler, 'read')
                             ,array($session_handler, 'write')
                             ,array($session_handler, 'destroy')
                             ,array($session_handler, 'gc'));
  }
}

// Base login function, prints the login form if no active auth session exists
function login($username = null, $status = null, &$auth = null)
{
  include 'header.php';
  include 'login.php';
  include 'footer.php';
//  echo "<form method=\"post\" action=\"/StatusWolf/\">";
//  echo "<input type=\"text\" name=\"username\">";
//  echo "<input type=\"password\" name=\"password\">";
//  echo "<input type=\"submit\">";
//  echo "</form>";
}

// Function to deal with failed user login attempts
function login_failed($user = null, &$auth = null)
{
  if ($auth->getStatus() == '-3')
  {
    $_SESSION['_auth_fail'] = "Username or password are incorrect";
  }
  else
  {
    $_SESSION['_auth_fail'] = "Login failed";
  }
}

// Create authentication object
$sw_auth = new Auth($auth_method, $auth_options, 'login');


// Set the function to use on failed login attempts
$sw_auth->setFailedLoginCallback('login_failed');

// Start the new auth session
$sw_auth->start();

// Register the autoload method now that we have a session to cache to
spl_autoload_register(array('SWAutoLoader', 'sw_autoloader'));
spl_autoload_register();
spl_autoload_extensions('.php');
if (array_key_exists('debug', $app_config) && $app_config['debug'])
{
  $_SESSION['debug'] = array();
}

if (array_key_exists('enableLogging', $auth_options) && $auth_options['enableLogging'])
{
  require_once "Log.php";
  require_once "Log/observer.php";
  $debug_log_observer = new SWAuthLogObserver(PEAR_LOG_DEBUG);
  $sw_auth->attachLogObserver($debug_log_observer);
}

// Logout the user, restart the session and present a login form
if (array_key_exists('action', $_GET))
{
  if ($_GET['action'] == "logout" && $sw_auth->checkAuth())
  {
    $sw_auth->logout();
    $sw_auth->start();
  }
}

// Check for a logged in session
if ($sw_auth->checkAuth())
{
  $usersession = &$_SESSION[$auth_options['sessionName']];
  print "User " . $usersession['username'] . " is logged in!\n";
  $bootstrap = true;
}

if (array_key_exists('enableLogging', $auth_options) && $auth_options['enableLogging'])
{
  $auth_log = array();
  foreach ($debug_log_observer->auth_log_messages as $debug_event)
  {
    $auth_log[] = $debug_event['priority'] . ": " . $debug_event['message'];
  }
  $_SESSION['debug']['auth_log'] = $auth_log;
}