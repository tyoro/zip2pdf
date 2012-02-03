<?php

	chdir( "/usr/local/sfw/zip2pdf/" );
	set_include_path(dirname(__FILE__).'/lib:'.get_include_path());
	set_include_path(dirname(__FILE__).'/conf:'.get_include_path());

include_once './conf/load.php';
include_once './lib/fpdimage.php';

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
	
	$inputFile = InputFileFactory::getInputFile( $input_file_name );

	if( empty( $output_dir_path ) ){
		$output_dir_path = $inputFile->dirname.'/';
	}

	$pdf_title = "\xEF\xBB\xBF" . $inputFile->title;
	$prf_author = "\xEF\xBB\xBF" .$inputFile->author;

	$output_file_name = $output_dir_path.$inputFile->title.".pdf";
	
	$canvas = new Imagick();
	$pdf = new Imagick();

	while( $image = $inputFile->getImage() ){
		
		//TODO: MagickGetImageColors
		//MagickGetImageColorspace
		$color = $inputFile->checkColor();
		$ext = $inputFile->getCurrentExt();
		
		//level,ガンマ補正
		//$canvas->gammaImage( 1.0/$conf['gamma'] );
		$image->levelImage(0,1/$conf['gamma'],60535);
		if( in_array( $ext, Array( 'jpg', "jpeg" ) ) ){
			//グレースケール化
			$image->setImageColorspace(Imagick::COLORSPACE_GRAY);
			//$image->setImageType( Imagick::COLORSPACE_GRAY );
		}else{
			//色深度
			$image->setImageDepth(4);
		}

		// 一旦ラスタライズしないとtrimの段階でグレースケール化が消滅する。
		$buf = new Imagick();
		$buf->readImageBlob( $image->getImageBlob() );
		$image->destroy();
		$image = $buf;

		$base_width = $image->getImageWidth(); //横幅（ピクセル）
		$base_height = $image->getImageHeight(); //縦幅（ピクセル）

		if( $TEST_MODE ){
			print "b_width:$base_width\n";
			print "b_height:$base_height\n";
		}

		$image->trimImage( $conf['trim'] );
		$width = $image->getImageWidth(); //横幅（ピクセル）
		$height = $image->getImageHeight(); //縦幅（ピクセル）
		if( $base_width == $width && $base_height == $height ){
			//トリミングされてない(変な枠とかある？
		//	print "no trim\n";

			$clone = $image->clone();

		//	$clone->levelImage(0,1/$conf['gamma'],60535);
			$clone->cropImage( $base_width-64,$base_height-16,32,8);
			$clone->trimImage( $conf['trim'] );

			$width = $clone->getImageWidth(); //横幅（ピクセル）
			$height = $clone->getImageHeight(); //縦幅（ピクセル）
			if( $base_width-64 != $width && $base_height-8 != $height ){
				$image->destroy();
				$image = $clone;
			}else{
				$clone->destroy();
			}
		}

		if( $TEST_MODE ){
			print "width:$width\n";
			print "height:$height\n";
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

		$image->destroy();
		
		$canvas->setImageFormat( $ext );

		$pdf->addImage( $canvas );
		if( $TEST_MODE ){
			$canvas->writeImage( sprintf( "./tmp/%04d.tmp", $inputFile->getIndex() ) );
		}
		$canvas->clear();

	}
	$pdf->setFormat("pdf");
	$pdf->writeImages( $output_file_name, true );
	$pdf->clear();

	$canvas->destroy();
	$pdf->destroy();

	$inputFile->close();

	print "time:".(time()-$start)."sec";

}else{
	print "not args";
}



//class file2img

class InputFile{
	var $filename;
	var $dirname;
	var $extension;

	var $author;
	var $title;

	var $currentExt;

	function __construct( $input_file_name, $path_data ){
		if(	preg_match( "/\[(.*?)\](.*)/", $path_data[ 'filename' ], $matches ) ){
			$this->author = mb_convert_encoding($matches[1],"UTF-8");
			$this->title = mb_convert_encoding($matches[2],"UTF-8");
		}else{
			$this->author = 'unknown';
			$this->title = mb_convert_encoding($path_data[ 'filename' ],"UTF-8");
		}

		$this->filename = $path_data[ 'filename' ];
		$this->dirname = $path_data[ 'dirname' ];
		$this->extension = $path_data[ 'extension' ];
	}

	function getImage(){ }
	function hasNext(){ }
	function close(){ }
	function checkColor(){}
	function getIndex(){}

	function getCurrentExt(){
		return $this->currentExt;
	}
}

