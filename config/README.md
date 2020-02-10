Config files go in here.

Ensemble tries to find a config file that matches the IP address of the host
that it's running on, so that host configuration effectively piggybacks on
DHCP allocation.

Config files are just PHP files, so they can do pretty much whatever. In scope
will be:
    * `$conf`: An associative array of configuration information; look at setup.php for defined keys
