<?php
/**
*
* @package auth
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* Facebook OAuth service
*
* @package auth
*/
class phpbb_auth_provider_oauth_service_facebook extends phpbb_auth_provider_oauth_service_base
{
	/**
	* phpBB config
	*
	* @var phpbb_config
	*/
	protected $config;

	/**
	* Constructor
	*
	* @param	phpbb_config 	$config
	*/
	public function __construct(phpbb_config $config)
	{
		$this->config = $config;
	}

	/**
	* {@inheritdoc}
	*/
	public function get_service_credentials()
	{
		return array(
			'key'		=> $this->config['auth_oauth_facebook_key'],
			'secret'	=> $this->config['auth_oauth_facebook_secret'],
		);
	}

	/**
	* {@inheritdoc}
	*/
	public function perform_auth_login()
	{
		if (!($this->service_provider instanceof \OAuth\OAuth2\Service\Facebook))
		{
			// TODO: make exception class and use language constant
			throw new Exception('Invalid service provider type');
		}

		// This was a callback request from bitly, get the token
		$this->service_provider->requestAccessToken( $this->request->variable('code', '') );

		// Send a request with it
		$result = json_decode( $this->service_provider->request('/me'), true );

		// Return the unique identifier returned from bitly
		return $result['id'];
	}
}