Class InputZipFile extends InputFile{
	var $za;

	var $i;
	var $currentStat;

	function __construct( $input_file_name, $path_data ){
		parent::__construct($input_file_name, $path_data);	
		
		$this->za = new ZipArchive();
		$this->za->open( $input_file_name);
	}

	function getImage(){
		global $TEST_MODE;
		while( $this->i < $this->za->numFiles ){
			$stat = $this->za->statIndex( $this->i );
			$file_name = mb_convert_encoding( ( $stat['name']  ),"UTF8","SJIS" );
			$path_data = pathinfo( $file_name );
			if( $TEST_MODE  )
				print $file_name."\n";
			$ext = $path_data[ "extension" ];
			if( $stat['size'] <= 0 || !in_array( $ext, Array("jpg","png","jpeg","gif") ) ){
				print "no support extension.\n";
				$this->i++;
				continue;
			}
			$this->currentExt = $ext;
			$this->currentStat = $stat;
			
			if( $TEST_MODE && $this->i%30 ){
				$this->i++;
				continue;
			}
			$image = new Imagick();
			$image->readImageBlob($this->za->getFromIndex($this->i));
			
			$this->i++;
			return $image;
		}
		return false;
	}

	function checkColor(){
		global $conf;
		return $this->currentStat[ "size" ] > $conf['img_threshold'];
	}

	function close(){
		$this->za->close();
	}

	function getIndex(){
		return $this->i;
	}
}
Class InputPDFFile extends InputFile{
	var $pdf;
	var $pageCnt;
	var $i;

	var $color;
	function __construct( $input_file_name, $path_data ){
		parent::__construct($input_file_name, $path_data);	
		
		$this->pdf = new FPDImage();
		$this->pageCnt = $this->pdf->setSourceFile( $input_file_name );
		$this->i = 0;
		$this->currentExt = "jpeg";
		$this->color = true;

		$this->title = $this->title."_kin";
	}
	function getImage(){
	global $TEST_MODE;
		
		while( $this->i < $this->pageCnt ){
			if( $TEST_MODE && $this->i%30 ){
				$this->i++;
				continue;
			}
			$data = $this->pdf->getImage( $this->i +1 );
			$this->i++;

			if( is_null( $data ) ){ continue; }
			if( $TEST_MODE  )
				print "pdf[".$this->i."] load.\n";
			$image = new Imagick();
			$image->setResolution( 72, 72 );
			$image->readImageBlob($data['blob']);

			if( $TEST_MODE  )
				$image->writeImage( sprintf( "./tmp/debug%04d.tmp",$this->i ) );

			$this->color = ( $data['colorspace'] ==  "/DeviceRGB" );

			return $image;
		}
		return false;
	}
	function checkColor(){ return $this->color; }
	function getIndex(){
		return $this->i;
	}

	function close(){
		$this->pdf->Close();
	}
}

Class InputPDFFileIM extends InputFile{
	var $pdf;
	var $next;
	function __construct( $input_file_name, $path_data ){
		parent::__construct($input_file_name, $path_data);	
		

		$this->pdf = new Imagick( $input_file_name );
		$this->pdf->setFirstIterator();
		$this->next = $this->pdf->hasNextImage();

		$this->title = $this->title."_out";
	}
	function getImage(){
	global $TEST_MODE;
		if( $this->next ){
			$image = $this->pdf->getImage();

			//$image->setImageUnits(0);
			//$image->setImagePage(
			/*$image->setImageExtent (
				$image->getImageWidth()*25.4  ,
				$image->getImageHeight()*25.4
			);*/

			//debug
			$image->writeImage( sprintf( "./tmp/debug%04d.pdf",$this->pdf->getIteratorIndex() ) );

			print "pdf[".$this->pdf->getIteratorIndex()."] load.\n";
			$this->pdf->nextImage();
			$this->next = $this->pdf->hasNextImage();
			if( $TEST_MODE ){
				for( $i=0;$i<29;$i++){
					$this->pdf->nextImage();
					$this->next = $this->pdf->hasNextImage();
					if(! $this->next ){ return false; }
				}
			}
			return $image;
		}else{
			return false;
		}
	}
	function getCurrentExt(){ return 'pdf'; }
	function checkColor(){ return false; }
	function getIndex(){
		return $this->pdf->getIteratorIndex();
	}

	function close(){
		$this->pdf->destroy();
	}
}

Class InputFileFactory
{
	static function getInputFile( $input_file_name ){
		$input_file_name =  realpath($input_file_name );

		if (!file_exists( $input_file_name ) ){
			exit( "input file not found.");
		}
		print "input file '$input_file_name'\n";
		$path_data = pathinfo( $input_file_name );

		switch( strtolower( $path_data[ "extension" ] ) ){
			case "zip":
				return new InputZipFile( $input_file_name, $path_data );
			case "pdf":
				return new InputPDFFile( $input_file_name, $path_data );
			default:
				exit( "no zip file.");
		}
	}
}


?>
