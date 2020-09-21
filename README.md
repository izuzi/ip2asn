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

Script `asn-cache-purge.sh` is used to purge cached data. You may want to run this script hourly using crontab. (Edit parameters as needed.)

Script `update-asn2name.sh` is used to download database file for mapping AS numbers to their names. You may want to run this script (not more than) once daily using crontab. (Edit parameters as needed.)

## Usage

### IP to ASN (and all related information)
```php
use peterkahl\ip2asn\ip2asn;

$asnObj = new ip2asn('/srv/bgp'); # The argument is the cache directory.

$temp = $asnObj->getAsn('8.8.8.8'); # Accepts both IPv4 and IPv6

var_dump($temp);

/*
array(7) {
  ["as_number"]=>
  string(5) "15169"
  ["as_prefix"]=>
  string(10) "8.8.8.0/24"
  ["as_prefix_bin"]=>
  string(24) "000010000000100000001000"
  ["as_country_code"]=>
  string(2) "US"
  ["as_isp"]=>
  string(24) "GOOGLE - Google Inc., US"
  ["as_nic"]=>
  string(4) "ARIN"
  ["as_alloc"]=>
  string(0) "NA"
}
*/
```

### AS Number(s) to List of Prefixes
```php
use peterkahl\ip2asn\ip2asn;

$asnObj = new ip2asn('srv/bgp'); # The argument is the cache directory.

# This will get us an array of all prefixes for AS 94, 95, 96.
# The second argument defines your choice of IPv (4 or 6).
$temp = $asnObj->ArrayAsn2prefix(array(94, 95, 96), 6);

var_dump($temp);
```

### AS Number to Name (Description)
```php
use peterkahl\ip2asn\ip2asn;

$asnObj = new ip2asn('srv/bgp'); # The argument is the cache directory.

$temp = $asnObj->Asn2description(15169);

echo $temp; # "GOOGLE - Google Inc., US"
```
