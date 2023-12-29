<?php
namespace Policyreporter\LazyBase\Lazy\KeyedGenerator;

// A simple function to dump a CSV into a \Generator
if (!defined('CSV_ASSOCIATIVE')) {
    define('CSV_ASSOCIATIVE', 1);
}
if (!defined('CSV_NUMERIC')) {
    define('CSV_NUMERIC', 2);
}
if (!defined('CSV_SKIP_FIRST')) {
    define('CSV_SKIP_FIRST', 3);
}

function readCsv($file, $mode, $newLine = false, $normalizeEol = false)
{
    if (!in_array($mode, [CSV_ASSOCIATIVE, CSV_NUMERIC, CSV_SKIP_FIRST])) {
        throw new \Exception\System('Invalid CSV reading mode');
    }
    if (is_string($file)) {
        if (!file_exists($file)) {
            throw new \Exception("File {$file} is not a file");
        }
        $file = new \SplFileInfo($file);
    }
    if ($file instanceof \SplFileInfo) {
        if (!$file->isFile()) {
            throw new \Exception("File {$file->getRealPath()} is not a regular file");
        }
        if (!$file->isReadable()) {
            throw new \Exception("File {$file->getRealPath()} is not readable");
        }
        $file = $file->openFile('r');
    }
    if (!$file instanceof \SplFileObject) {
        throw new \Exception(
            "Object passed to " . __FUNCTION__ . " must be either a filepath or a readable instance of \\SplFileInfo"
        );
    }
    $flags = $newLine ? \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_AHEAD | $file->getFlags() :
        \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_AHEAD | $file->getFlags() | \SplFileObject::DROP_NEW_LINE;

    $file->setFlags($flags);
    $i = 1;
    $firstLine = null;
    if ($mode === CSV_SKIP_FIRST) {
        $firstLine = $file->fgetcsv();
    } elseif ($mode === CSV_ASSOCIATIVE) {
        $firstLine = $header = $file->fgetcsv();
        if ($header === null) {
            throw new \Exception\User("Unable to read empty CSV file");
        }
        if (count(array_filter($header)) != count($header)) {
            throw new \Exception\User("Saw blank headers");
        }
        if (count(array_unique($header)) != count($header)) {
            throw new \Exception\User("Saw duplicated headers");
        }
    } else {
        $i = 0;
    }
    while (!$file->eof()) {
        $line = $file->fgetcsv();
        $i++;

        if (is_null($line) || !count($line)) {
            continue;
        }
        if ($firstLine === null) {
            $firstLine = $line;
        }
        if (count($firstLine) !== count($line)) {
            throw new \Exception\User(
                "\$row count mismatch on line {$i}: found " . count($line) . ' entries, expecting ' . count($firstLine)
            );
        }
        if ($normalizeEol) {
            $line = array_map('normalizeEol', $line);
        }
        if ($mode === CSV_ASSOCIATIVE) {
            yield array_combine($header, $line);
        } else {
            yield $line;
        }
    }
}
