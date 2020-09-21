<?php
/**
 * ip2asn
 * Maps IP address to as number and related methods.
 *
 * @version    2020-09-21 09:47:00 UTC
 * @author     Peter Kahl <https://github.com/peterkahl>
 * @copyright  2015-2020 Peter Kahl
 * @license    Apache License, Version 2.0
 *
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      <http://www.apache.org/licenses/LICENSE-2.0>
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace peterkahl\ip2asn;

use \SplFileObject;
use \Exception;


class ip2asn
{


  /**
   * Name of this library as first part of file name
   * @var string
   */
  const NAME_PREFIX = 'ip2asn_';


  /**
   * Filename prefix incl. full path to cache directory.
   * @var string
   */
  private $_file_prefix;


  /**
   * Filename suffix of ASN to name lookup.
   * @var string
   */
  const ASN2NAME_FILESUFFIX = 'asn2name.db';


  /**
   * Filename of ASN to name lookup.
   * (The full name will be cachdir+prefix+suffix.)
   * @var string
   */
  private $_asn2name_filename;


  /**
   * User-specific maximum age of cache files.
   * @var integer
   */
  public $cache_time;


  /**
   * Maximum age of cache files.
   * @var integer
   */
  private $_caching_time;


  /**
   * Default value of maximum age of cache files.
   * @var integer
   */
  const CACHING_TIME_DEFAULT = 604800;


  /**
   * Constructor
   * @param  string ... cache directory
   */
  public function __construct($dir = '')
  {
    $dir = rtrim($dir, '/');
    $this->_check_cachedir($dir);
    $this->_file_prefix = "$dir/". self::NAME_PREFIX;
    $this->_asn2name_filename = "$dir/". self::NAME_PREFIX . self::ASN2NAME_FILESUFFIX;
  }


  /**
   * Returns AS number and associated data. Employs caching.
   * @param  string
   * @param  string
   * @param  integer
   * @return array
   * @throws \Exception
   */
  public function getAsn($ip, $bin = '', $ver = 0)
  {
    $arr = array();
    $ip = trim($ip);

    if (empty($ver)) {
      $ver = $this->_get_ipv($ip);
    }

    if (empty($bin)) {
      $bin = $this->_ip2binstr($ip, $ver);
    }

    if ($ver == 4 || $ver == 6) {
      $filename = $this->_get_file_prefix() ."v${ver}_asdata.cache";
    }
    else {
      throw new Exception("Illegal value argument ver");
    }

    if (file_exists($filename) && (filemtime($filename) + $this->_get_caching_time()) > time())
    {

      $fileObj = new SplFileObject($filename, 'r');
      while (!$fileObj->flock(LOCK_EX)) {
        usleep(1);
      }
      while (!$fileObj->eof()) {
        $line = $fileObj->fgets();
        $line = trim($line);
        if (strpos($line, '|') !== false) {
          list($epoch, $prefixBin, $prefix, $code, $num, $isp, $nic, $alloc) = explode('|', $line);
          if ($this->_match_bin_strings($bin, $prefixBin)) {
            if (strpos($num, ' ') !== false) {
              $num = $this->_first_before_glue(' ', $num);
            }
            $fileObj->flock(LOCK_UN);
            return array(
              'as_number'       => $num,
              'as_prefix'       => $prefix,
              'as_prefix_bin'   => $prefixBin,
              'as_country_code' => $code,
              'as_isp'          => $isp,
              'as_nic'          => $nic,
              'as_alloc'        => $alloc,
              'as_source'       => 'ip2asn',
            );
          }
        }
      }
      $fileObj->flock(LOCK_UN);
    }

    $arr = $this->_get_cymru_asn($ip, $ver);

    if (empty($arr)) {
      return array(
        'as_number'       => '',
        'as_prefix'       => '',
        'as_prefix_bin'   => '',
        'as_country_code' => '',
        'as_isp'          => '',
        'as_nic'          => '',
        'as_alloc'        => '',
        'as_source'       => 'ip2asn',
      );
    }

    $fileObj = new SplFileObject($filename, 'w');

    if (empty($arr['as_prefix_bin']) || $arr['as_prefix_bin'] == 'NA') {
      $arr['as_prefix_bin'] = $bin;
    }

    $str =
      time().'|'.
      $arr['as_prefix_bin'].'|'.
      $arr['as_prefix'].'|'.
      $arr['as_country_code'].'|'.
      $arr['as_number'].'|'.
      $arr['as_isp'].'|'.
      $arr['as_nic'].'|'.
      $arr['as_alloc'];

    $fileObj->fseek($fileObj->getSize());
    $fileObj->fwrite("$str\n");
    $fileObj->flock(LOCK_UN);

    return $arr;
  }


  /**
   * Returns AS number and associated data.
   * @param  string
   * @param  integer
   * @return array
   * @throws \Exception
   */
  private function _get_cymru_asn($ip, $ver = 0)
  {
    $arr = array();

    if (empty($ver)) {
      $ver = $this->_get_ipv($ip);
    }
    else {
      $ver = (integer) $ver;
    }

    if ($ver === 4) {
      exec('dig +short '. $this->_get_rev_addr_four($ip) .'.origin.asn.cymru.com TXT', $arr);
    }
    elseif ($ver === 6) {
      exec('dig +short '. $this->_get_rev_addr_six($ip) .'.origin6.asn.cymru.com TXT', $arr);
    }
    else {
      throw new Exception("Illegal value argument ver");
    }

    if (empty($arr)) {
      return array();
    }

    list($num, $prefix, $code, $nic, $alloc) = explode('|', trim($arr[0], " \"\t\n\r\0\x0B"));
    $num    = trim($num);
    $prefix = trim($prefix);
    $nic    = trim($nic);
    $alloc  = trim($alloc);

    if ($prefix == 'NA') {
      $prefixBin = 'NA';
    }
    else {
      $prefixBin = $this->_cidr2binprefix($prefix, $ver);
    }

    if (strpos($num, ' ') !== false) {
      $num = $this->_first_before_glue(' ', $num);
    }

    if (empty($alloc)) {
      $alloc = 'NA';
    }

    return array(
      'as_number'       => trim($num),
      'as_prefix'       => $prefix,
      'as_prefix_bin'   => $prefixBin,
      'as_country_code' => trim($code),
      'as_isp'          => $this->Asn2description($num),
      'as_nic'          => strtoupper($nic),
      'as_alloc'        => $alloc,
      'as_source'       => 'ip2asn',
    );
  }


  /**
   * Returns organisation/isp of given AS number.
   * @param  integer
   * @return string
   */
  public function Asn2description($num)
  {
    $num = (integer) $num;
    return trim(shell_exec("grep -P '^AS". $num ."\ ' ". $this->_asn2name_filename ." | sed 's/^AS[0-9]*[ \t]*//'"));
  }


  /**
   * Returns array of prefixes for given AS number.
   * @param  integer
   * @param  integer
   * @return array
   */
  public function Asn2prefix($num, $ver)
  {
    $num = (integer) $num;
    $ver = (integer) $ver;

    if ($ver !== 4 && $ver !== 6) {
      throw new Exception("Illegal value argument ver");
    }

    $filename = $this->_get_file_prefix() ."prefixes_v${ver}_${num}.json";

    if (file_exists($filename) && filemtime($filename) + $this->_get_caching_time() > time()) {
      return json_decode($this->_get_file_contents($filename), true);
    }

    if ($ver === 4) {
      exec("whois -h whois.radb.net '!gas$num'", $arr);
    }
    else {
      exec("whois -h whois.radb.net '!6as$num'", $arr);
    }

    if (empty($arr) || count($arr) == 1) {
      return array();
    }

    array_shift($arr); # Remove the first element
    array_pop($arr);   # Remove the last element

    $new = array();

    foreach ($arr as $key => $val) {
      if (strpos($val, ' ') !== false) {
        $tmparr = explode(' ', $val);
        foreach ($tmparr as $nk => $cidr) {
          if ($ver == 4 && $this->_valid_cidr_four($cidr)) {
            $new[] = $cidr;
          }
          elseif ($ver == 6 && $this->_valid_cidr_six($cidr)) {
            $new[] = $cidr;
          }
        }
      }
    }

    unset($arr);

    $new = array_unique($new);

    if ($ver === 4) {
      natsort($new);
    }
    else {
      $new = $this->_sort_cidr_array($new);
    }

    $new = array_values($new);

    $this->_put_file_contents($filename, json_encode($new, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    return $new;
  }


  /**
   * Reverses order of chars of IPv4.
   * @param  string
   * @return string
   */
  private function _get_rev_addr_four($addr)
  {
    $tok = explode('.', $addr);
    $tok = array_reverse($tok);
    return implode('.', $tok);
  }


  /**
   * Reverses order of chars of IPv6.
   * @param  string
   * @return string
   */
  private function _get_rev_addr_six($addr)
  {
    $addr = $this->_expand_addr_six($addr);
    $tok  = str_replace(':', '', $addr);
    $tok = str_split($tok);
    $tok = array_reverse($tok);
    return implode('.', $tok);
  }


  /**
   * Sorts array of cidrs.
   * @param  array
   * @return array
   */
  private function _sort_cidr_array($arr)
  {
    $new = array();
    foreach ($arr as $key => $cidr) {
      list($ip, $bits) = explode("/", $cidr);
      $new[inet_pton($ip)] = $bits;
    }
    unset($arr);
    ksort($new);
    $out = array();
    foreach($new as $binstr => $bits) {
      $ip = inet_ntop($binstr);
      $out[] = "$ip/$bits";
    }
    return $out;
  }


  /**
   * Validates v4 cidr.
   * @param  string
   * @return boolean
   */
  private function _valid_cidr_four($cidr)
  {
    if (strpos($cidr, '/') !== false) {
      list($addr, $bits) = explode('/', $cidr);
      if ($bits <= 32 && $bits >= 12) {
        if (preg_match('/^(([1-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/', $addr)) {
          return true;
        }
      }
    }
    return false;
  }


  /**
   * Validates v6 cidr.
   * @param  string
   * @return boolean
   */
  private function _valid_cidr_six($cidr)
  {
    if (strpos($cidr, '/') !== false) {
      list($addr, $bits) = explode('/', $cidr);
      if ($bits <= 128 && $bits >= 10) {
        if (preg_match('/^2[0-9a-f]{3}:[0-9a-f:]{3,34}$/i', $addr)) {
          return true;
        }
      }
    }
    return false;
  }


  /**
   * Returns array of prefixes for given array of AS numbers.
   * @param  array
   * @param  integer
   * @return array
   */
  public function ArrayAsn2prefix($arr, $ver)
  {
    $new = array();
    foreach ($arr as $num) {
      if ($arr = $this->Asn2prefix($num, $ver)) {
        $new = array_merge($new, $arr);
      }
    }
    if (!empty($new)) {
      $new = array_unique($new);
      $new = array_values($new);
      natsort($new);
      return $new;
    }
    return array();
  }


  /**
   * Returns version of IP.
   * @param  string
   * @return integer
   */
  public function _get_ipv($ip)
  {
    return (strpos($ip, ':') === false) ? 4 : 6;
  }


  /**
   * Converts IP address into binary string.
   * @param  string
   * @return string
   */
  private function _ip2binstr($ip)
  {
    $ip = trim($ip);
    if (strpos($ip, ':') === false) {
      $ip = inet_pton($ip);
      $value = unpack('H*', $ip);
      $ip = base_convert($value[1], 16, 2);
      return str_pad($ip, 32, '0', STR_PAD_LEFT);
    }
    $ip_n = inet_pton($ip);
    $bits = 15;
    $new = 0;
    while ($bits >= 0) {
      $bin = sprintf("%08b", ord($ip_n[$bits]));
      if ($new) {
        $new = $bin . $new;
      }
      else {
        $new = $bin;
      }
      $bits--;
    }
    return $new;
  }


  /**
   * Converts cidr prefix into binary string.
   * @param  string
   * @return string
   */
  private function _cidr2binprefix($cidr)
  {
    list($ip, $mask) = explode('/', $cidr);
    return substr($this->_ip2binstr($ip), 0, $mask);
  }


  /**
   * Matches two binary strings, to be used for determining whether
   * an address matches a cidr prefix.
   * @param  string
   * @param  string
   * @return boolean
   */
  private function _match_bin_strings($needle, $haystack)
  {
    return substr($needle, 0, strlen($haystack)) === $haystack;
  }


  /**
   * Expands compressed IPv6.
   * @param  string
   * @return string
   */
  private function _expand_addr_six($addr)
  {
    if (strpos($addr, '::') !== false) {
      $part = explode('::', $addr);
      $part[0] = explode(':', $part[0]);
      $part[1] = explode(':', $part[1]);
      $missing = array();
      for ($i = 0; $i < (8 - (count($part[0]) + count($part[1]))); $i++) {
        array_push($missing, '0000');
      }
      $missing = array_merge($part[0], $missing);
      $part = array_merge($missing, $part[1]);
    }
    else {
      $part = explode(":", $addr);
    }
    foreach ($part as &$p) {
      $p = str_pad($p, 4, '0', STR_PAD_LEFT);
    }
    unset($p);
    $result = implode(':', $part);
    if (strlen($result) === 39) {
      return $result;
    }
    return false;
  }


  /**
   * Returns the first element before a glue.
   * Eg, "first-second-last" > "first"
   * @param  string
   * @param  string
   * @return string
   */
  private function _first_before_glue($glue, $str)
  {
    return (strpos($str, $glue) === false) ? $str: strstr($str, $glue, true);
  }


  /**
   * Saves string inside a file
   * @param  string  $file
   * @param  string  $str
   * @return mixed
   */
  private function _put_file_contents($file, $str)
  {
    $fileObj = new SplFileObject($file, 'w');
    while (!$fileObj->flock(LOCK_EX)) {
      usleep(1);
    }
    $bytes = $fileObj->fwrite($str);
    $fileObj->flock(LOCK_UN);
    return $bytes;
  }


  /**
   * Retrieves contents of a file
   * @param  string  $file
   * @return string
   */
  private function _get_file_contents($file)
  {
    $fileObj = new SplFileObject($file, 'r');
    while (!$fileObj->flock(LOCK_EX)) {
      usleep(1);
    }
    $size = $fileObj->getSize();
    if ($size == 0) {
      $fileObj->flock(LOCK_UN);
      return '';
    }
    $str = $fileObj->fread($size);
    $fileObj->flock(LOCK_UN);
    return $str;
  }


  /**
   * Checks whether cache directory is defined & exists.
   * @param  string
   * @throws \Exception
   */
  private function _check_cachedir($dir)
  {
    if (empty($dir)) {
      throw new Exception("Illegal value argument dir");
    }
    if (!file_exists($dir) || !is_dir($dir)) {
      throw new Exception("Directory $dir does not exist or not a directory");
    }
  }


  /**
   * Returns file name prefix.
   * @param  string
   * @return string
   * @throws \Exception
   */
  private function _get_file_prefix()
  {
    if (!empty($this->_file_prefix)) {
      return $this->_file_prefix;
    }
    throw new Exception("Property _file_prefix cannot be empty");
  }


  /**
   * Returns caching time in seconds.
   * @return integer
   */
  private function _get_caching_time()
  {
    if (!empty($this->_caching_time)) {
      return $this->_caching_time;
    }
    if (!empty($this->cache_time) && is_integer($this->cache_time)) {
      $this->_caching_time = $this->cache_time;
      return $this->_caching_time;
    }
    return self::CACHING_TIME_DEFAULT;
  }

}
