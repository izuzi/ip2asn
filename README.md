# ip2asn

[![Downloads](https://img.shields.io/packagist/dt/peterkahl/ip2asn.svg)](https://packagist.org/packages/peterkahl/ip2asn.svg)
[![Download per Month](https://img.shields.io/packagist/dm/peterkahl/ip2asn.svg)](https://packagist.org/packages/peterkahl/ip2asn)
[![License](https://img.shields.io/github/license/peterkahl/ip2asn.svg?logo=License)](https://github.com/peterkahl/ip2asn/blob/master/LICENSE)
[![If this project has business value for you then don't hesitate to support me with a small donation.](https://img.shields.io/badge/Donations-via%20Paypal-blue.svg)](https://www.paypal.me/PeterK93)

Maps IP address to ASN. ASN to prefix. ASN to name. Both IPv4 and IPv6.

## Required

* Writeable cache directory (permissions, ownership)
* PHP functions `exec()` and `shell_exec()` are not disabled
* Can execute bash scripts (using crontab)
* Package `whois` installed
* Package `dnsutils` installed

## Installing Required Packages

```
sudo apt install whois dnsutils
```

## Shell Scripts

Essential component of this library are 2 shell scripts.

Script `ip2asn_purger.sh` is used to purge cached data. You may want to run this script hourly using crontab. (Edit parameters as needed.)

Script `ip2asn_updater.sh` is used to download database file for mapping AS numbers to their names. You may want to run this script (not more than) once daily using crontab. (Edit parameters as needed.)

## Usage

### IP to ASN (and all related information)
```php
use peterkahl\ip2asn\ip2asn;

$asnObj=new ip2asn('/srv/bgp'); # The argument is the cache directory.

$temp=$asnObj->getAsn('8.8.8.8'); # Accepts both IPv4 and IPv6

var_dump($temp);

/*
array(9) {
  ["as_timestamp"]=>
  string(10) "1604703722"
  ["as_prefix_bin"]=>
  string(24) "000010000000100000001000"
  ["as_prefix"]=>
  string(10) "8.8.8.0/24"
  ["as_country_code"]=>
  string(2) "US"
  ["as_number"]=>
  string(5) "15169"
  ["as_isp"]=>
  string(10) "GOOGLE, US"
  ["as_nic"]=>
  string(4) "ARIN"
  ["as_alloc"]=>
  string(10) "1992-12-01"
  ["as_source"]=>
  string(6) "ip2asn"
}
*/
```

### AS Number(s) to List of Prefixes
```php
use peterkahl\ip2asn\ip2asn;

$asnObj=new ip2asn('srv/bgp'); # The argument is the cache directory.

# This will get us an array of all prefixes for AS 63949.
# The second argument defines your choice of IPv (4 or 6).
$temp=$asnObj->ArrayAsn2prefix(array(63949), 6);

var_dump($temp);
/*
array(26) {
  [24]=>
  string(14) "2a01:7e00::/32"
  [25]=>
  string(14) "2a01:7e01::/32"
  [0]=>
  string(14) "2400:8900::/31"
  [1]=>
  string(14) "2400:8901::/32"
  [2]=>
  string(14) "2400:8902::/31"
  [3]=>
  string(14) "2400:8903::/32"
  [4]=>
  string(14) "2400:8904::/31"
  [5]=>
  string(14) "2400:8905::/32"
  [6]=>
  string(14) "2400:8906::/31"
  [7]=>
  string(14) "2400:8907::/32"
  [18]=>
  string(14) "2600:3c0a::/32"
  [19]=>
  string(14) "2600:3c0b::/32"
  [20]=>
  string(14) "2600:3c0c::/32"
  [21]=>
  string(14) "2600:3c0d::/32"
  [22]=>
  string(14) "2600:3c0e::/32"
  [23]=>
  string(14) "2600:3c0f::/32"
  [8]=>
  string(14) "2600:3c00::/32"
  [9]=>
  string(14) "2600:3c01::/32"
  [10]=>
  string(14) "2600:3c02::/32"
  [11]=>
  string(14) "2600:3c03::/32"
  [12]=>
  string(14) "2600:3c04::/32"
  [13]=>
  string(14) "2600:3c05::/32"
  [14]=>
  string(14) "2600:3c06::/32"
  [15]=>
  string(14) "2600:3c07::/32"
  [16]=>
  string(14) "2600:3c08::/32"
  [17]=>
  string(14) "2600:3c09::/32"
}
*/
```

### AS Number to Name (Description)
```php
use peterkahl\ip2asn\ip2asn;

$asnObj=new ip2asn('srv/bgp'); # The argument is the cache directory.

$temp=$asnObj->Asn2description(63949);

echo $temp; # "LINODE-AP Linode, LLC, US"
```
