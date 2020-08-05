1. Create database if required, if not, skip to step 3.
2. Run scripts/setup.sql on your database.
3. Update settings.php
4. Create site with redirecting all URLs that don't match the regex pattern of "(\.css)|(\.svg)|(\.js)|(\.ico)" (include all other image formats in the same pattern) and redirect to route.php
