<?php

ini_set('xdebug.var_display_max_depth', '-1');
ini_set('xdebug.var_display_max_children', '-1');
ini_set('xdebug.var_display_max_data', '-1');

$data = file_get_contents('sample.txt');
header('Content-Type: text/plain');

function step1($data)
{
  $output = [];
  $pattern = '/( )*(MASTERCARD|VISA|DISCOVER|DEBIT)(.*)SALES DISC(.*)Service charges( )*-\$(\d*\.\d*)/';
  preg_match_all($pattern, $data, $result1);
  if (isset($result1[0])
      && is_array($result1[0])
      && !empty($result1[0])) {
    echo 'STEP 1:' . PHP_EOL;
    echo '[';
    foreach ($result1[0] as $r) {
      $pattern = '/(.*) SALES/';
      preg_match_all($pattern, $r, $result2);
      $network = ucfirst(strtolower(trim($result2[1][0])));
      $pattern = '/(\.\d*) DISC/';
      preg_match_all($pattern, $r, $result3);
      $discount = (float)$result3[1][0];
      $pattern = '/\$(\d*\.?\d*.*)Service/';
      preg_match_all($pattern, $r, $result4);
      $amount = trim($result4[1][0]);
      $output[] = '"' . $network . '" => ["discRate" => ' . $discount . ', “amount” => “' . $amount . '”]';
    }

    $output = implode(', ', $output);
    echo $output;
    echo ']';
  }
}

function step2($data)
{
  echo PHP_EOL;
  echo PHP_EOL;
  echo 'STEP 2:' . PHP_EOL;
  $pattern = $pattern = '/(VI-.*(PP|DB).*%.*-\$\d*\.?\d*)/';
  echo 'VI-* (PP)/(DB): ';
  step2_sub($data, $pattern);
  echo PHP_EOL;

  $pattern = $pattern = '/(MC-.*(DB).*%.*-\$\d*\.?\d*)/';
  echo 'MC-* (DB): ';
  step2_sub($data, $pattern);
}

function step2_sub($data, $pattern)
{
  preg_match_all($pattern, $data, $result1);

  $sum = 0;

  if (isset($result1[0])
      && is_array($result1[0])
      && !empty($result1[0])) {
    foreach ($result1[0] as $r) {
      $pattern = '/-\$(\d*\.?\d*)/';
      preg_match_all($pattern, $r, $result2);
      $sum += (float)$result2[1][0];
    }
  }

  echo $sum;
}

