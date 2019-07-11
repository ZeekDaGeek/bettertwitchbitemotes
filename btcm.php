<?php

    require_once("./vendor/autoload.php");

    use GIFEndec\Events\FrameDecodedEvent;
    use GIFEndec\Events\FrameRenderedEvent;
    use GIFEndec\IO\FileStream;
    use GIFEndec\Decoder;
    use GIFEndec\Renderer;

    $env = array();
    $cmd = new Commando\Command();

    $cmd
        ->option()
        ->require()
        ->describedAs('Convert Twitch emotes from their standard gif format to ______.');

    $emote_name = "Cheer1";

    $input_dir = "./input";

    $file_light = "{$input_dir}/{$emote_name}_light_4.gif";
    $file_dark = "{$input_dir}/{$emote_name}_dark_4.gif";

    $output_dir = "./output";

    $output_light = "{$output_dir}/{$emote_name}_light.png";
    $output_dark = "{$output_dir}/{$emote_name}_dark.png";

    $light_handle = create_sprite_map($file_light);
    imagepalettetotruecolor($light_handle["image_handle"]);
    imagepng($light_handle["image_handle"], "{$output_dir}/{$emote_name}_spritemap_light.png");

    $dark_handle = create_sprite_map($file_dark);
    imagepalettetotruecolor($dark_handle["image_handle"]);
    imagepng($dark_handle["image_handle"], "{$output_dir}/{$emote_name}_spritemap_dark.png");

    $diff_handle = compare_images($light_handle["image_handle"], $dark_handle["image_handle"]);
    imagepng($diff_handle["diff"], "{$output_dir}/{$emote_name}_spritemap_diff.png");
    imagepng($diff_handle["clean"], "{$output_dir}/{$emote_name}_spritemap_4.png");
    imagepng($diff_handle["scale3_4"], "{$output_dir}/{$emote_name}_spritemap_3.png");
    imagepng($diff_handle["scale2_4"], "{$output_dir}/{$emote_name}_spritemap_2.png");
    imagepng($diff_handle["scale1_4"], "{$output_dir}/{$emote_name}_spritemap_1.png");

    // Destroy images to clear memory?
    imagedestroy($light_handle["image_handle"]);
    imagedestroy($dark_handle["image_handle"]);
    imagedestroy($diff_handle["diff"]);
    imagedestroy($diff_handle["clean"]);
    imagedestroy($diff_handle["scale3_4"]);
    imagedestroy($diff_handle["scale2_4"]);
    imagedestroy($diff_handle["scale1_4"]);

    print("Frames: " . $light_handle["frames"]);

    //echo "{$cmd[0]} and {$cmd[1]}";
    echo PHP_EOL;

    function compare_images($light_handle, $dark_handle) {

        $marginError = 30;

        $light_background = array(
                "red"   => 250,
                "blue"  => 249,
                "green" => 250,
            );

        $dark_background = array(
                "red"   => 15,
                "blue"  => 14,
                "green" => 17,
            );

        /* The math:

        outAlpha = srcAlpha + dstAlpha(1 - srcAlpha) // Not needed.
        outRGB = (srcRGB*srcA + dstRGB*dstA(1-srcAlpha)) / outAlpha

        16, 5
        Light != Dark
        Red: 209 != 89
        Blue: 210 != 93
        Green: 209 != 89

        x = srcRGB*srcA
        Light:
            R: 209 = x + 250; x = -41
            G: 210 = x + 249; x = -39
            B: 209 = x + 250; x = -41

        Dark:
            R: 89 = x + 15; x = 74
            G: 93 = x + 14; x = 79
            B: 89 = x + 17; x = 74

        Guessing color (89, 89, 89): 
            R: 209 = 89*x + 250(1 - x); 209 = 89x + 250 - 250x; 209 = -161x + 250; -161x = 250 - 209; x = -41 / -161; x = 0.25
            G:
            B:

        outRGB = srcRGB*srcA + dstRGB*1 - dstRGB*srcAlpha
        srcRGB*srcA = dstRGB - dstRGB - outRGB
        
         */

        $width = imagesx($light_handle);
        $height = imagesy($light_handle);

        // Create a new image to display all of the differing pixels.
        $diffImage = imagecreatetruecolor($width, $height);
        imagealphablending($diffImage, false);
        imagesavealpha($diffImage, true);
        $background = imagecolorallocatealpha($diffImage, 0, 0, 0, 127);
        imagefill($diffImage, 0, 0, $background);

        // Create a new image for the clean plate version.
        $cleanImage = imagecreatetruecolor($width, $height);
        imagealphablending($cleanImage, false);
        imagesavealpha($cleanImage, true);
        $background = imagecolorallocatealpha($cleanImage, 0, 0, 0, 127);
        imagefill($cleanImage, 0, 0, $background);

        $color = imagecolorallocatealpha($diffImage, 255, 0, 255, 0);

        $iY = 0;

        if (isset($env["DEBUG"]) && $env["DEBUG"] == true)
            $fh = fopen("./debug_output.txt", "w");

        while ($iY <= $height - 1) {

            $iX = 0;

            while ($iX <= $width - 1) {

                $light_pixel = imagecolorat($light_handle, $iX, $iY);
                $light_colors = imagecolorsforindex($light_handle, $light_pixel);

                $dark_pixel = imagecolorat($dark_handle, $iX, $iY);
                $dark_colors = imagecolorsforindex($dark_handle, $dark_pixel);

                if ($light_colors["red"] != $dark_colors["red"] OR $light_colors["blue"] != $dark_colors["blue"] OR $light_colors["green"] != $dark_colors["green"] OR $light_colors["alpha"] != $dark_colors["alpha"]) {

                    if (isset($env["DEBUG"]) && $env["DEBUG"] == true)
                        fwrite($fh, "Color mismatch at {$iX}, {$iY}." . PHP_EOL);

                    $marginRed = abs($light_colors["red"] - $dark_colors["red"]);
                    $marginBlue = abs($light_colors["blue"] - $dark_colors["blue"]);
                    $marginGreen = abs($light_colors["green"] - $dark_colors["green"]);

                    // Check if differences are within the margin of error.
                    if ($marginRed <= $marginError OR $marginBlue <= $marginError OR $marginGreen <= $marginError) {

                        // If they are within the margin, send them to the clean plate image.
                        /*imagesetpixel($cleanImage, $iX, $iY,
                                imagecolorallocatealpha(
                                    $cleanImage,
                                    $light_colors["red"],
                                    $light_colors["green"],
                                    $light_colors["blue"],
                                    0
                                )
                            );*/

                        imagesetpixel($cleanImage, $iX, $iY, $light_pixel);

                        $iX++;
                        continue;
                    }

                    // Log if the color is not within the margin of error.
                    if (isset($env["DEBUG"]) && $env["DEBUG"] === true) {
                        if ($light_colors["red"] != $dark_colors["red"])
                            fwrite($fh, "\tRed: {$light_colors["red"]} != {$dark_colors["red"]}" . PHP_EOL);

                        if ($light_colors["blue"] != $dark_colors["blue"])
                            fwrite($fh, "\tBlue: {$light_colors["blue"]} != {$dark_colors["blue"]}" . PHP_EOL);

                        if ($light_colors["green"] != $dark_colors["green"])
                            fwrite($fh, "\tGreen: {$light_colors["green"]} != {$dark_colors["green"]}" . PHP_EOL);

                        if ($light_colors["alpha"] != $dark_colors["alpha"])
                            fwrite($fh, "\tAlpha: {$light_colors["alpha"]} != {$dark_colors["alpha"]}" . PHP_EOL);
                    }
                   
                    // Draw a pink pixel in the diff image to see where things are different.
                    imagesetpixel($diffImage, $iX, $iY, $color);

                } elseif ($light_colors["alpha"] != 127) {

                    // If they are identical send them to the clean plate image.
                    /*imagesetpixel($cleanImage, $iX, $iY,
                            imagecolorallocatealpha(
                                $cleanImage,
                                $light_colors["red"],
                                $light_colors["blue"],
                                $light_colors["green"],
                                0
                            )
                        );*/

                    imagesetpixel($cleanImage, $iX, $iY, $light_pixel);

                }

                $iX++;

            }

            $iY++;

        }

        if (isset($env["DEBUG"]) && $env["DEBUG"] == true)
            fclose($fh);

        // Create a new image for the 3/4 version.
        $scale3_4Image = imagecreatetruecolor($width * 3 / 4, $height * 3 / 4);
        imagealphablending($scale3_4Image, false);
        imagesavealpha($scale3_4Image, true);
        $background = imagecolorallocatealpha($scale3_4Image, 0, 0, 0, 127);
        imagefill($scale3_4Image, 0, 0, $background);

        imagepalettecopy($scale3_4Image, $light_handle);

        // Create a new image for the 2/4 version.
        $scale2_4Image = imagecreatetruecolor($width * 2 / 4, $height * 2 / 4);
        imagealphablending($scale2_4Image, false);
        imagesavealpha($scale2_4Image, true);
        $background = imagecolorallocatealpha($scale2_4Image, 0, 0, 0, 127);
        imagefill($scale2_4Image, 0, 0, $background);

        imagepalettecopy($scale2_4Image, $light_handle);

        // Create a new image for the 1/4 version.
        $scale1_4Image = imagecreatetruecolor($width * 1 / 4, $height * 1 / 4);
        imagealphablending($scale1_4Image, false);
        imagesavealpha($scale1_4Image, true);
        $background = imagecolorallocatealpha($scale1_4Image, 0, 0, 0, 127);
        imagefill($scale1_4Image, 0, 0, $background);

        imagepalettecopy($scale1_4Image, $light_handle);


        imagecopyresampled($scale3_4Image, $cleanImage, 0, 0, 0, 0, $width * 3 / 4, $height * 3 / 4, $width, $height);
        imagecopyresampled($scale2_4Image, $cleanImage, 0, 0, 0, 0, $width * 2 / 4, $height * 2 / 4, $width, $height);
        imagecopyresampled($scale1_4Image, $cleanImage, 0, 0, 0, 0, $width * 1 / 4, $height * 1 / 4, $width, $height);

        return array(
            "diff" => $diffImage,
            "clean" => $cleanImage,
            "scale3_4" => $scale3_4Image,
            "scale2_4" => $scale2_4Image,
            "scale1_4" => $scale1_4Image,
        );

    }


    function create_sprite_map($imagefile) {

        $handle = new FileStream($imagefile);
        $gifDecoder = new Decoder($handle);

        // Get dimensions of gif.
        $imageDimensions = getimagesize($imagefile);
        $imageDimensions = array(
                "width" => $imageDimensions[0],
                "height" => $imageDimensions[1]
            );

        $gifRenderer = new Renderer($gifDecoder);

        $newImage = imagecreatetruecolor(1, $imageDimensions["height"]);
        imagesavealpha($newImage, true);
        $background = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
        imagefill($newImage, 0, 0, $background);

        $frames = 0;
        $animation = array();

        $gifRenderer->start(function (FrameRenderedEvent $event) use (&$newImage, $imageDimensions, &$frames, &$animation) {


            $width = ($event->frameIndex == 0) ? $imageDimensions["width"] : imagesx($newImage) + $imageDimensions["width"]; 
            $height = imagesy($newImage);

            $frames = $event->frameIndex + 1;
            $animation[$event->frameIndex] = $event->decodedFrame->getDuration();

            // There's no way to resize an existing image element, so instead we create a new one.
            $newNewImage = imagecreatetruecolor($width, $height);
            imagesavealpha($newNewImage, true);
            $background = imagecolorallocatealpha($newNewImage, 0, 0, 0, 127);
            imagefill($newNewImage, 0, 0, $background);

            // Copy the exist image to the new canvas.
            imagecopy($newNewImage, $newImage, 0, 0, 0, 0, imagesx($newImage), imagesy($newImage));

            // Copy the new frame to the sprite map.
            if ($event->frameIndex == 0) {
                // This part is required because you must have an image with a width of 1 to start out with, no 0 width images.
                imagecopy($newNewImage, $event->renderedFrame, 0, 0, 0, 0, $imageDimensions["width"], $imageDimensions["height"]);
            } else {
                imagecopy($newNewImage, $event->renderedFrame, imagesx($newImage), 0, 0, 0, $imageDimensions["width"], $imageDimensions["height"]);
            }

            imagedestroy($newImage);
            $newImage = $newNewImage;

        });

        return array(
            "image_handle" => $newImage,
            "dimensions" => $imageDimensions,
            "frames" => $frames,
            "animation" => $animation
        );

    }