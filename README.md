# Download

- git clone --branch 8.x-1.x https://git.drupal.org/sandbox/joergM/2853930.git h5p

# Installation

- goto  modules/contrib/h5p directory
- run composer update. As result within the modules directory there should be a vendors directory
- drush en h5p

# Deinstallation

- drush pmu h5p --no-halt-on-error

# Configuration
## Administration Settings
- goto admin/content/system H5P
- disable Use H5P Hub

## Upload legacy Libraries
- goto admin/content
- select tab 'H5P Libraries'
- upload H5P Libraries from https://h5p.org/update-all-content-types

# Restrictions
- embed type div not yet supported


