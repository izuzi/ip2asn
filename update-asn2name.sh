#!/bin/bash
#
# IP 2 ASN
# Maps IP address to ASN.
#
# @version    0.6 (2017-11-17 19:36:19 GMT)
# @author     Peter Kahl <peter.kahl@colossalmind.com>
# @copyright  2015-2017 Peter Kahl
# @license    Apache License, Version 2.0
#
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#      <http://www.apache.org/licenses/LICENSE-2.0>
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#


# ====== User configuration (edit as needed) ======
#
# Cache directory
CACHEDIR="/srv/bgp"
#
# ========== Do not edit below this line ==========

cd $CACHEDIR


wget http://www.cidr-report.org/as2.0/autnums.html


# Strip HTML tags
sed -e 's/<[^>]*>//g' autnums.html > autnums.txt


# Convert to utf-8 encoding
iconv -f ISO-8859-1 -t UTF-8 autnums.txt > autnums.temp1


# Remove line starting with...
grep -v '^File\ last\ modified' autnums.temp1 > autnums.temp2


# Remove line starting with...
grep -E -v '^\ +\(UTC' autnums.temp2 > autnums.temp3


# Remove blank lines
grep -v '^$' autnums.temp3 > asnames.txt


rm $CACHEDIR/autnums.*

exit 0
