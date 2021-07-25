# PageLocks Plugin for [Grav](http://getgrav.org)

**Note: This plugin is still in beta and should not be used in production.**

PageLocks is a useful plugin for Grav installations with several users working in the Admin backend. It activates a lock for any page that someone is editing so that nobody else can accidentally edit the same page (but they can look at it).

This only works if all editors are using the Admin panel. It will NOT help if
- someone uploads changes via FTP
- someone is using one of the frontend editing plugins.

## Installation

Installing the PageLocks plugin can be done in one of three ways: The GPM (Grav Package Manager) installation method lets you quickly install the plugin with a simple terminal command, the manual method lets you do so via a zip file, and the admin method lets you do so via the Admin Plugin.

### Admin Plugin (easiest)

If you use the Admin Plugin (which you most certainly do because otherwise you don't need this plugin), you can install PageLocks directly by browsing the `Plugins`-menu and clicking on the `Add` button.

### GPM Installation

To install the plugin via the [GPM](http://learn.getgrav.org/advanced/grav-gpm), through your system’s terminal (also called the command line), navigate to the root of your Grav-installation, and enter:

    bin/gpm install automagic-images

This will install the PageLocks plugin into your `/user/plugins`-directory within Grav. Its files can be found under `/your/site/grav/user/plugins/pagelocks`.

### Manual Installation

To install the plugin manually, download the zip-version of this repository and unzip it under `/your/site/grav/user/plugins`. Then rename the folder to `pagelocks`. You can find these files on [GitHub](https://github.com/skinofthesoul/grav-plugin-pagelocks) or via [GetGrav.org](http://getgrav.org/downloads/plugins).

You should now have all the plugin files under

    /your/site/grav/user/plugins/pagelocks


## Configuration
Here is the default configuration and an explanation of available options:

```
enabled: true
expiresAfter: 60            # Lock expires after x seconds of no keepAlive pings
keepAliveInterval: 30       # Send every x seconds a keepAlive ping to server
productionMode: false       # Add minified assets if true
debug: true                 # If true, write a log to data/pagelocks/debug.log
```

## How does it work?
If you'd like to see the plugin in action yourself, get two different user logins for your Grav installation and log into Admin with each of them in a separate browser, then try to edit the same page with both.

### Features:
- When lock is acquired, user is prevented from editing the page:
  - The form is "blocked" by adding an extra layer on top.
  - Editing buttons Move, Delete and Save are hidden
  - User can no longer toggle between Normal|Expert mode.
- Locks expire after `expiresAfter` seconds if no keepAlive has arrived during that period. 
  - E.g. when user moved out of Admin, or user went out for lunch...
  - User is notified when lock has been forcefully removed, or has expired.
- Keep alive request are being sent every `keepAliveInterval` seconds.
- Using option `debug` all lock state changes can me logged
- Messages in client are translated
- Users are notified in banner in top of page
- Javascript is generated using strongly typed Typescript


### Frontend: in Admin
- Two javascript files are added to Admin panel
  - `pagelocker.js`: Injected into every Admin page
    - Sends async requests to acquire lock
    - Sends async keepAlive requests to prevent lock from expiring
  - `pagelocks-admin.js`: Injected only into Admin page `/admin/locks`, the page to list/remove locks
    - Lists all acquired locks for all pages/users
    - User can sends async request to remove lock for a certain route

### Backend
- All locks are kept in file `/data/pagelocks/locks.yaml`
- On each async request on the server, any expired lock is removed
- If route of page is like `/admin/pages(/pagename)+`:
  - Any existing lock of current user is removed
  - A new lock is acquired for user
- Else:
  - Any existing lock of current user is removed
