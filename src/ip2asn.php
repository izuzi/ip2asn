<?php
/**
 * IP 2 ASN
 * IP address intelligence.
 *
 * @version    0.6 (2017-11-17 19:36:19 GMT)
 * @author     Peter Kahl <peter.kahl@colossalmind.com>
 * @copyright  2015-2017 Peter Kahl
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

class ip2asn {

  /**
   * Cache directory
   * @var string
   */
  private $cacheDir;

  #===================================================================

  public function __construct($str) {
    $this->cacheDir = $str;
  }

  #===================================================================

  public function getAsn($ip, $bin = '', $ver = 0) {

    $ip = trim($ip);

    if (empty($ver)) {
      $ver = $this->GetIPversion($ip);
    }

    if (empty($bin)) {
      $bin = $this->AnyIPtoBinary($ip, $ver);
    }

    if ($ver == 4) {
      $readFile = $this->cacheDir .'/ASN4-CACHE.db';
    }
    elseif ($ver == 6) {
      $readFile = $this->cacheDir .'/ASN6-CACHE.db';
    }
    else {
      throw new Exception('Illegal value argument ver');
    }

    if (file_exists($readFile)) {
      $file_obj_gtn = new SplFileObject($readFile);
      while (!$file_obj_gtn->eof()) {
        $line = $file_obj_gtn->fgets();
        $line = trim($line);
        if (strpos($line, '|') !== false) {
          list($epoch, $prefixBin, $prefix, $code, $num, $isp, $nic, $alloc) = explode('|', $line);
          if ($this->MatchBinaryStrings($bin, $prefixBin)) {
            return array(
              'as_number'       => $num,
              'as_prefix'       => $prefix,
              'as_prefix_bin'   => $prefixBin,
              'as_country_code' => $code,
              'as_isp'          => $isp,
              'as_nic'          => $nic,
              'as_alloc'        => $alloc,
            );
          }
        }
      }
    }

    $arr = $this->getCymruAsn($ip, $ver);

    if (empty($arr)) {
      return array(
        'as_number'       => '',
        'as_prefix'       => '',
        'as_prefix_bin'   => '',
        'as_country_code' => '',
        'as_isp'          => '',
        'as_nic'          => '',
        'as_alloc'        => '',
      );
    }

    if (empty($arr['as_prefix_bin']) || $arr['as_prefix_bin'] == 'NA') {
      $arr['as_prefix_bin'] = $bin;
    }

    $str = time().'|'.$arr['as_prefix_bin'].'|'.$arr['as_prefix'].'|'.$arr['as_country_code'].'|'.$arr['as_number'].'|'.$arr['as_isp'].'|'.$arr['as_nic'].'|'.$arr['as_alloc'];

    file_put_contents($readFile, $str . PHP_EOL, FILE_APPEND | LOCK_EX);

    return $arr;
  }

  #===================================================================
  # Using the faster DNS method
