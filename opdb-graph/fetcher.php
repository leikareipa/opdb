<?php
namespace opdb_graph;

/*
 * Tarpeeksi Hyvae Soft 2019 /
 * opdb-graph
 * 
 * Fetches data from an opdb database file.
 *
 */

// Returns data from the given opdb database file over the given number of days,
// counting back from the latest entry in the database. Returns these data in an
// array containing a ["value"] and a ["timestamp"] (epoch) for each datum.
function fetch_data(string $filename, int $numDays)
{
    if (!file_exists($filename)) throw new \Exception("Can't find the given database file.");
    if ($numDays < 1) throw new \Exception("The number of days to fetch data over must be greater than zero.");

    $file = fopen($filename, "rb");
    if (!$file) throw new \Exception("Can't open the given database file.");

    // Read in the database file, starting from the end and working toward the beginning,
    // one 16-byte entry at a time, until we've covered the requested number of days.
    $idx = 0;
    $data = [];
    fseek($file, -16, SEEK_END);
    while (1)
    {
        $data[$idx]["timestamp"] = unpack("q", fread($file, 8))[1];
        $data[$idx]["value"] = unpack("d", fread($file, 8))[1];
        if ((ftell($file) % 16) !== 0) throw new \Exception("Misaligned data read detected.");
        if ($idx > 0 && $data[$idx]["timestamp"] > $data[$idx-1]["timestamp"]) throw new \Exception("Invalid database: non-monotonic timestamps.");

        // Stop once we've finished collecting data over the requested number of days.
        if ($data[$idx]["timestamp"] < strtotime("-" . $numDays . " days", $data[0]["timestamp"]))
        {
            array_pop($data);
            break;
        }

        // Stop if we've reached the end (i.e. the beginning) of the file. Assumes each entry
        // to be 16 bytes, so if we can't seek back by the 16 we just read and another 16 to
        // get to the start of the next entry, we're done.
        if (ftell($file) < 32) break;

        fseek($file, -32, SEEK_CUR);
        $idx++;
    }

    return $data;
}
?>
