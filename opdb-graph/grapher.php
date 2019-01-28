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

    $colors = ["black"    => imagecolorallocate($graph, 20, 20, 20),
               "gray"     => imagecolorallocate($graph, 165, 165, 165),
               "dimgray"  => imagecolorallocate($graph, 120, 120, 120)];
    
    $marginLeft = 13;
    $marginRight = 65;
    $marginTop = 27;
    $marginBottom = 35;

    $graphWidth = ($width - $marginLeft - $marginRight);
    $graphHeight = ($height - $marginTop - $marginBottom);
    if ($graphWidth <= 0 || $graphHeight <= 0) return $graph;

    $startTime = $data[count($data)-1]["timestamp"];
    $endTime = $data[0]["timestamp"];
    if ($endTime < $startTime) throw new \Exception("Invalid timestamps detected.");

    $maxVal = max(array_column($data, "value"));
    $minVal = min(array_column($data, "value"));

    // Draw the graph.
    {
        // Print out the title.
        imagestring($graph, 2, ($width/2 - $string_pixel_width($filename)/2), 5, $filename, $colors["black"]);

        // Draw horizontal markers at even intervals along the y axis.
        $draw_y_axis_markers = function($yFraction) use(&$draw_y_axis_markers, $marginLeft, $marginRight, $marginBottom,
                                                        $marginTop, $colors, $graph, $width, $height, $minVal, $maxVal,
                                                        $graphHeight)
        {
            $interval = 0.125;
            if ($height <= 190) $interval = 0.25;
            if ($height <= 110) $interval = 0.5;

            $y = (($height - $marginBottom) - ($graphHeight * $yFraction));
            $value = round($minVal + (($maxVal - $minVal) * $yFraction), 4);
            imagestring($graph, 2, ($width - $marginRight) + 7, ($y - imagefontheight(2)/2), $value, $colors["dimgray"]);
            imagedashedline($graph, $marginLeft, $y, ($width - $marginRight), $y, $colors["gray"]);

            return ($yFraction >= 1)? 1 : $draw_y_axis_markers($yFraction + $interval);
        }; $draw_y_axis_markers(0);

        // Draw vertical markers at even intervals along the x axis.
        $draw_x_axis_markers = function($x) use(&$draw_x_axis_markers, $data, $marginLeft, $marginRight,
                                                $marginBottom, $marginTop, $colors, $graph, $width, $height,
                                                $string_pixel_width, $graphWidth, $startTime, $endTime)
        {
            // Guarantee that the graph's left edge gets a vertical marker.
            if ($x < 0) $x = 0;

            $interval = 80;

            imagedashedline($graph,
                            ($marginLeft + $x), $marginTop,
                            ($marginLeft + $x), ($height - $marginBottom) + (($x > 0)? 3 : 0),
                            $colors["gray"]);

            // Find the timestamp that corresponds to the given x coordinate on the graph.
            $timePerX = ($endTime - $startTime) / $graphWidth;
            $currentTime = ($startTime + ($x * $timePerX));

            // Print out this marker's timestamp.
            $dateString = date("d-M-y", $currentTime);
            $timeString = date("H:i:s", $currentTime);
            if (($x - $string_pixel_width($dateString)) >= 0 &&
                ($x - $string_pixel_width($timeString)) >= 0)
            {
                imagestring($graph, 2,
                            ($marginLeft + $x - $string_pixel_width($dateString) + imagefontwidth(2)/2),
                            ($height - $marginBottom) + 5,
                            $dateString, $colors["dimgray"]);

                imagestring($graph, 2,
                            ($marginLeft + $x - $string_pixel_width($timeString) + imagefontwidth(2)/2),
                            ($height - $marginBottom) + 5 + imagefontheight(2),
                            $timeString, $colors["dimgray"]);
            }
            
            return ($x <= 0)? 1 : $draw_x_axis_markers($x - $interval);
        }; $draw_x_axis_markers($graphWidth);

        // If there's only one data point, draw it on the graph as a point rather than as a line.
        if ($endTime == $startTime)
        {
            imagefilledellipse($graph,
                               ($width - $marginRight),
                               ($marginTop + ($graphHeight/2)),
                               5, 5, $colors["black"]);
        }
        // Otherwise, draw the data as a continuous line that runs through each data point.
        else
        {
            // If all the values to be graphed are identical, just draw a straight horizontal
            // line through the middle of the graph.
            if ($maxVal == $minVal)
            {
                $verticalMiddle = ($marginTop + ($graphHeight/2));

                imageline($graph,
                          $marginLeft, $verticalMiddle,
                          ($width - $marginRight), $verticalMiddle,
                          $colors["black"]);
            }
            else
            {
                $xStep = ($graphWidth / ($endTime - $startTime));
                $yStep = ($graphHeight / ($maxVal - $minVal));

                for ($i = (count($data) - 1); $i > 0; $i--)
                {
                    $y1 = ($graphHeight - (($data[$i-1]["value"] - $minVal) * $yStep));
                    $y2 = ($graphHeight - (($data[$i]["value"] - $minVal) * $yStep));

                    $x1 = (($data[$i-1]["timestamp"] - $startTime) * $xStep);
                    $x2 = (($data[$i]["timestamp"] - $startTime) * $xStep);

                    imageline($graph,
                              ($marginLeft + $x1), ($marginTop + $y1),
                              ($marginLeft + $x2), ($marginTop + $y2),
                              $colors["black"]);
                }
            }
        }
    }

    return $graph;
}
?>
