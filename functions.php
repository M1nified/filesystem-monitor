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

function sum_dir($directory)
{
    $sums = [];
    $itres = file_iterator($directory, function ($file, $info) use (&$sums) {
        // echo "$file\t$info\n";
        $sums[$file] = md5_file($file);
    });
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

function logg($message = "")
{
    $timestamp = date('Y-m-d H:i:s');
    $message = rtrim($message);
    $log = "[{$timestamp}]\t$message";
    print("$log\n");
}
function logg_h1($message = "")
{
    logg(str_pad(" $message ", 60, "=", STR_PAD_BOTH));
}