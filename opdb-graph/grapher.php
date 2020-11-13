<?php
namespace opdb_graph;

/*
 * 2019, 2020 Tarpeeksi Hyvae Soft
 * 
 * Software: opdb-graph
 * 
 * Graphs data from an opdb database file.
 *
 */

function create_graph(int $graphWidth,
                      int $graphHeight,
                      string $graphTitle,
                      string $dataSrcFilename,
                      int $numDays)
{
    if (!file_exists($dataSrcFilename)) throw new \Exception("Can't find the given database file.");
    if ($graphWidth < 1 || $graphHeight < 1) throw new \Exception("Invalid graph dimensions.");

    // Returns the width of the given string in pixels. Note: makes a hardcoded assumption
    // about which font the string uses.
    $string_pixel_width = function(string $string) { return (strlen($string) * imagefontwidth(2)); };

    $graph = imagecreatetruecolor($graphWidth, $graphHeight);
    imagefill($graph, 0, 0, imagecolorallocate($graph, 190, 190, 190));

    // Get the data to be graphed.
    $data = fetch_data($dataSrcFilename, $numDays);
    if (count($data) <= 0) return $graph;

    $colors = ["black"    => imagecolorallocate($graph, 20, 20, 20),
               "gray"     => imagecolorallocate($graph, 165, 165, 165),
               "dimgray"  => imagecolorallocate($graph, 120, 120, 120)];

    $maxVal = max(array_column($data, "value"));
    $minVal = min(array_column($data, "value"));
    
    $marginLeft = (imagefontwidth(2) * 2);
    $marginRight = 14;
    $marginTop = 29;
    $marginBottom = 39;

    // Spacing between labels on the X and Y axes.
    $horizontalLabelInterval = 80;
    $verticalLabelInterval = 0.125;
    if ($graphHeight <= 190) $verticalLabelInterval = 0.25;
    if ($graphHeight <= 110) $verticalLabelInterval = 0.5;

    // Size the right margin so that all value labels on the Y axis can fit.
    $fit_right_margin_to_y_labels = function($yFraction) use(&$fit_right_margin_to_y_labels, &$marginRight, $graphHeight,
                                                             $minVal, $maxVal, $verticalLabelInterval)
    {
        $value = round($minVal + (($maxVal - $minVal) * $yFraction), 3);
        $marginRight = max($marginRight, (strlen(strval($value)) + 3) * imagefontwidth(2));

        return ($yFraction >= 1)? 1 : $fit_right_margin_to_y_labels($yFraction + $verticalLabelInterval);
    }; $fit_right_margin_to_y_labels(0);

    $graphInnerWidth = ($graphWidth - $marginLeft - $marginRight);
    $graphInnerHeight = ($graphHeight - $marginTop - $marginBottom);
    if ($graphInnerWidth <= 0 || $graphInnerHeight <= 0) return $graph;

    $startTime = $data[count($data)-1]["timestamp"];
    $endTime = $data[0]["timestamp"];
    if ($endTime < $startTime) throw new \Exception("Invalid timestamps detected.");

    // Draw the graph.
    {
        // Print out the title.
        imagestring($graph, 2, ($graphWidth/2 - $string_pixel_width($graphTitle)/2), 7, $graphTitle, $colors["black"]);

        // Draw horizontal markers at even intervals along the y axis.
        $draw_y_axis_markers = function($yFraction) use(&$draw_y_axis_markers, $marginLeft, $marginRight, $marginBottom,
                                                        $marginTop, $colors, $graph, $graphWidth, $graphHeight, $minVal, $maxVal,
                                                        $graphInnerHeight, $verticalLabelInterval)
        {
            $y = (($graphHeight - $marginBottom) - ($graphInnerHeight * $yFraction));
            $value = round($minVal + (($maxVal - $minVal) * $yFraction), 3);
            imagestring($graph, 2, ($graphWidth - $marginRight) + 7, ($y - imagefontheight(2)/2), $value, $colors["dimgray"]);
            imagedashedline($graph, $marginLeft, $y, ($graphWidth - $marginRight), $y, $colors["gray"]);

            return ($yFraction >= 1)? 1 : $draw_y_axis_markers($yFraction + $verticalLabelInterval);
        }; $draw_y_axis_markers(0);

        // Draw vertical markers at even intervals along the x axis.
        $draw_x_axis_markers = function($x) use(&$draw_x_axis_markers, $data, $marginLeft, $marginRight, $marginBottom,
                                                $marginTop, $colors, $graph, $graphWidth, $graphHeight, $string_pixel_width,
                                                $graphInnerWidth, $startTime, $endTime, $horizontalLabelInterval)
        {
            // Guarantee that the graph's left edge gets a vertical marker.
            if ($x < 0) $x = 0;

            imagedashedline($graph,
                            ($marginLeft + $x), $marginTop,
                            ($marginLeft + $x), ($graphHeight - $marginBottom) + (($x > 0)? 3 : 0),
                            $colors["gray"]);

            // Find the timestamp that corresponds to the given x coordinate on the graph.
            $timePerX = ($endTime - $startTime) / $graphInnerWidth;
            $currentTime = ($startTime + ($x * $timePerX));

            // Print out this marker's timestamp.
            $dateString = date("d-M-y", $currentTime);
            $timeString = date("H:i:s", $currentTime);
            if (($x - $string_pixel_width($dateString)) >= 0 &&
                ($x - $string_pixel_width($timeString)) >= 0)
            {
                imagestring($graph, 2,
                            ($marginLeft + $x - $string_pixel_width($dateString) + imagefontwidth(2)/2),
                            ($graphHeight - $marginBottom) + 5,
                            $dateString, $colors["dimgray"]);

                imagestring($graph, 2,
                            ($marginLeft + $x - $string_pixel_width($timeString) + imagefontwidth(2)/2),
                            ($graphHeight - $marginBottom) + 5 + imagefontheight(2),
                            $timeString, $colors["dimgray"]);
            }
            
            return ($x <= 0)? 1 : $draw_x_axis_markers($x - $horizontalLabelInterval);
        }; $draw_x_axis_markers($graphInnerWidth);

        // If there's only one data point, draw it on the graph as a point rather than as a line.
        if ($endTime == $startTime)
        {
            imagefilledellipse($graph,
                               ($graphWidth - $marginRight),
                               ($marginTop + ($graphInnerHeight/2)),
                               5, 5, $colors["black"]);
        }
        // Otherwise, draw the data as a continuous line that runs through each data point.
        else
        {
            // If all the values to be graphed are identical, just draw a straight horizontal
            // line through the middle of the graph.
            if ($maxVal == $minVal)
            {
                $verticalMiddle = ($marginTop + ($graphInnerHeight/2));

                imageline($graph,
                          $marginLeft, $verticalMiddle,
                          ($graphWidth - $marginRight), $verticalMiddle,
                          $colors["black"]);
            }
            else
            {
                $xStep = ($graphInnerWidth / ($endTime - $startTime));
                $yStep = ($graphInnerHeight / ($maxVal - $minVal));

                for ($i = (count($data) - 1); $i > 0; $i--)
                {
                    $y1 = ($graphInnerHeight - (($data[$i-1]["value"] - $minVal) * $yStep));
                    $y2 = ($graphInnerHeight - (($data[$i]["value"] - $minVal) * $yStep));

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