function step3($data)
{
  echo PHP_EOL;
  echo PHP_EOL;
  echo 'STEP 3:' . PHP_EOL;

  $output = [
    [
      'name' => 'SUMMARY BY CARD TYPE',
    ],
    [
      'name' => 'SUMMARY BY BATCH',
    ],
    [
      'name' => 'SUMMARY BY DAY',
    ],
  ];

  $pattern = '/SUMMARY BY CARD TYPE(.*)Total.*\d\s*SUMMARY BY BATCH/s';
  preg_match_all($pattern, $data, $result1);
  if (isset($result1[1])
      && is_array($result1[1])
      && !empty($result1[1])) {
    $d = trim($result1[1][0]);
    $d = explode(PHP_EOL, $d);
    $lines = [];
    $col_count = 0;
    foreach ($d as $dd) {
      $dd = trim($dd);
      $dd = preg_replace('/\s{2,1024}/', '  ', $dd);
      $lines[] = explode('  ', $dd);
      if (count($lines) > $col_count) {
        $col_count = count($lines);
      }
    }
    $d = [];
    foreach ($lines as $line) {
      if (count($line) == $col_count) {
        $d[] = $line;
      }
    }
    foreach ($d as $i => $dd) {
      foreach ($dd as &$ddd) {
        $ddd = '"' . $ddd . '"';
      }
      $dd = '[' . implode(', ', $dd) . ']';

      if ($i == 0) {
        $output[0]['headers'] = $dd;
      } else {
        $output[0]['rows'][] = $dd;
      }
    }
  }

  $rr = '';
  $pattern = '/SUMMARY BY DAY(.*)YOUR CARD PROCESSING STATEMENT.*Merchant Number.*Page 2 of 7/s';
  preg_match_all($pattern, $data, $result1);

  if (isset($result1[1])
      && is_array($result1[1])
      && !empty($result1[1])) {
    $rr .= trim($result1[1][0]);
  }

  $pattern = '/Phone -.*SUMMARY BY DAY.*Processed(.*)SUMMARY BY CARD TYPE/s';
  preg_match_all($pattern, $data, $result1);

  if (isset($result1[1])
      && is_array($result1[1])
      && !empty($result1[1])) {
    $rr .= trim($result1[1][0]);
  }

  $d = explode(PHP_EOL, $rr);
  $lines = [];
  foreach ($d as $dd) {
    $dd = trim($dd);
    $dd = preg_replace('/\s{2,1024}/', '  ', $dd);
    $lines[] = explode('  ', $dd);
  }
  $h = [
    $lines[0][0] . ' ' . $lines[1][0],
    $lines[0][1] . ' ' . $lines[1][1],
    $lines[0][2] . ' ' . $lines[1][2],
    $lines[1][3],
    $lines[1][4],
    $lines[0][3] . ' ' . $lines[1][5],
  ];
  array_shift($lines);
  array_shift($lines);
  array_unshift($lines, $h);
  $col_count = count($lines[0]);
  $d = [];
  foreach ($lines as $line) {
    if (count($line) == $col_count) {
      $d[] = $line;
    }
  }
  foreach ($d as $i => $dd) {
    foreach ($dd as &$ddd) {
      $ddd = '"' . $ddd . '"';
    }
    $dd = '[' . implode(', ', $dd) . ']';

    if ($i == 0) {
      $output[2]['headers'] = $dd;
    } else {
      $output[2]['rows'][] = $dd;
    }
  }

  $rr = '';
  $pattern = '/SUMMARY BY BATCH.*Submitted(.*)YOUR CARD PROCESSING STATEMENT.*Page 3 of 7/s';
  preg_match_all($pattern, $data, $result1);
  if (isset($result1[1])
      && is_array($result1[1])
      && !empty($result1[1])) {
    $rr .= trim($result1[1][0]);
  }

  $pattern = '/SUMMARY BY BATCH.*Amount(.*)CHARGEBACKS\/REVERSALS/s';
  preg_match_all($pattern, $data, $result1);
  if (isset($result1[1])
      && is_array($result1[1])
      && !empty($result1[1])) {
    $rr .= trim($result1[1][0]);
  }

  $d = explode(PHP_EOL, $rr);

  $lines = [];
  foreach ($d as $dd) {
    $dd = trim($dd);
    $dd = preg_replace('/\s{2,1024}/', '  ', $dd);
    $lines[] = explode('  ', $dd);
  }
  $d = [];
  foreach ($lines as $line) {
    if (count($line) > 1) {
      $d[] = $line;
    }
  }
  foreach ($d as $i => $dd) {
    foreach ($dd as &$ddd) {
      $ddd = '"' . $ddd . '"';
    }
    $dd = '[' . implode(', ', $dd) . ']';

    if ($i == 0) {
      $output[1]['headers'] = $dd;
    } else {
      $output[1]['rows'][] = $dd;
    }
  }

  step3_sub($output);
}

function step3_sub($output)
{
  ob_start();
  var_export($output);
  $v = ob_get_contents();
  ob_end_clean();
  $v = str_replace('(', '[', $v);
  $v = str_replace(')', ']', $v);
  $v = str_replace('array', '', $v);
  $v = str_replace('=> ' . PHP_EOL, '=> ', $v);
  $v = preg_replace('/=>\ {2,1024}\[/', '=> [', $v);
  $v = str_replace('\'', '"', $v);
  $v = str_replace('"[', '[', $v);
  $v = str_replace('"]', ']', $v);
  echo $v;
}

step1($data);

step2($data);

step3($data);
