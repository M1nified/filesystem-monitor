<?php

require_once(__DIR__.DIRECTORY_SEPARATOR.'functions.php');

$will_email = false;

$opts = getopt("", [
    "dir::",
    "sum",
    "cmp",
    "dbg",
    "mail-to::"
    ]);

if (is_array($opts) && array_key_exists('dbg', $opts)) {
    defined('DEBUG') || define('DEBUG', true);
}

if (sizeof($opts) == 0) {
    echo "Options:\n";
    $o = [
        "--dir"     => "Directory to work on. --dir=\"path\"",
        "--sum"     => "Create sum",
        "--cmp"     => "Compare sums",
        "--dbg"     => "Prints out a debug log",
        "--mail-to" => "Report recipients. --mail-to=\"john@domain.com,will@domain.com\"",
    ];
    foreach ($o as $option => $description) {
        echo "\t".str_pad($option, 20, " ")."\t".$description."\n";
    }
}

is_debug() && print_r($opts);

$dir = realpath($opts['dir']);
if ($dir === false) {
    die("Invalid directory.");
}

$name = md5($dir);
$mem_dir = __DIR__.DIRECTORY_SEPARATOR."mem-".$name;

$date = date('Y-m-d-H-i-s');
$hash = str_shuffle('bestcompanyever');
$fname = "$date-$hash";
global $LOGG_DST_FILE_PATH;
$LOGG_DST_FILE_PATH = $mem_dir.DIRECTORY_SEPARATOR."logg-$fname";


if (!is_dir($mem_dir)) {
    mkdir($mem_dir);
}
$config_path = $mem_dir.DIRECTORY_SEPARATOR."conf.ini";
if (!is_file($config_path)) {
    touch($config_path);
    $config_ini_str = implode("\n", [
        "# This is configuration file for:",
        "# $dir",
        null,
        "# Exclude directories using wildcards:",
        "# exclude[]=\"*/.git/*\"",
        null,
    ]);
    file_put_contents($config_path, $config_ini_str);
}
$config = parse_ini_file($config_path);
is_debug() && print_r($config);

if (sizeof($opts) > 0) {
    logg($dir, LoggDestination::LOGG_DST_ALL);
}

if (is_array($config['exclude'])) {
    logg_h1("excluded wildcards", LoggDestination::LOGG_DST_ALL);
    foreach ($config['exclude'] as $excluded_wc) {
        logg($excluded_wc, LoggDestination::LOGG_DST_ALL);
    }
}

if (array_key_exists('sum', $opts)) {
    $sums = sum_dir($dir, ['exclude'=>$config['exclude']]);
    $str = serialize($sums);
    $log_path = $mem_dir.DIRECTORY_SEPARATOR."sum-$fname";
    file_put_contents($log_path, $str);
}

// print_r($sums);

if (array_key_exists('cmp', $opts)) {
    $mems = scandir($mem_dir, SCANDIR_SORT_DESCENDING);
    // print_r($mems);
    $sum_files = array_filter($mems, function ($file) {
        return preg_match('/^sum-.*/i', $file);
    });
    // print_r($sum_files);
    if (sizeof($sum_files) < 2) {
        logg("Nothing to compare.\n", LoggDestination::LOGG_DST_ALL);
    } else {
        $last_file = $mem_dir.DIRECTORY_SEPARATOR.$sum_files[0];
        $prev_file = $mem_dir.DIRECTORY_SEPARATOR.$sum_files[1];

        $last = is_array($sums) ? $sums : read_sum($last_file);
        $prev = read_sum($prev_file);

        // echo "LAST\n";print_r($last);echo "PREV\n";print_r($prev);

        $files_removed = array_diff_key($prev, $last);
        $files_added = array_diff_key($last, $prev);
        $files_modified = array_diff($last, $prev);

        if (max(sizeof($files_removed), sizeof($files_added), sizeof($files_modified)) > 0) {
            $will_email = true;
        }

        logg_h1("files removed (".sizeof($files_removed).")", LoggDestination::LOGG_DST_ALL);
        foreach ($files_removed as $file => $md5sum) {
            logg($file, LoggDestination::LOGG_DST_FILE | LoggDestination::LOGG_DST_EMAIL);
        }

        logg_h1("files added (".sizeof($files_added).")", LoggDestination::LOGG_DST_ALL);
        foreach ($files_added as $file => $md5sum) {
            logg($file, LoggDestination::LOGG_DST_FILE | LoggDestination::LOGG_DST_EMAIL);
        }

        logg_h1("files modified (".sizeof($files_modified).")", LoggDestination::LOGG_DST_ALL);
        foreach ($files_modified as $file => $md5sum) {
            logg($file, LoggDestination::LOGG_DST_FILE | LoggDestination::LOGG_DST_EMAIL);
        }
    }
}

if (sizeof($opts)>0) {
    logg_h1("end", LoggDestination::LOGG_DST_ALL);
}

if ($will_email && sizeof($opts['mail-to'])>0) {
    $to = explode(",", $opts['mail-to']);
    $to = array_map('trim', $to);
    send_email($to, 'File Monitor Report');
}
