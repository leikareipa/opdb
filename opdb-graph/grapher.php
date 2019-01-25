<?php
namespace opdb_graph;

/*
 * Tarpeeksi Hyvae Soft 2019 /
 * opdb-graph
 * 
 * Graphs data from an opdb database file.
 *
 */

function create_graph(int $width, int $height, string $filename, int $numDays)
{
    if (!file_exists($filename)) throw new \Exception("Can't find the given database file.");
    if ($width < 1 || $height < 1) throw new \Exception("Invalid graph dimensions.");

    // Returns the width of the given string in pixels. Note: makes a hardcoded assumption
    // about which font the string uses.
    $string_pixel_width = function(string $string) { return (strlen($string) * imagefontwidth(2)); };

    $graph = imagecreatetruecolor($width, $height);
    imagefill($graph, 0, 0, imagecolorallocate($graph, 190, 190, 190));

    // Get the data to be graphed.
    $data = fetch_data($filename, $numDays);
    if (count($data) <= 0) return $graph;

    $colors = ["black" => imagecolorallocate($graph, 20, 20, 20),
               "gray"  => imagecolorallocate($graph, 165, 165, 165)];
    
    $marginLeft = 13;
    $marginRight = 65;
    $marginTop = 25;
    $marginBottom = 23;

    $maxVal = max(array_column($data, "value"));
    $minVal = min(array_column($data, "value"));

    // Draw the graph.
    {
        // Print out the title.
        imagestring($graph, 2, ($width/2 - $string_pixel_width($filename)/2), 4, $filename, $colors["black"]);

        // Draw horizontal markers at even intervals along the y axis.
        $draw_y_axis_markers = function($yFraction) use(&$draw_y_axis_markers, $marginLeft, $marginRight, $marginBottom,
                                                        $marginTop, $colors, $graph, $width, $height, $minVal, $maxVal)
        {
            $interval = 0.125;
            if ($height <= 190) $interval = 0.25;
            if ($height <= 110) $interval = 0.5;

            $y = (($height - $marginBottom) - (($height - $marginTop - $marginBottom) * $yFraction));
            $value = round($minVal + (($maxVal - $minVal) * $yFraction), 4);
            imagestring($graph, 2, ($width - $marginRight) + 7, ($y - imagefontheight(2)/2), $value, $colors["black"]);
            imagedashedline($graph,$marginLeft, $y, ($width - $marginRight), $y, $colors["gray"]);

            return ($yFraction >= 1)? 1 : $draw_y_axis_markers($yFraction + $interval);
        }; $draw_y_axis_markers(0);

        // Draw vertical markers at even intervals along the x axis.
        $draw_x_axis_markers = function($x) use(&$draw_x_axis_markers, $data, $marginLeft, $marginRight,
                                                $marginBottom, $marginTop, $colors, $graph, $width, $height,
                                                $string_pixel_width)
        {
            // Guarantee that the graph's left edge gets a vertical marker.
            if ($x < 0) $x = 0;

            $interval = 150;

            imagedashedline($graph,
                            ($marginLeft + $x), $marginTop,
                            ($marginLeft + $x), ($height - $marginBottom) + (($x > 0)? 7 : 0),
                            $colors["gray"]);

            // Find the timestamp that corresponds to the given x coordinate on the graph.
            $startTime = $data[count($data)-1]["timestamp"];
            $endTime = $data[0]["timestamp"];
            $timePerX = ($endTime - $startTime) / ($width - $marginRight - $marginLeft);
            $currentTime = ($startTime + ($x * $timePerX));

            $dateString = date("d-M-y/H:i:s", $currentTime);
            if (($x - $string_pixel_width($dateString)) < 0) return; 

            imagestring($graph, 2,
                        ($marginLeft + $x - $string_pixel_width($dateString) - 2),
                        ($height - $marginBottom) + 4,
                        $dateString, $colors["black"]);
            
            return ($x <= 0)? 1 : $draw_x_axis_markers($x - $interval);
        }; $draw_x_axis_markers($width - $marginRight - $marginLeft);

        // Draw the data into the graph as a continuous line that runs through each data point.
        {
            $xStep = (($width - ($marginLeft + $marginRight)) / (count($data) - 1));
            $yStep = (($height - ($marginTop + $marginBottom)) / ($maxVal - $minVal));
            
            for ($i = 1; $i < count($data); $i++)
            {
                $y1 = (($data[$i-1]["value"] - $minVal) * $yStep);
                $y2 = (($data[$i]["value"] - $minVal) * $yStep);

                imageline($graph,
                        $marginLeft + (($i-1) * $xStep), $marginTop + $y1,
                        $marginLeft + ($i * $xStep), $marginTop + $y2,
                        $colors["black"]);
            }
        }
    }

    return $graph;
}
?>
