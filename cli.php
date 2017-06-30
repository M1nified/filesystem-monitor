<?php

require_once(__DIR__.DIRECTORY_SEPARATOR.'functions.php');

$opts = getopt("", [
    "dir::",
    "sum",
    "cmp"
    ]);

// print_r($opts);

$dir = realpath($opts['dir']);
if ($dir === false) {
    die("Invalid directory.");
}

$mem_dir = __DIR__.DIRECTORY_SEPARATOR."mem-".md5($dir);
if (!is_dir($mem_dir)) {
    mkdir($mem_dir);
}
$date = date('Y-m-d-H-i-s');

if (array_key_exists('sum', $opts)) {
    $sums = sum_dir($dir);
    $str = serialize($sums);
    $log_path = $mem_dir.DIRECTORY_SEPARATOR."sum-".$date;
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
        logg("Nothing to compare.\n");
    } else {
        $last_file = $mem_dir.DIRECTORY_SEPARATOR.$sum_files[0];
        $prev_file = $mem_dir.DIRECTORY_SEPARATOR.$sum_files[1];

        $last = is_array($sums) ? $sums : read_sum($last_file);
        $prev = read_sum($prev_file);

        // echo "LAST\n";
        // print_r($last);
        // echo "PREV\n";
        // print_r($prev);

        $files_removed = array_diff_key($prev, $last);
        $files_added = array_diff_key($last, $prev);
        $file_modified = array_diff($last, $prev);

        logg_h1("files removed (".sizeof($files_removed).")");
        foreach ($files_removed as $file => $md5sum) {
            logg($file);
        }

        logg_h1("files added (".sizeof($files_added).")");
        foreach ($files_added as $file => $md5sum) {
            logg($file);
        }

        logg_h1("files modified (".sizeof($file_modified).")");
        foreach ($file_modified as $file => $md5sum) {
            logg($file);
        }
    }
}
