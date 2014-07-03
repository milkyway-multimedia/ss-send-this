Silverstripe Backups
======
**Silverstripe Backups** is a small backup package allowing users to download a back up of their assets and database.

You can also register other collectors (classes specifically designed to dump data for backing up) and methods (classes specifically designed to handle dumped data)

## Install
Add the following to your composer.json file
```

    "require"          : {
		"milkyway-multimedia/silverstripe-backups": "dev-master"
	}
	
```

## About
The module provides the following configuration by default:

### Collectors
1. Assets: Backs up the assets folder and stores it in a compressed tar archive (.tar.gz)
2. Database: Dump the database the application is using

### Methods
1. On-site: Backs up the files in another directory within the current server
2. Via email: Emails a back-up to the administrator or other email address

#### The following methods require installation of the [Lusitanian/PHPoAuthLib library](https://github.com/Lusitanian/PHPoAuthLib)
1. Via Google Drive: Backs up to Google Drive

**Oauth methods are not enabled by default, since they require additional configuration, specifically a consumer ID and/or consumer secret**

## License 
* MIT

## Version 
* Version 0.1 - Alpha

## Contact
#### Milkyway Multimedia
* Homepage: http://milkywaymultimedia.com.au
* E-mail: mell@milkywaymultimedia.com.au
* Twitter: [@mwmdesign](https://twitter.com/mwmdesign "mwmdesign on twitter")