<?php

echo '<html><head></head><body>';

echo '<p>Hi jon</p>';

// Construct the path of the photos to convert
$original_path = '/var/www/html/wp-content/plant_photos/original/';

$find_path = $original_path.'*.jpg';

echo '<p>$find_path='.$find_path.'</p>';

foreach (glob($find_path) as $file_path) {


//    echo '<p>Found file called='.$file_path.'</p>';

    // create a new name for the file
    $filename = basename($file_path);
    $new_filename = str_replace( '-', '_', strtolower( $filename ) );

    // rename the file
    if (rename($file_path, $original_path.$new_filename)) {
        echo '<p>Successfully renamed file from '.$file_path.' to '.$new_filename.'<\p>';
    } else {
        echo '<p>FAILED to rename file '.$file_path.' to '.$new_filename.'<\p>';
    }
}

echo '</body></html>';

?>
