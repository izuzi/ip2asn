#!/usr/bin/env bash
#
# AS number to ISP/ORG database downloader script.
#
# This script is part of ip2asn PHP library.
#
# @version    2020-09-21 12:49:00 UTC
# @author     Peter Kahl <https://github.com/peterkahl>
# @copyright  2015-2020 Peter Kahl
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

# ============= User configuration (edit as needed) ==================

# Cache directory
CACHEDIR="/srv/bgp"

#SOURCEURL="https://bgp.potaroo.net/cidr/autnums.html"
SOURCEURL="https://www.cidr-report.org/as2.0/autnums.html"


# ================= Do not edit below this line ======================

# Disable unicode
LC_ALL=C
LANG=C

TSTARTSEC="$(date +"%s")"

MODULENAME="ip2asn"

SUBNAME="updater"

filename="${CACHEDIR}/${MODULENAME}_asn2name.db"

# Whether to provide debug info?
# 0 ..... only errors
# 1 ..... medium
# 2 ..... every useless detail
LOG_LEVEL="2"

debugLog="${CACHEDIR}/${MODULENAME}_debug.log"

UAGENT="Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:80.0) Gecko/20100101 Firefox/80.0"

# ====================================================================

function stopwatch()
{
  local start="$1"
  local intval="$(($(date +"%s")-start))"
  if (( intval >= 60 ))
  then
    local mins="$((intval/60))"
    local secs="$((intval%60))"
    local result=""
    (( mins > 0 )) && result="${mins}min"
    (( secs > 0 )) && result="${result} ${secs}sec"
    printf %s "$(echo "$result" | sed 's/^ //')"
  else
    printf %s "${intval}sec"
  fi
}

function log_write()
{
  # Usage:
  # $ log_write <string> <severity>
  local string="$1"
  local severity="$2"
  (( severity <= LOG_LEVEL )) && echo "$(date +"%Y-%m-%d %H:%M:%S") $MODULENAME/$SUBNAME[$BASHPID]: $string" >> $debugLog
}

function RandomString()
{
  printf %s "$(openssl rand -base64 13 | tr -cd "[0-9A-Za-z]")"
}

function get_lcount()
{
  printf %s "$(wc -l $1 | cut -d " " -f1)"
}

# ====================================================================


TEMPDIR="${CACHEDIR}/${MODULENAME}_tmpdir_$(RandomString)"

mkdir "${TEMPDIR}"

cd $TEMPDIR

TEMP0="${TEMPDIR}/autnums.html"
TEMP1="${TEMPDIR}/1.tmp"
TEMP2="${TEMPDIR}/2.tmp"


log_write "Downloading $SOURCEURL" "1"

curl -L --compressed --silent --header "cache-control: max-age=0" --header "accept: */*" --header "accept-language: en-GB,en;q=0.5" -A "${UAGENT}" --output "$TEMP0" "$SOURCEURL"


# Strip HTML tags
sed -e 's/<[^>]*>//g' $TEMP0 > $TEMP1

# Only lines starting with...
grep -P '^AS\d+[\s\S]+' $TEMP1 > $TEMP2


chown www-data:www-data $TEMP2 && chmod 0644 $TEMP2
mv $TEMP2 $filename

cd ..
rm -rf $TEMPDIR

if [ -s $filename ]
then
  log_write "OK: File $filename exists with $(get_lcount $filename) lines" "1"
else
  log_write "CRIT: File $filename not found or empty" "1"
fi


log_write "Process completed in $(stopwatch "$TSTARTSEC")" "2"

exit 0
