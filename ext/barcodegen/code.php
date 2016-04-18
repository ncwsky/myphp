<?php
// Including all required classes
require('class/BCGFont.php');
require('class/BCGColor.php');
require('class/BCGDrawing.php'); 

$barcode_type = array('codabar', 'code11', 'code39', 'code39extended', 'code93', 'code128', 'ean8', 'ean13', 'gs1128', 'isbn', 'i25', 's25', 'msi', 'upca', 'upce', 'upcext2', 'upcext5', 'postnet', 'othercode');

$type = isset($_GET['type']) ? $_GET['type'] : 'code39';
if(!in_array($type, $barcode_type)) $type='code39';

$cls_name = 'BCG'.$type;
// Including the barcode technology
include('class/'.$cls_name.'.barcode.php'); 

$text = isset($_GET['text']) ? $_GET['text'] : 'HELLO';

// Loading Font
// $font = new BCGFont('./class/font/Arial.ttf', 18);

// The arguments are R, G, B for color.
$color_black = new BCGColor(0, 0, 0);
$color_white = new BCGColor(255, 255, 255); 

$code = new $cls_name();
$code->setScale(1.5); // Resolution
$code->setThickness(28); // Thickness
$code->setForegroundColor($color_black); // Color of bars
$code->setBackgroundColor($color_white); // Color of spaces
// $code->setFont($font); // Font (or 0)
$code->parse($text); // Text


/* Here is the list of the arguments
1 - Filename (empty : display on screen)
2 - Background color */
$drawing = new BCGDrawing('', $color_white);
$drawing->setBarcode($code);
$drawing->draw();

// Header that says it is an image (remove it if you save the barcode to a file)
header('Content-Type: image/png');
// header('Content-Disposition: inline; filename="barcode.png"');

// Draw (or save) the image into PNG format.
$drawing->finish(BCGDrawing::IMG_FORMAT_PNG);