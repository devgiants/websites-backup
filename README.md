# Websites Backup 1.2.0
## Presentation
Allow to backup sites (databases + files) easily using a YML configuration file.

## Installation

```
# Get the application
wget https://devgiants.github.io/websites-backup/downloads/website-backup-1.2.0.phar

# Move it in command folder
mv website-backup-1.2.0.phar /usr/bin/backup

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

    ftp2:
      ....
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

More to come : Dropbox storage, SSH Storage, Local filesystem Storage, backup executed as user, tests...

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

### Boxing PHAR
You need PHP + [Box](https://github.com/box-project/box2) on host machine. Here is the process :

1. Make your edits
2. Commit and create tag (the box configuration here uses the last tag)
3. Run `box build` and get your shiny new PHAR.