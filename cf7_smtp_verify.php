<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Plugin Name: CF7 SMTP Verify
Description: Extends Contact Form 7 Email Validation.
Version: 1.0.2
Author: Ido Friedlander
Author URI: https://profiles.wordpress.org/idofri/
*/

require_once ABSPATH . WPINC . '/class-smtp.php';

class CF7_SMTP_Verify extends SMTP {
		
	protected $user;
	
	protected $domain;
	
	protected $ipv4;
	
	protected $log_file;
	
	protected $log_path;
	
	public function __construct() {
		
		add_filter( 'wpcf7_validate_email*', array( &$this, 'filter' ), 20, 2 );
		
		$this->set_log();
		
		$this->set_ipv4();
		
		$this->setDebugLevel(3);
		
		$this->Debugoutput = function( $str, $level ) {
			
			foreach ( explode( PHP_EOL, $str ) as $c ) {
				
				if ( empty( $c ) ) continue;
					
				error_log(  $this->ipv4 . ' - - [' . date('Y-m-d H:i:s') . '] ' . $c . PHP_EOL, 3, $this->log_path . '/' . $this->log_file );
			}
		};
	}
	
	public function filter( $result, $tag ) {
		
		$tag = new WPCF7_Shortcode( $tag );
	 
		if ( 'email*' == $tag->type ) {
			
			if ( isset( $_POST[ $tag->name ] ) && !empty( $_POST[ $tag->name ] ) ) {
				
				if ( !$valid = $this->validate( $_POST[ $tag->name ] ) ) {
					
					$result->invalidate( $tag, __( 'Email address seems invalid.', 'contact-form-7' ) );
				}
			}
		}
	 
		return $result;
	}
	
	private function set_log() {
		
		$this->log_file = 'cf7_smtp-' . date('Y-m-d') . '.log';
		
		$this->log_path = plugin_dir_path( __FILE__ ) . 'logs';
			
		if ( !file_exists( $this->log_path ) ) {
			
			mkdir( $this->log_path, 0777, true );
			
		} else {
			
			chmod( $this->log_path, 0777 );
		}
		
		if ( !is_file( $this->log_path . '/' . $this->log_file ) ) {
			
			touch( $this->log_path . '/' . $this->log_file );
		}
	}
	
	private function set_ipv4() {
	
		if ( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			
			$this->ipv4 = $_SERVER['HTTP_CLIENT_IP'];
			
		} elseif ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			
			$this->ipv4 = $_SERVER['HTTP_X_FORWARDED_FOR'];
			
		} else {
			
			$this->ipv4 = $_SERVER['REMOTE_ADDR'];
		}
		
		$this->ipv4 = false;
	}
	
	private function is_excluded( $mx ) {
		
		$excluded = array('outlook.com');
		
		if ( preg_match('/[a-z0-9\-]{1,63}\.[a-z\.]{2,6}$/', strtolower( $mx ), $matches ) ) {
			
			return in_array( $matches[0], $excluded );
		}
		
		return true;
	}
	
	private function validate( $email ) {
		
		if ( $hosts = $this->getMXHost( $email ) ) {
			
			if ( isset( $hosts[0]['exchange'] ) ) {
				
				if ( $this->is_excluded( $hosts[0]['exchange'] ) ) return true;
				
				if ( $this->connect( $hosts[0]['exchange'], 25, $timeout = 5, null ) ) {
					
					if ( $this->hello( $this->domain ) ) {
						
						if ( $this->mail( $email ) ) {
							
							if ( !$this->verify( $this->user ) ) {
								
								if ( !$this->recipient( $email ) ) {
									
									return false;
								}
							}
							
							return true;
						}
					}
				}
			}
		}
		
		return false;
	}
	
	private function getMXHost( $email ) {
		
		if ( !filter_var( $email, FILTER_VALIDATE_EMAIL) === false ) {
			
			list( $this->user, $this->domain ) = explode( '@', $email ); 
			
			try {
				
				if( !getmxrr( $this->domain, $hosts, $pref ) ) {
					
					return false;
				}
				
				foreach( $hosts as $key => $value) {
					
					$result[ $key ]['preference'] = $pref[ $key ];
					
					$result[ $key ]['exchange'] = $value;
				}
				
				usort( $result, array( &$this, 'psort') );
				
				return $result;
				
			} catch ( Exception $e ) {
					
				return false;
			}
		}
		
		return false;
	}
	
	// PHP <= 5.2.17
	private function psort( $a, $b ) {
		
		return  $a['preference'] - $b['preference'];
	}
}

function init_cf7_smtp_verify() {
	
	if ( !class_exists( 'WPCF7_Shortcode' ) ) return;
	
	$cf7_smtp_verify = new CF7_SMTP_Verify();
}
add_action( 'plugins_loaded', 'init_cf7_smtp_verify' );