rempower
========

web interface to control power rack

This is a simple php script written quickly to match a simple problem: 
* be able to control more than one power outlet at a time
* select outlets by name, without knowing on which power rack they are plugged

It doesn't:
* configure proper names in the racks for you (we do that when plugging cables)
* prepare coffee while you try to debug

# WARNING
* NO SECURITY lies here: access control is on the server (**dont** make that 
thing available from the whole internet)
* NO GARANTEE: we just share this as it could help some

# install
Copy config.sample.php to config.php and edit it to match your config

* **$GLOBALS["apcids"]** should list your rack(s) names and IP's
* **$GLOBALS["apcsnmp"]** should match your SNMP write community

# use
Just go to the url of the scripts, your outlets should list themselves
with the names you configured in the racks.

# TODO
* notifications by mail
* implement a bit more security
