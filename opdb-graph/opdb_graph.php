<?php
namespace opdb_graph;

/*
 * Tarpeeksi Hyvae Soft 2019 /
 * opdb-graph (command-line version)
 *
 * Creates graphs of data from an opdb database file.
 * 
 * Command-line options:
 *  -w <int> = graph width.
 *  -h <int> = graph height.
 *  -t <string> = graph title.
 *  -d <int> = number of days of data to graph, counting back from the most recent entry in the database.
 *  -i <string> = name and path of the opdb database file.
 *  -o <string> = name and path of the image file to generate the graph into (will use PNG format regardless of the file name).
 */

include "fetcher.php";
include "grapher.php";

set_error_handler(function($errno, $errstr, $errfile, $errline)
{
    throw new \Exception($errstr);
    return true;
});

// Parse the command line.
$commandLine = getopt("w:h:i:d:o:t:");
if (!isset($commandLine["t"])) $commandLine["t"] = $commandLine["i"];

// Create the graph.
$graph = create_graph($commandLine["w"] , $commandLine["h"], $commandLine["t"], $commandLine["i"], $commandLine["d"]);

// Save the graph to disk.
imagepng($graph, $commandLine["o"]);
?>
