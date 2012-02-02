<?php

chdir( "/usr/local/sfw/zip2pdf/" );

include_once './conf/load.php';

define( "KINDLE_WIDTH" , 560 );
define( "KINDLE_HEIGHT", 734 );

$start = time();
$TEST_MODE = false;
$output_dir_path = '';

if( $argc > 1 ){

	for( $i=1; $i<$argc;$i++){
		switch( $argv[$i] ){
			case "--test":
				$TEST_MODE = true;
				break;
			case "--input":
				$input_file_name = $argv[++$i];
				break;
			case "--output":
				$output_dir_path = $argv[++$i];
				break;
		}
	}

	$input_file_name =  realpath($input_file_name );

	if (!file_exists( $input_file_name ) ){
		exit( "input file not found.");
	}
	print "input file '$input_file_name'\n";
	$path_data = pathinfo( $input_file_name );
	
	if( $path_data[ "extension" ] != 'zip' ){
		exit( "no zip file.");
	}

	if( empty( $output_dir_path ) ){
		$output_dir_path = $path_data['dirname'].'/';
	}

	if(	preg_match( "/\[(.*?)\](.*)/", $path_data[ 'filename' ], $matches ) ){
		$author = mb_convert_encoding($matches[1],"UTF-8");
		$title = mb_convert_encoding($matches[2],"UTF-8");
	}else{
		$author = 'unknown';
		$title = mb_convert_encoding($path_data[ 'filename' ],"UTF-8");
	}

	$pdf_title = "\xEF\xBB\xBF" . $title;
	$prf_author = "\xEF\xBB\xBF" .$author;

	$output_file_name = $output_dir_path.$title.".pdf";
	
	$za = new ZipArchive();

	$za->open( $input_file_name);
	
	$image = new Imagick();
	$canvas = new Imagick();
	$pdf = new Imagick();

	//for( $i = 0; $i < $za->numFiles && $i < 20 ; $i++ ){
	for( $i = 0; $i < $za->numFiles ; $i++ ){

    	$stat = $za->statIndex( $i );

	    $file_name = mb_convert_encoding( ( $stat['name']  ),"UTF8","SJIS" );
		$path_data = pathinfo( $file_name );
		print $file_name."\n";
		$ext = $path_data[ "extension" ];
		if( $stat['size'] <= 0 || !in_array( $path_data[ "extension" ], Array("jpg","png","jpeg","gif") ) ){
			print "no support extension.\n";
			continue;
		}

		if( $TEST_MODE && $i%30 ){
			continue;
		}

		$image->readImageBlob($za->getFromIndex($i));

		if( $stat[ "size" ] > $conf['img_threshold'] ){
		}else{
			//余白除去
			$image->trimImage( $conf['trim'] );
		}

		$width = $image->getImageWidth(); //横幅（ピクセル）
		$height = $image->getImageHeight(); //縦幅（ピクセル）

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

		//リサイズ
		$image->resizeImage( $new_width, $new_height, imagick::FILTER_MITCHELL, 1);
		//$image->resizeImage( KINDLE_WIDTH, KINDLE_HEIGHT, imagick::FILTER_MITCHELL, 1, true);
		//$image->setImagePage( KINDLE_WIDTH, KINDLE_HEIGHT, 0, 0 );

		$canvas->newImage( KINDLE_WIDTH, KINDLE_HEIGHT, new ImagickPixel("white"));
		$canvas->compositeImage($image,imagick::COMPOSITE_OVER,$l_margine,$t_margine);
		$image->clear();

		$canvas->setImageFormat( $ext );

		$im = new Imagick();
		if( $ext == 'jpg' ){
			//グレースケール化
			$canvas->setImageColorspace(Imagick::COLORSPACE_GRAY);
		}else{
			//色深度
			$canvas->setImageDepth(4);
		}
		//ガンマ補正
		$canvas->setImageGamma( $conf['gamma'] );

		$im->readImageBlob( $canvas->getImageBlob() );
		$im->setImageFormat( $ext );
		$pdf->addImage( $im );
		if( $TEST_MODE ){
			$canvas->writeImage( sprintf( "./tmp/%04d.tmp", $i ) );
		}
		$canvas->clear();
		$im->clear();
	}
	$pdf->setFormat("pdf");
	$pdf->writeImages( $output_file_name, true );
	$pdf->clear();

	$canvas->destroy();
	$image->destroy();
	$pdf->destroy();
	$za->close();

	print "time:".(time()-$start)."sec";

}else{
	print "not args";
}

?>
