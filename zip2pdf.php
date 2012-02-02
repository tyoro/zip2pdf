<?php

chdir( "/usr/local/sfw/zip2pdf/" );

include_once './conf/load.php';

$start = time();
$TEST_MODE = false;
$output_dir_path = '';

if( $argc > 1 ){

	for( $i=1; $i<$argc;$i++){
		switch( $argv[$i] ){
			case "--test":
				$TEST_MODE = true;
				break;
			case "--input": case "--i":
				$input_file_name = $argv[++$i];
				break;
			case "--output": case "--o":
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

		$color = false;
		// カラー画像の類であるかどうかの判定
		if( $stat[ "size" ] > $conf['img_threshold'] ){
			$color = true;
		}

		$base_width = $image->getImageWidth(); //横幅（ピクセル）
		$base_height = $image->getImageHeight(); //縦幅（ピクセル）

		$image->trimImage( 50.0 );
		$width = $image->getImageWidth(); //横幅（ピクセル）
		$height = $image->getImageHeight(); //縦幅（ピクセル）
		if( $base_width == $width && $base_height == $height ){
			//トリミングされてない(変な枠とかある？
			print "no trim\n";

			$clone = $image->clone();

			$clone->levelImage(0,1/$conf['gamma'],60535);
			$clone->cropImage( $base_width-64,$base_height-8	,32,4);
			$clone->trimImage( 50.0 );

			$width = $clone->getImageWidth(); //横幅（ピクセル）
			$height = $clone->getImageHeight(); //縦幅（ピクセル）
			if( $base_width-64 != $width && $base_height-8 != $height ){
				$image->destroy();
				$image = $clone;
			}else{
				$clone->destroy();
			}
		}

		//拡大される場合
		if( $conf[ 'width' ] > $width && $conf[ 'height' ] > $height ){
			$l_margine = ( $conf[ 'width' ] - $width ) /2;
			$t_margine = ( $conf[ 'height' ] - $height ) /3;
		}else{
			$new_width = $conf[ 'width' ];
			$rate = $new_width / $width;
			$new_height = $rate * $height;
			if( $new_height > $conf[ 'height' ] ){
				$new_height = $conf[ 'height' ];
				$rate = $new_height / $height;
				$new_width = $rate * $width;

				$l_margine = ($conf['width'] - $new_width)/2;
				$t_margine = 0;
			}else{
				$l_margine = 0;
				$t_margine = ($conf[ 'height' ] - $new_height)/2;
			}
			//リサイズ
			$image->resizeImage( $new_width, $new_height, imagick::FILTER_MITCHELL, 1);
		}

		$canvas->newImage( $conf['width'], $conf[ 'height' ], new ImagickPixel("white"));
		$canvas->compositeImage($image,imagick::COMPOSITE_OVER,$l_margine,$t_margine);
		$image->clear();

		$canvas->setImageFormat( $ext );

		//level,ガンマ補正
		//$canvas->gammaImage( 1.0/$conf['gamma'] );
		$canvas->levelImage(0,1/$conf['gamma'],60535);
		if( $ext == 'jpg' ){
			//グレースケール化
			$canvas->setImageColorspace(Imagick::COLORSPACE_GRAY);
		}else{
			//色深度
			$canvas->setImageDepth(4);
		}

		$im = new Imagick();
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
