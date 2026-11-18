# cPanel / CloudLinux deployment constraints

Laravel 13 requires PHP 8.3+ and framework PHP extensions (including mbstring, curl, DOM/XML, PDO MySQL, OpenSSL). MySQL 8/InnoDB, Cron and writable storage/bootstrap/cache are required. Verify separate web/CLI PHP versions. Host root privileges, systemd, Redis, Supervisor and a permanent worker are NOT assumed.

The HTTP document root MUST point to public/ and never expose .env or source files. Configure trusted proxies deliberately. HTTPS in production requires SESSION_SECURE_COOKIE=true and validated HSTS behavior. No runtime CDN for CSS/icons/fonts/JS.

Build frontend assets locally with npm run build and transfer public/build to the release; production does not need Node. Composer vendor dependencies must be provisioned via supported deploy workflow; never run install on live traffic blindly.

Example Cron (replace binary and paths after checking cPanel):

    * * * * * cd /home/USER/path/to/arsamnet-relay && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >/dev/null 2>&1

Verify single scheduler invocation, overlap locks and heartbeat. Actual hosting, DNS, TLS, mail delivery and outbound provider network have not been checked.
