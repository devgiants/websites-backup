[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/devgiants/websites-backup/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/devgiants/websites-backup/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/devgiants/websites-backup/badges/build.png?b=master)](https://scrutinizer-ci.com/g/devgiants/websites-backup/build-status/master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/devgiants/websites-backup/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)
# Websites Backup 1.3.2
## Presentation
Allow to backup sites (databases + files) easily using a YML configuration file.

## Installation

```
# Get the application
wget https://devgiants.github.io/websites-backup/downloads/websites-backup-1.3.2.phar

# Move it in command folder
mv websites-backup-1.3.2.phar /usr/bin/backup

# Make it executable
chmod u+x /usr/bin/backup
```

## Usage

### Backup
You can use it to backup files + database (MySQL only so far). You can provide as much sites as you want, and several storage medias as well.

`backup save --file=config.yml`

Below a full YAML example, commented :

```
configuration:
  # this is the max backup number you accept to keep on each storage media provided below
  remanence: 5
  # this is the log path you want. Default to /tmp/websites-backup-logs/
  log_folder: "/path/to/log/folder"
  sites:
    # Add as much site section as you want. The key here is free, and will be used on retrieve command
    site-1:
      # Set of commands you want to see run BEFORE backup. ATTENTION : the commands are executed without any verifications
      pre_save_commands:
        - "mkdir /tmp/pre_test_command"
      database:
        server: localhost
        user: root
        password: "password"
        name: database_name
      files:
        # root dir to refer to
        root_dir: "/var/www/html/my-site/"
        # Folder list to include (root dir based)
        include:
          - "my-folder/to-include"
        # Files/Folder list to exclude (root dir based)
        exclude:
          - "my-folder/to-exclude"
          - "my-folder2/my-specific-file.file"
        # Specific storages among those listed below. Optionnal : if not provided, backup will use all storages available
      backup_storages:
        - dropbox_test
      post_save_commands:
        - "mkdir /tmp/post_test_command"

    site-2:
        ...

  backup_storages:
    # key here is free as well. Same as above, will be used in retrieve command to specify storage media to retrieve backup from
    ftp1:
      # So far, FTP only
      type: FTP
      # For opening connection with SSL. Set it to false or don't mention it for regular FTP connection
      ssl: true
      # Passive mode
      passive: true
      server: ftp.server.com
      user: ftp_user
      password: "password"
      # Remote root dir
      root_dir: "/sites"

    dropbox_test:
      type: Dropbox
      # Obtain those 3 params from you dropbox account page, app section.
      client_id: clientid
      client_secret: clientsecret
      access_token: accesstoken
      root_dir: "/"
```

### Retrieve

The retrieve method will allow you to get one backup back :

`backup retrieve --file=config.yml`

The command will ask you :
1. Which storage to retrieve backup from (among the storages defined in config file, and if you didn'tvmention it already with the -s option (`backup retrieve -f=config.yml -s=ftp1`))
2. Which site you want to retrieve backup from
3. Backup timestamp (among the availables ones)

Then it will put the files (basically tar + sql files) in /tmp folder.

The application is modular, each storage is created a Devgiants/Storage/Storage class instance.

More to come : SSH Storage, Local filesystem Storage, backup executed as user, tests...

### Self-update
`backup self-update` will automatically update the PHAR archive.

## Dev and dev usage
### Docker stack
The app comes with an Docker stack which configure 3 images :
- PHP FPM (including Composer and Box for PHAR packaging)
- MySQL
- PHPMyAdmin

As it's a pure console app, two last are just here on dev testing purpose because it allow app to connect to MySQL.

#### First run 
Just update `.env` file with you data. Then you can execute `init.sh`.

#### Day-to-day work
Just do a `docker-compose up -d` like any other Docker stack.

### Logs
Logs are quite verbose. 2 distincts channels are created :
- the `main` one for global events
- one channel per site on each command (backup and retrieve)

Both are implemented using the `RotatingFileHandler` from Monolog.

### PHAR Packager
This bash script goal is to ease tedious PHAR packaging process for a open-source app published on Github. This script is very specific to following case :
- Open-source project hosted on Github
- Use [Box](https://github.com/box-project/box2) for create package
- Use [Kherge version (abandoned)](https://github.com/kherge-abandoned/Version) to handle version number. TODO is to switch to maintained project ASAP
- Use a manifest system to publish phar archive to gh-pages. You can have live example on applications I created such as [livebox](https://github.com/devgiants/livebox/blob/gh-pages/manifest.json) or [websites-backup](https://github.com/devgiants/websites-backup/blob/gh-pages/manifest.json)

#### Usage

Just make sur your local `master` branch is up-to-date, with README updated with good version number (the one you publish). Then: 

`./make-phar -n appname -v 1.2.3`


This will do, in order : 
1. Create local tag with version passed as argument
2. Push `master` branch to remote repo
3. Push tags to remote repo
4. Build PHAR using `box`
5. Move generated PHAR to `/tmp`
6. Checkout to `gh-pages`
7. Move generated archive from `/tmp` to `downloads/{appname}-{version}.phar`
8. Create matching manifest file
9. Push everything to `gh-page` remote branch.
10. Switch back to `master`