/*
$ dig +short 31.108.55.213.origin.asn.cymru.com TXT
"24757 | 213.55.64.0/18 | ET | afrinic | 2000-10-12"
"24757 | 213.55.108.0/24 | ET | afrinic | 2000-10-12"
"24757 | 213.55.108.0/23 | ET | afrinic | 2000-10-12"
"24757 | 213.55.108.0/22 | ET | afrinic | 2000-10-12"
*/
  public function getCymruAsn($ip, $ver = 0) {

    $arr = array();

    if (empty($ver)) {
      $ver = $this->GetIPversion($ip);
    }

    if ($ver == 4) {
      $tok = explode('.', $ip);
      $tok = array_reverse($tok);
      $rev = implode('.', $tok);
      exec('dig +short '. $rev .'.origin.asn.cymru.com TXT', $arr);
    }
    elseif ($ver == 6) {
      $ip  = $this->IPv6expand($ip);
      $ip  = str_replace(':', '', $ip);
      $tok = str_split($ip);
      $tok = array_reverse($tok);
      $rev = implode('.', $tok);
      exec('dig +short '. $rev .'.origin6.asn.cymru.com TXT', $arr);
    }
    else {
      throw new Exception('Illegal value argument ver');
    }

    if (empty($arr)) {
      return false;
    }

    list($num, $prefix, $code, $nic, $alloc) = explode('|', trim($arr[0], " \"\t\n\r\0\x0B"));

    $prefix = trim($prefix);

    if ($prefix == 'NA') {
      $prefixBin = 'NA';
    }
    else {
      $prefixBin = $this->AnyCIDRtoBinaryPrefix($prefix, $ver);
    }

    $alloc = trim($alloc);

    if (empty($alloc)) {
      $alloc = 'NA';
    }

    return array(
      'as_number'       => trim($num),
      'as_prefix'       => $prefix,
      'as_prefix_bin'   => $prefixBin,
      'as_country_code' => trim($code),
      'as_isp'          => $this->Asn2description(trim($num)),
      'as_nic'          => preg_replace('/NCC$/', '', strtoupper(trim($nic))),
      'as_alloc'        => $alloc,
    );
  }

  #===================================================================

  public function Asn2description($num) {
    $num = intval($num);
    $str = shell_exec('cat '. $this->cacheDir .'/asnames.txt | grep -P "^AS'. $num .'\ "');
    if (empty($str)) {
      return '';
    }
    return preg_replace('/^AS'. $num .'\ +/', '', trim($str));
  }

  #===================================================================

  public function Asn2prefix($num, $ver = 4) {

    $num = intval($num);
    $ver = intval($ver);

    if ($ver === 4) {
      exec('whois -h whois.radb.net "!gas'. $num .'"', $arr);
    }
    elseif ($ver === 6) {
      exec('whois -h whois.radb.net "!6as'. $num .'"', $arr);
    }
    else {
      throw new Exception('Illegal value argument ver');
    }

    if (empty($arr) || count($arr) == 1) {
      return false;
    }

    array_shift($arr); # Remove the first element
    array_pop($arr);   # Remove the last element

    $str = implode('', $arr);

    $str = strtolower($str);

    return explode(' ', $str);
  }

  #===================================================================

  public function ArrayAsn2prefix($arr, $ver) {
    $new = array();
    foreach ($arr as $num) {
      for ($x = 0; $x < 10; $x++) {
        $temp = $this->Asn2prefix($num, $ver);
      }
      if (!empty($temp)) {
        $new = array_merge($new, $temp);
      }
    }
    if ($ver == 6 && !empty($new)) {
      $new = $this->IPv6arrayExpand($new);
    }
    $new = array_values($new);
    natsort($new);
    return $new;
  }

  #===================================================================

  public function GetIPversion($ip) {

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      return 4;
    }
    elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      return 6;
    }

    throw new Exception('Invalid IP address notation');
  }

  #===================================================================

  public function AnyIPtoBinary($ip, $ver = 0) {

    if (empty($ver)) {
      $ver = $this->GetIPversion($ip);
    }

    if ($ver == 4) {
      return $this->IPv4toBinary($ip);
    }
    elseif ($ver == 6) {
      return $this->IPv6toBinary($ip);
    }

    throw new Exception('Illegal value argument ver');
  }

  #===================================================================

  public function IPv4toBinary($ip) {
    $ip = trim($ip);
    $ip = $this->bstr2bin(inet_pton($ip));
    return str_pad($ip, 32, '0', STR_PAD_LEFT);
  }

  #===================================================================

  private function bstr2bin($input) {
    $value = unpack('H*', $input);
    return base_convert($value[1], 16, 2);
  }

  #===================================================================

  public function IPv6toBinary($ipv6) {
    $ip_n = inet_pton($ipv6);
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

  #===================================================================

  public function AnyCIDRtoBinaryPrefix($cidr, $ver = 0) {

    list($ip, $mask) = explode('/', $cidr);

    if (empty($ver)) {
      $ver = $this->GetIPversion($ip);
    }

    if ($ver == 4) {
      return substr($this->IPv4toBinary($ip), 0, $mask);
    }
    elseif ($ver == 6) {
      return substr($this->IPv6toBinary($ip), 0, $mask);
    }

    throw new Exception('Illegal value argument ver');
  }

  #===================================================================

  private function MatchBinaryStrings($needle, $haystack) {
    return substr($needle, 0, strlen($haystack)) == $haystack;
  }

  #===================================================================

  private function IPv6expand($addr) {
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
    if (strlen($result) == 39) {
      return $result;
    }
    return false;
  }

  #===================================================================

  private function IPv6arrayExpand($arr) {
    foreach ($arr as $key => $val) {
      list($ip, $mask) = explode('/', $val);
      $arr[$key] = $this->IPv6expand($ip) .'/'. $mask;
    }
    return $arr;
  }

  #===================================================================
}
