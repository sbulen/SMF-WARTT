# Description
The SMF Web Access Real-Time Tracker, or WARTT, allows the admin to define certain actions to take upon certain thresholds of a bot attack.  Up to 10 thresholds & actions may be specified.  All actions are logged.  

Potential actions include:
 - Block - disallow & return an http 429, "too many requests"
 - Block % - disallow a specified percentage, thin the herd
 - Cease online logging - leverage SMF 2.1.8 feature to reduce impact of attack
 - Cease view counts - leverage SMF 2.1.8 feature to reduce impact of attack
 - Log only - perfect for testing thresholds

Thresholds are defined in terms of requests, per a specified number of minutes, per a specified address range.  E.g., if 10,000 requests are made within 10 minutes per a certain block of IP addresses, take the action.

IP address ranges may be specified as an environment variable, if available.  E.g., some hosts pass along the country code or the ASN number, of the requestor.  If available, you can track the requests by either the country or the ASN.

If your host provides no such environment variable, the stats are tracked by the first nodes of the IP.  For IPv4, requests for the first 3 nodes are tracked.  For IPv6, requests for the first 7 nodes are tracked.
