H5P
===========

Create, share and reuse interactive HTML5 content on your site.

## Instructions

A comprehensive tutorial for how to install and manage dependencies can be found at [drupal.org](https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies).

### Download
If you are not requiring H5P from Composer you may download it from [the drupal project page](https://www.drupal.org/project/h5p).
The latest development version may be found on git.
```javascript
git clone --branch 8.x-1.x https://git.drupal.org/project/h5p.git
```

### Development

Composer dependencies are bundled with the module to ease installation.
If you're not a developer proceed to the installation section.

For developers, you can clone the Git repositories for the dependencies by
running ```rm -rf vendor/h5p && composer install --no-autoloader```

### Installation

After downloading the module you can enable it by:
1) Using the GUI at /admin/modules
2) Using the Drush command ```drush en h5p```
3) Using the Drupal CLI command ```drupal module:install h5p```

The next step is to add and configure H5P Fields

### Uninstall

Remember to delete any fields and clean up H5P Content entities before uninstalling.
You can uninstall the module by:
1) Using the GUI at /admin/modules
2) Using the Drush command ```drush pmu h5p```
3) Using the Drupal CLI command ```drupal module:uninstall h5p```

## Configuration
All configuration settings should be available through the Drupal GUI at /admin/config/system/h5p

### Administer libraries
In addition you may administer libraries that have been uploaded to your site at /admin/content/h5p. Here you will be able to:
- Upload new libraries
- Update content type cache
- Update existing content on your site
- Restrict library usage
- Delete libraries

## Restrictions
Embedding content types from Drupal 8 is not supported yet.
