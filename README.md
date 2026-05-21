# Description
The SMF Web Access Real-Time Tracker, or WARTT, allows you to define actions to take when user-defined thresholds are met during a bot attack.

The goal here is to automate response, in real-time, of increased guest traffic.  Statistics are kept within a short time window, a couple of hours, for a set of IP buckets.  To keep things fast, these counters are maintained in memory resident tables where possible.  They are cleaned out periodically via a Scheduled Task, for the sake of speed and to ensure you don't blow up memory...

Logged-in users are not impacted.  Only guests & bots are.

WARTT does not allow you to block specific ASNs, countries or IP ranges.  Update your .htaccess for that.  Instead, WARTT tracks activity for all potential ASNs, and will take actions on the problem ones.  It doesn't know in advance which one is going to be a problem - it observes activity & accumulates the stats to determine that.

I.e., you don't tell WARTT what to block.  You tell WARTT what it should be looking at, e.g., ASNs, and if it identifies a problem ASN due to a threshold being exceeded, it can automatically block that.

The threshold check works both ways: when traffic falls back below the threshold, the block is lifted.

WARTT is rule-based.  Each rule defines a bucket of IPs, a threshold, and an action to take.  Guest hits to the site are tracked per each rule per each IP bucket encountered.

Thresholds are defined in terms of requests, per a specified number of minutes, per each IP bucket.  E.g., if 10,000 requests are made within 10 minutes per a certain block of IP addresses, take the action for that block of IP addresses.

Blocks of IP addresses may be tracked by:
 - IP mask, i.e., the first 3 nodes of an ipv4 IP address.
 - ASN, the ASN number of a network provider that identifies a set of their IP addresses.
 - Country, the country code associated with a set of IP addresses.

There are three ways to identify ASN or country:
 - If you have ASN or Country available via an environment variable, that may be used.  Some hosts provide this.
 - If you have ASN or Country available via a server variable, that may be used.  Some hosts provide this.
 - ASN or country may be looked up by WARTT by IP.  This requires that the SMF Web Access Log Analyzer (WALA) mod is installed, and the DBIP tables for ASN & country are loaded and current.

WARTT & WALA work hand-in-hand for IP lookups.

Potential actions include:
 - Block - disallow & return an http 429, "too many requests"; a percentage may be specified, e.g., to thin the herd.
 - Cease online logging - leverage SMF 2.1.8 feature to reduce impact of attack on the forum DB CPU; the 'guests online' statistic will not include this activity.
 - Cease view counts - leverage SMF 2.1.8 feature to reduce impact of attack on the forum DB CPU; the topic 'views' statistic will not include this activity.
 - Log only - perfect for testing thresholds.

Restrictions:
 - Since Memory tables are used, counter and block information is lost upon database restarts.  They build back up pretty quickly, though...
 - To use the "stop online logging" feature, SMF 2.1.8 must be running.
 - To use the "stop view counts" feature, SMF 2.1.8 must be running.
 - To use ASN or country lookups by IP, the SMF Web Access Log Analyzer (WALA) mod must be used, and the DBIP tables for ASN and country must be loaded & current.

Guidance:
 - TEST & OBSERVE CLOSELY...
 - Don't use too many rules...  Don't ask for too many buckets to be tracked...  You will blow up memory.
 - Start simple.  A rule or two.  Log entries for a while until you understand what the impact is.  Only cut over to other actions when you are comfortable with the results in the log.
 - The DBIP ASN & Country tables are updated monthly.  If you are using them, you should stay current and update them monthly.  IPs change hands a lot...
 - TEST & OBSERVE CLOSELY...
