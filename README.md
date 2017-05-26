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

### Installation

1) goto  modules/contrib/h5p directory
2) run ```composer update```. This should add h5p to the vendor directory of your Drupal installation
3) Enable H5P through GUI at /admin/modules or with drush using ```drush en h5p```

### Uninstall
Uninstall through GUI at /admin/modules or with drush using ```drush pmu h5p```

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
