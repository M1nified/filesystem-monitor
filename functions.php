<?php

function dir_iterator($directory, $callback)
{
    $path = realpath($directory);
    if ($path === false) {
        return false;
    }
    $rdi = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::KEY_AS_PATHNAME);
    foreach (new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST) as $file => $info) {
        $callback($file, $info);
    }
}

function file_iterator($directory, $callback)
{
    $itres = dir_iterator($directory, function ($file, $info) use ($callback) {
        if (is_file($file)) {
            $callback($file, $info);
        }
    });
    if ($itres === false) {
        return false;
    }
}

function sum_dir($directory, $options = [])
{
    $exclude = is_array($options) && isset($options['exclude']) && !is_array($options['exclude'])
        ? [$options['exclude']]
        : $options['exclude'];
    if ($exclude == null) {
        $exclude = [];
    }
    // print_r($exclude);
    $sums = [];
    $count = 0;
    $itres = file_iterator($directory, function ($file, $info) use (&$sums, $exclude, &$count) {
        // echo "$file\t$info\n";
        $count++;
        if (is_debug() && $count % 100 == 0) {
            echo "Files checked: $count; $file\r";
        }
        $match_count = array_reduce($exclude, function ($match_count, $pattern) use ($file) {
            if (fnmatch($pattern, $file)) {
                $match_count++;
            }
            // print_r([$match_count, $pattern, $file]);
            return $match_count;
        }, 0);
        if ($match_count == 0) {
            $sums[$file] = md5_file($file);
        } else {
            is_debug() && print("excluded, $file\n");
        }
    });
    is_debug() && print("\n");
    logg("Checked $count files.");
    if ($itres === false) {
        return false;
    }
    return $sums;
}

function read_sum($file)
{
    $realpath = realpath($file);
    if ($realpath === false) {
        return false;
    }
    $sums = file_get_contents($file);
    $sums = unserialize($sums);
    return $sums;
}

// Helpers

abstract class LoggDestination
{
    const LOGG_DST_FILE     = 0b0000001;
    const LOGG_DST_ECHO     = 0b0000010;
    const LOGG_DST_EMAIL    = 0b0000100;
    const LOGG_DST_ALL      = 0b0000111;
}

function logg($message = "", $logg_dst = LoggDestination::LOGG_DST_ECHO)
{
    $timestamp = date('Y-m-d H:i:s');
    $message = rtrim($message);
    $log = "[{$timestamp}]\t$message";
    if (($logg_dst & LoggDestination::LOGG_DST_ECHO) == LoggDestination::LOGG_DST_ECHO) {
        print("$log\n");
    }
    if (($logg_dst & LoggDestination::LOGG_DST_EMAIL) == LoggDestination::LOGG_DST_EMAIL) {
        global $LOGG_DST_EMAIL_CONTENT;
        $LOGG_DST_EMAIL_CONTENT .= "$log\n";
    }
    if (($logg_dst & LoggDestination::LOGG_DST_FILE) == LoggDestination::LOGG_DST_FILE) {
        global $LOGG_DST_FILE_PATH;
        $logg_file =
            isset($LOGG_DST_FILE_PATH) && !empty($LOGG_DST_FILE_PATH)
            ? $LOGG_DST_FILE_PATH
            : __DIR__.DIRECTORY_SEPARATOR."logg-".date('Y-m-d-H-i-s');
        error_log("$log\n", 3, $logg_file);
    }
}
function logg_h1($message = "", $logg_dst = LoggDestination::LOGG_DST_ECHO)
{
    logg(str_pad(" $message ", 60, "=", STR_PAD_BOTH), $logg_dst);
}

function is_debug()
{
    return defined('DEBUG') && DEBUG === true;
}

// send it ;)

function send_email($to, $subject)
{
    global $LOGG_DST_EMAIL_CONTENT;
    if (is_array($to)) {
        $to = implode(', ', $to);
    }
    if (mail($to, $subject, $LOGG_DST_EMAIL_CONTENT)) {
        logg("Email sent successfully.", LoggDestination::LOGG_DST_ECHO | LoggDestination::LOGG_DST_FILE);
    } else {
        logg("Failed to send an email.", LoggDestination::LOGG_DST_ECHO | LoggDestination::LOGG_DST_FILE);
    }
}
