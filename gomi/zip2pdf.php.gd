<?php

define( "KINDLE_WIDTH" , 560 );
define( "KINDLE_HEIGHT", 734 );

if( $argc > 1 ){
	$input_file_name = $argv[1];
	print "input file '$input_file_name'\n";
	$path_data = pathinfo( $input_file_name );
	
	if( $path_data[ "extension" ] != 'zip' ){
		print "no zip file.";
		exit;
	}


	if(	preg_match( "/\[(.*?)\](.*)/", $path_data[ 'filename' ], $matches ) ){
		$author = $matches[1];
		$title = $matches[2];
	}else{
		$author = 'unknown';
		$title = $path_data[ 'filename' ];
	}
	
	$za = new ZipArchive();
	$pdf = PDF_new();
	PDF_begin_document($pdf, "./test.pdf", "");

	pdf_set_info($pdf, "Author", $author);

	pdf_set_info($pdf, "Title", $title);

	pdf_set_info($pdf, "Creator", "zip2pdf");

	//pdf_set_info($pdf, "Subject", "subject tte nani");

	//print $input_file_name;
	$za->open( $input_file_name);
	
	$tmp_file = "/tmp/tmp.jpg";

	for( $i = 0; $i < $za->numFiles; $i++ ){
    	$stat = $za->statIndex( $i );

	    $file_name = mb_convert_encoding( ( $stat['name'] . PHP_EOL ),"UTF8","SJIS" );
		$path_data = pathinfo( $file_name );
		print "'$file_name' checking...\n";
		if( in_array( $path_data[ "extension" ], Array("jpg","png","jpeg","gif") ) ){
			print "no support extension.\n";
			continue;
		}

		pdf_begin_page_ext($pdf, KINDLE_WIDTH, KINDLE_HEIGHT,"" );

		$img = imagecreatefromstring(  $za->getFromIndex($i) );
		if( $stat[ "size" ] > 1000000 ){
			imagefilter($img, IMG_FILTER_GRAYSCALE);
		}
		$new_image = ImageCreateTrueColor(KINDLE_WIDTH, KINDLE_HEIGHT);
		imagefill($new_image, 0, 0, imagecolorallocate( $new_image, 255,255,255) );

		$width = ImageSX($img); //横幅（ピクセル）
		$height = ImageSY($img); //縦幅（ピクセル）

		$new_width = KINDLE_WIDTH;
		$rate = $new_width / $width;
		$new_height = $rate * $height;
		if( $new_height > KINDLE_HEIGHT ){
			$new_height = KINDLE_HEIGHT;
			$rate = $new_height / $height;
			$new_width = $rate * $width;

			$l_margine = (KINDLE_WIDTH - $new_width)/2;
			$t_margine = 0;
		}else{
			$l_margine = 0;
			$t_margine = (KINDLE_HEIGHT - $new_height)/2;
		}
		//imagecopyresized($new_image,$img,$l_margine,$t_margine,0,0,$new_width,$new_height,$width,$height);
		ImageCopyResampled($new_image,$img,$l_margine,$t_margine,0,0,$new_width,$new_height,$width,$height);

		//ガンマ補正
		imagegammacorrect( $new_image, 1.0, 1.6 );

		ImageJPEG($new_image, "/tmp/tmp.jpg", 70);
		
		$img_id = pdf_load_image( $pdf, "jpeg", "/tmp/tmp.jpg", "" );
		PDF_fit_image($pdf,$img_id,0,0,"");
		PDF_close_image( $pdf,$img_id);
		pdf_end_page_ext( $pdf,"");
	}
	
	pdf_close($pdf);
	$za->close();

}else{
	print "not args";
}


