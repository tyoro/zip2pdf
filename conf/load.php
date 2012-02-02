<?php

include_once './conf/spyc.php';
include_once './conf/base.php';

$y2c = new yaml2conf('./conf.yml',$conf);

if( $y2c->check() ){
	print $y2c->errorMessage();
	exit;
}

$conf = $y2c->conf;

class  yaml2conf
{
	var $conf;
	var $yaml;
	var $error = false;
	var $errorMessages = array();
	public function __construct( $yaml_file, $conf )
	{
		if( !is_file( $yaml_file ) ){ $this->addError( 'conf.yml not found.' ); }
		$this->yaml = Spyc::YAMLLoad( $yaml_file );
		$this->conf = $conf;
	}

	
	public function check()
	{	
		//必須
		foreach( Array( ) as $required )
		{
			$this->nullCheck( $required, "$required not found." );
		}

		//setting
		foreach( $this->conf as $key => $def  )
		{
			if(  isset( $this->yaml[$key] ) && !is_array( $this->conf[ $key ] ) ) { $this->conf[ $key ] = $this->yaml[ $key ]; }
		}
		return $this->error;
	}
	
	public function errorMessage()
	{
		return join("<br/>\n",$this->errorMessages);
	}
	
	//checker
	protected function nullCheck( $check, $message )
	{
		if( is_string( $check) )
		{
			if( isset( $this->yaml[ $check ] )  && !empty( $this->yaml[ $check ] ) )
			{
				return true;
			}
			else
			{
				$this->addError( $message );
				return false;
			}
		}
		else
		{
			if( !empty( $check ) )
			{
				return true;
			}
			else
			{
				$this->addError( $message );
				return false;
			}
		}
	}

	protected function addError( $message )
	{
		array_push( $this->errorMessages, $message );
		$this->error = true;
	}

}

?>
