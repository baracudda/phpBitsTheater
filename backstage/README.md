Backstage Scripts
=================

This directory contains executable scripts that we do not want
accessible via the webroot, yet utilize the website to perform
tasks like updating the website schema without requiring a login
or searching all app-data related tables in every org for a
particular table/column value.

Place this directory outside of the website root directory tree,
ensuring they are not accidentally served up via the webserver
and therefore only accessible when logged into the website server.

