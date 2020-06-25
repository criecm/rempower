rempower
========

web interface to control power rack

This is a simple php script written quickly to match a simple problem: 
* be able to control more than one power outlet at a time
* select outlets by name, without knowing on which power rack they are plugged
* configure outlets names in one clic (you have to know the rack/port numbers for this :)
* configure timers (fixed delays corrected by rack to spread the load in case of power surge)

It doesn't:
* prepare coffee while you try to debug
* authorize not authenticate anything

# WARNING
* NO SECURITY lies here: access control is on the server (**dont** make that 
thing available from the whole internet)
* NO GARANTEE: we just share this as it could help some

# install
Copy config.sample.php to config.php and edit it to match your config

* **$GLOBALS["apcids"]** should list your rack(s) names and IP's
* **$GLOBALS["apcsnmp"]** should match your SNMP write community

# test
`./scripts/liste_apc` will list configured power plugs

# use
Just go to the url of the scripts, your outlets should list themselves
with the names you configured in the racks.

