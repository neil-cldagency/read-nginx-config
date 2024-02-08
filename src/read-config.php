#!/opt/homebrew/bin/php
<?php
define('BASE_PATH', __DIR__ . '/..');

require_once BASE_PATH . '/vendor/autoload.php';

use SashaBo\NginxConfParser\Parser;

$opts = parseOpts(getopt('c:o:h', [
    'help'
]));

if (isset($opts['o'])) {
    $out = fopen($opts['o'], 'w');
    fputcsv($out, ['config', 'server', 'aliases', 'root']);
}

foreach ($opts['c'] as $file) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $configName = basename($file, ".{$ext}");
    $config = Parser::parseFile($file);

    write("Config: {$configName}");
    write('');

    foreach ($config as $row) {
        if ($row->name === 'server') {
            $server = processServerRows($row);

            if (isset($server['root'])) {
                write('# Server ' . implode(', ', $server['domains']));
                write(' - Root: ' . $server['root']);
                write('');

                if (isset($out)) {
                    $data = [
                        $configName,
                        $server['domains'][0],
                        implode('\n', array_slice($server['domains'], 1)),
                        $server['root']
                    ];

                    fputcsv($out, $data);
                }
            }
        }
    }
}

if (isset($out)) {
    fclose($out);
}

function getFiles($source) {
    if (is_dir($source)) {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source)) as $filename) {
            // filter out "." and ".."
            if ($filename->isDir()) continue;

            $files[] = $filename;
        }

        return $files;
    }

    return [$source];
}

function processServerRows(SashaBo\NginxConfParser\Row $server) {
    if ($server->name !== 'server') {
        error('Server block is invalid', $server->line);
        return;
    }

    $result = [];

    foreach ($server->rows as $row) {
        if ($row->name == 'server_name') {
            $result['domains'] = $row->values;
        }

        if ($row->name == 'root') {
            $result['root'] = implode(', ', $row->values);
        }

        // This does sort of work but it's not reliable so I don't want it for now
        // if ($row->name == 'location' && in_array('/', $row->values)) {
        //     foreach ($row->rows as $locationRow) {
        //         if ($locationRow->name === 'return') {
        //             // add a Redirect
        //             $result['redirects'][] = implode(' ', $locationRow->values);
        //         }
        //     }
        // }
    }

    return $result;
}

function write($str, $eol = PHP_EOL) {
    echo $str . $eol;
}

function error($str, $line = null) {
    write('Error - ' . $str . ($line ? " (line: {$line})" : ''));
}

function parseOpts($opts) {
    if (isset($opts['h']) || isset($opts['help'])) {
        write('# read-config');
        write(' - An nginx log parser.');
        write('This script will read nginx formatted configuration files and output its best guess as to what the general server blocks are along with any aliases and document root data. It will also optionally output to a .csv file of your choosing (see options).');
        write('');
        write(implode("\n", [
            'Options',
            ' -c <file>     Nginx configuration file or directory to read.',
            ' -o <file>     Output CSV file to generate'
        ]));
        write('');
        exit;
    }

    if (!isset($opts['c']) || empty($opts['c'])) {
        error('Please specify a file or directory of files to parse');
        exit -1;
    }

    $opts['c'] = getFiles($opts['c']);

    foreach ($opts['c'] as &$file) {
        $file = realpath(trim($file));

        if (!file_exists($file)) {
            error("File {$file} not found");
            exit -2;
        }
    }

    return $opts;
}
