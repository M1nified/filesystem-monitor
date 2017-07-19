<?php

require_once(__DIR__.DIRECTORY_SEPARATOR.'functions.php');

$will_email = false;

$opts = getopt("", [
    "cmp",
    "cmp1:",
    "cmp2:",
    "dbg",
    "dir:",
    "info",
    "mail-to:",
    "sum",
    ]);

if (is_array($opts) && array_key_exists('dbg', $opts)) {
    defined('DEBUG') || define('DEBUG', true);
}

if (sizeof($opts) == 0) {
    echo "Usage:\n";
    echo "php cli.php --dir <dir>[ --cmp[ --cmp1 <file1> --cmp2 <file2>]][ --dbg][ --info][ --mail-to <emails>][ --sum]\n";
    echo "Options:\n";
    $o = [
        "--cmp"     => "Compare sums",
        "--cmp1"    => "Compare file 1; path or file name or its part",
        "--cmp2"    => "Compare file 2",
        "--dbg"     => "Prints out a debug log",
        "--dir"     => "Directory to work on. --dir \"path\"",
        "--info"    => "Displays info about directory",
        "--mail-to" => "Report recipients. --mail-to \"john@domain.com,will@domain.com\"",
        "--sum"     => "Create sum",
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
$hash = str_shuffle(preg_replace('/[\s-]/i', '', 'The Human Spirit Must Prevail Over Technology - Albert Einstein'));
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

if (array_key_exists('info', $opts)) {
    logg_h1("directory info", LoggDestination::LOGG_DST_ALL);
    $mems = scandir($mem_dir, SCANDIR_SORT_DESCENDING);
    $sum_files = array_filter($mems, function ($file) {
        return preg_match('/^sum-.*/i', $file);
    });
    $log_files = array_filter($mems, function ($file) {
        return preg_match('/^logg-.*/i', $file);
    });
    logg("Mem dir:     $mem_dir");
    logg("All files:   ".sizeof($mems));
    logg("SUM files:   ".sizeof($sum_files));
    logg("LOG files:   ".sizeof($log_files));
    logg("OTHER files: ".(sizeof($mems)-sizeof($sum_files)-sizeof($log_files)-2));
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
        if (array_key_exists('cmp1', $opts) && array_key_exists('cmp2', $opts)) {
            $prev_file = realpath($opts['cmp1']);
            if ($prev_file === false) {
                $prev_files = array_values(array_filter($sum_files, function ($file) use ($opts) {
                    return strpos($file, $opts['cmp1']) !== false;
                }));
                if (sizeof($prev_files)===1) {
                    $prev_file = $prev_files[0];
                } else {
                    exit("Failed to match cmp1 with any file.");
                }
                $prev_file = $mem_dir.DIRECTORY_SEPARATOR.$prev_file;
            }
            $last_file = realpath($opts['cmp2']);
            if ($last_file === false) {
                $last_files = array_values(array_filter($sum_files, function ($file) use ($opts) {
                    return strpos($file, $opts['cmp2']) !== false;
                }));
                if (sizeof($last_files)===1) {
                    $last_file = $last_files[0];
                } else {
                    exit("Failed to match cmp2 with any file.");
                }
                $last_file = $mem_dir.DIRECTORY_SEPARATOR.$last_file;
            }
        } else {
            $last_file = $mem_dir.DIRECTORY_SEPARATOR.$sum_files[0];
            $prev_file = $mem_dir.DIRECTORY_SEPARATOR.$sum_files[1];
        }

        logg_h1("File 1:", LoggDestination::LOGG_DST_ALL);
        logg(basename($prev_file), LoggDestination::LOGG_DST_ALL);
        logg_h1("File 2:", LoggDestination::LOGG_DST_ALL);
        logg(basename($last_file), LoggDestination::LOGG_DST_ALL);

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
